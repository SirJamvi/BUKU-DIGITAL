<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\TemporaryOrder;
use App\Models\AiOrderDraft;
use App\Services\Ai\AiOrderDebouncer;
use App\Services\Ai\AiOrderProcessor;
use App\Services\Whatsapp\PhoneNumberHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AiOrderWebhookController extends Controller
{
    protected AiOrderProcessor $processor;

    public function __construct(AiOrderProcessor $processor)
    {
        $this->processor = $processor;
    }

    /**
     * Endpoint Penerima Webhook WhatsApp Fonnte (POST /api/v1/webhook/fonnte).
     * Menerapkan filtrasi grup cepat, secret token, idempotensi, dan debounce 7 detik.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // ==============================================================
        // 1. FILTRASI PESAN GRUP WHATSAPP (sebelum lock, DB, atau log berat)
        // ==============================================================
        $rawSender = $payload['sender'] ?? $payload['from'] ?? $payload['phone'] ?? $request->input('sender') ?? $request->input('from') ?? $request->input('phone') ?? '';
        $senderRaw = is_array($rawSender) ? (string) ($rawSender[0] ?? '') : (string) $rawSender;
        $senderRaw = trim($senderRaw);

        $isGroup = (isset($payload['isgroup']) && ($payload['isgroup'] === true || $payload['isgroup'] === 'true' || $payload['isgroup'] == 1))
            || (isset($payload['isGroup']) && ($payload['isGroup'] === true || $payload['isGroup'] === 'true' || $payload['isGroup'] == 1))
            || str_ends_with(strtolower($senderRaw), '@g.us');

        if ($isGroup) {
            // Perintah staf di grup admin (ambil <nomor> / lepas <nomor>)
            $adminGroupId = trim((string) config('ai_order.admin_group_id', ''));
            $adminMembers = config('ai_order.admin_members', []);

            if (!empty($adminGroupId) && !empty($adminMembers)) {
                $cleanGroupSender = str_replace('@g.us', '', $senderRaw);
                $cleanAdminGroup  = str_replace('@g.us', '', $adminGroupId);

                if ($cleanGroupSender === $cleanAdminGroup) {
                    $rawMember = $payload['member'] ?? $request->input('member') ?? '';
                    $member = PhoneNumberHelper::normalize((string) $rawMember);

                    $normalizedMembers = array_map([PhoneNumberHelper::class, 'normalize'], (array) $adminMembers);
                    if (!empty($member) && in_array($member, $normalizedMembers, true)) {
                        $rawMessage = $request->input('message') ?? $request->input('text') ?? $payload['message'] ?? $payload['text'] ?? '';
                        $msgText = trim((string) $rawMessage);

                        // 1. Perintah: ambil <nomor>
                        if (preg_match('/^ambil\s+(\+?\d[\d\s\-]+)/i', $msgText, $m)) {
                            $targetPhone = PhoneNumberHelper::normalize($m[1]);
                            $takeoverMins = (int) config('ai_order.staff_takeover_minutes', 30);
                            Cache::put('human_mode_' . $targetPhone, true, now()->addMinutes($takeoverMins));
                            $reply = "✅ Bot diheningkan untuk {$targetPhone} selama {$takeoverMins} menit.";
                            app(\App\Services\Whatsapp\FonnteService::class)->sendMessage($senderRaw, $reply);
                            Log::info("[AiOrderWebhook] Staf {$member} mengambil alih percakapan {$targetPhone} selama {$takeoverMins}m");
                            return response()->json(['status' => 'success', 'command' => 'ambil', 'target' => $targetPhone], 200);
                        }

                        // 2. Perintah: lepas <nomor>
                        if (preg_match('/^lepas\s+(\+?\d[\d\s\-]+)/i', $msgText, $m)) {
                            $targetPhone = PhoneNumberHelper::normalize($m[1]);
                            Cache::forget('human_mode_' . $targetPhone);
                            $reply = "✅ Bot diaktifkan kembali untuk {$targetPhone}.";
                            app(\App\Services\Whatsapp\FonnteService::class)->sendMessage($senderRaw, $reply);
                            Log::info("[AiOrderWebhook] Staf {$member} melepaskan pengambilalihan {$targetPhone}");
                            return response()->json(['status' => 'success', 'command' => 'lepas', 'target' => $targetPhone], 200);
                        }
                    }
                }
            }

            \App\Services\Ai\AdminGroupNotifier::logDiscoveredGroupOnce($senderRaw);
            return response()->json(['status' => 'ignored', 'reason' => 'group'], 200);
        }

        // ==============================================================
        // 2. VERIFIKASI WEBHOOK SECRET (OPSIONAL)
        // ==============================================================
        $expectedSecret = config('ai_order.webhook_secret');
        if (!empty($expectedSecret)) {
            $providedSecret = $request->header('X-Fonnte-Secret')
                ?? $request->header('Authorization')
                ?? $request->query('secret');
            if ($providedSecret !== $expectedSecret && $providedSecret !== 'Bearer ' . $expectedSecret) {
                return response()->json(['status' => 'unauthorized', 'message' => 'Invalid webhook secret'], 401);
            }
        }

        $sender = PhoneNumberHelper::normalize($senderRaw);
        if (empty($sender)) {
            return response()->json(['status' => 'ignored', 'message' => 'Pengirim kosong'], 200);
        }

        $rawMessage = $request->input('message') ?? $request->input('text') ?? $payload['message'] ?? $payload['text'] ?? '';
        if (is_array($rawMessage)) {
            $message = !empty($rawMessage) && is_scalar($rawMessage[0]) ? (string) $rawMessage[0] : (empty($rawMessage) ? '' : json_encode($rawMessage));
        } else {
            $message = (string) $rawMessage;
        }
        $message = trim($message);

        $rawLocation = $payload['location'] ?? $request->input('location') ?? '';
        $location = is_array($rawLocation) ? implode(',', $rawLocation) : (string) $rawLocation;

        $rawTimestamp = $payload['timestamp'] ?? $payload['time'] ?? $request->input('timestamp') ?? '';
        $timestamp = is_array($rawTimestamp) ? (string) ($rawTimestamp[0] ?? '') : (string) $rawTimestamp;

        $msgType = !empty($location) ? 'location' : ($message === 'non-text message' ? 'media' : (empty($message) ? 'empty' : 'text'));
        $maskedSender = PhoneNumberHelper::mask($sender);

        // ==============================================================
        // 3. ATOMIC CACHE LOCK (ANTI-DUPLIKASI WEBHOOK RETRY FONNTE)
        // Nilai id/inboxid yang kosong, null, "0", atau 0 dianggap TIDAK ADA.
        // Kunci = sha256(sender ternormalisasi | timestamp | type | teks pesan | location). TTL 120 detik.
        // ==============================================================
        $rawId = $payload['id'] ?? $payload['inboxid'] ?? $request->input('id') ?? $request->input('inboxid') ?? null;
        if (is_array($rawId)) {
            $rawId = !empty($rawId) && is_scalar($rawId[0]) ? $rawId[0] : null;
        }

        $realId = null;
        if ($rawId !== null && $rawId !== '' && $rawId !== 0 && $rawId !== '0') {
            $realId = trim((string) $rawId);
        }

        if ($realId !== null) {
            $keyData = $sender . '|' . $realId;
        } else {
            $keyData = $sender . '|' . $timestamp . '|' . $msgType . '|' . $message . '|' . $location;
        }

        $keyHash = hash('sha256', $keyData);
        $hashShort = substr($keyHash, 0, 8);
        $lockKey = 'lock_msg_' . $keyHash;

        if (!Cache::add($lockKey, true, now()->addSeconds(120))) {
            Log::info("[AiOrderWebhook] Duplicate webhook request ignored: sender={$maskedSender}, reason=duplicate_lock, hash={$hashShort}, len=" . strlen($message));
            return response()->json(['status' => 'ignored', 'message' => 'Duplicate webhook request'], 200);
        }

        // ==============================================================
        // 4. LOG RINGKAS & AMAN (Masking 4 Digit Tengah, Hash 8 Karakter)
        // ==============================================================
        Log::info("[AiOrderWebhook] Masuk: sender={$maskedSender}, len=" . strlen($message) . ", type={$msgType}, hash={$hashShort}");

        try {
            $locationParam = !empty($location) ? $location : null;

            // ==============================================================
            // 5. HUMAN HANDOFF ("admin") TIDAK DI-DEBOUNCE (Diproses Langsung)
            // ==============================================================
            if (AiOrderDebouncer::isHumanHandoff($message) || Cache::has('human_mode_' . $sender)) {
                $result = $this->processor->process($sender, $message, $locationParam, $payload);
                return response()->json(['status' => 'success', 'data' => $result], 200);
            }

            // ==============================================================
            // 6. SIMPAN KE DEBOUNCE BUFFER & TENTUKAN TOKEN
            // ==============================================================
            [$token, $debounceSeconds] = AiOrderDebouncer::bufferMessage($sender, $message, $payload);

            $useAfterResponse = config('ai_order.after_response', true);
            $supportsFastCgi = function_exists('fastcgi_finish_request');
            $supportsLiteSpeed = function_exists('litespeed_finish_request');

            // ==============================================================
            // 7. STRATEGI AFTER-RESPONSE vs SYNCHRONOUS
            // ==============================================================
            if ($useAfterResponse && ($supportsFastCgi || $supportsLiteSpeed)) {
                // Kirim HTTP 200 langsung ke Fonnte secepat kilat
                $response = response()->json([
                    'status'  => 'accepted',
                    'message' => 'Buffered for debounce',
                ], 200);

                $response->send();
                if ($supportsFastCgi) {
                    fastcgi_finish_request();
                } elseif ($supportsLiteSpeed) {
                    litespeed_finish_request();
                }

                // Eksekusi penundaan debounce pada worker PHP yang sama
                $this->executeDebounceWaitAndProcess($sender, $token, $debounceSeconds);
                return $response;
            }

            // Fallback sinkron (menunggu debounceSeconds)
            $processResult = $this->executeDebounceWaitAndProcess($sender, $token, $debounceSeconds);
            return response()->json($processResult ?? ['status' => 'success'], 200);

        } catch (\Throwable $e) {
            Cache::forget($lockKey);
            Log::error("[AiOrderWebhook] Exception saat memproses webhook: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Menunggu durasi debounce, memeriksa token versi terbaru, dan memproses gabungan bubble.
     */
    protected function executeDebounceWaitAndProcess(string $sender, string $token, int $debounceSeconds): ?array
    {
        // Tunggu hingga jeda bubble selesai (misal 7 detik untuk pesan biasa, 2 detik untuk batal/ubah)
        sleep($debounceSeconds);

        // Jika ada bubble yang lebih baru masuk saat kita menunggu, batalkan pemrosesan request lama ini
        if (!AiOrderDebouncer::isLatestToken($sender, $token)) {
            Log::info("[AiOrderWebhook] Token " . substr($token, -6) . " usang untuk " . PhoneNumberHelper::mask($sender) . ", ada bubble lebih baru.");
            return ['status' => 'superseded'];
        }

        // Ambil lock per pengirim agar dua proses tidak berjalan bersamaan
        $procLockKey = 'proc_lock_' . $sender;
        $lock = Cache::lock($procLockKey, 30);

        try {
            $lock->block(10); // tunggu maksimal 10 detik

            // Verifikasi kembali setelah mendapatkan lock
            if (!AiOrderDebouncer::isLatestToken($sender, $token)) {
                return ['status' => 'superseded'];
            }

            $flushResult = AiOrderDebouncer::flushBuffer($sender);
            $bufferItems = $flushResult['items'];
            $bubblesIn = $flushResult['bubbles_in'];
            $bubblesProcessed = $flushResult['bubbles_processed'];

            if ($bubblesIn !== $bubblesProcessed) {
                Log::warning("[AiOrderWebhook] Wave mismatch: bubbles_in={$bubblesIn}, bubbles_processed={$bubblesProcessed} untuk " . PhoneNumberHelper::mask($sender));
            } else {
                Log::info("[AiOrderWebhook] Wave match: bubbles_in={$bubblesIn}, bubbles_processed={$bubblesProcessed} untuk " . PhoneNumberHelper::mask($sender));
            }

            if (empty($bufferItems)) {
                return ['status' => 'empty_buffer'];
            }

            $combined = AiOrderDebouncer::combineBuffer($bufferItems);
            Log::info("[AiOrderWebhook] Memproses " . count($bufferItems) . " bubble untuk " . PhoneNumberHelper::mask($sender) . ": " . str_replace("\n", " | ", $combined['combined_message']));

            return $this->processor->process(
                $sender,
                $combined['combined_message'],
                $combined['location'],
                $combined['last_payload']
            );
        } catch (\Throwable $e) {
            Log::error("[AiOrderWebhook] Gagal menjalankan executeDebounceWaitAndProcess: " . $e->getMessage());
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Endpoint API untuk Kasir: Mengambil antrean pesanan AI berstatus 'validated'
     * GET /api/v1/kasir/ai-orders
     */
    public function getValidatedOrders(Request $request): JsonResponse
    {
        // Lazy-expiry pesanan validated > 24 jam menjadi expired
        try {
            TemporaryOrder::lazyExpireOldOrders();
        } catch (\Throwable $e) {
            Log::warning("[AiOrderWebhook] Gagal menjalankan lazyExpireOldOrders: " . $e->getMessage());
        }

        $orders = TemporaryOrder::where('status', 'validated')
            ->with(['customer:id,name,phone,address,created_at'])
            ->latest('updated_at')
            ->get()
            ->map(function ($order) {
                $customerName = ($order->customer_id == 108 || !$order->customer)
                    ? 'Pelanggan Baru (' . $order->sender_phone . ')'
                    : $order->customer->name;

                // Cek koordinat dari draft atau alamat
                $lat = null;
                $lng = null;
                $address = $order->customer ? $order->customer->address : '-';

                $draft = AiOrderDraft::where('sender_phone', $order->sender_phone)->first();
                if ($draft && !empty($draft->data['lat']) && !empty($draft->data['lng'])) {
                    $lat = $draft->data['lat'];
                    $lng = $draft->data['lng'];
                } elseif (preg_match('/(-?\d+\.\d+),\s*(-?\d+\.\d+)/', $address, $m)) {
                    $lat = $m[1];
                    $lng = $m[2];
                }

                $mapUrl = ($lat && $lng) ? "https://www.google.com/maps?q={$lat},{$lng}" : null;
                $isNewCustomer = (bool) ($order->is_new_customer ?? ($order->customer_id == 108 || ($order->customer && $order->customer->created_at && $order->customer->created_at->isToday())));

                return [
                    'id'                      => $order->id,
                    'customer_id'             => $order->customer_id,
                    'customer_name'           => $customerName,
                    'sender_phone'            => $order->sender_phone,
                    'customer_address'        => $address,
                    'jumlah_ball'             => (fmod((float) $order->jumlah_ball, 1.0) === 0.0) ? (int) $order->jumlah_ball : (float) $order->jumlah_ball,
                    'ukuran_produk'           => $order->ukuran_produk,
                    'delivery_time'           => $order->delivery_time ? $order->delivery_time->format('Y-m-d H:i:s') : null,
                    'delivery_time_formatted' => $order->delivery_time 
                        ? (Carbon::parse($order->delivery_time)->isToday() 
                            ? Carbon::parse($order->delivery_time)->timezone('Asia/Jakarta')->format('H:i') 
                            : (Carbon::parse($order->delivery_time)->isTomorrow()
                                ? 'Besok, ' . Carbon::parse($order->delivery_time)->timezone('Asia/Jakarta')->format('H:i')
                                : Carbon::parse($order->delivery_time)->timezone('Asia/Jakarta')->translatedFormat('d M H:i')))
                        : null,
                    'status'                  => $order->status,
                    'last_chat'               => $order->last_chat,
                    'created_at'              => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : null,
                    'time_formatted'          => $order->created_at ? $order->created_at->timezone('Asia/Jakarta')->format('H:i') : '',
                    'time_ago'                => $order->created_at ? $order->created_at->locale('id')->diffForHumans() : '',
                    // Field opsional baru untuk kasir UI
                    'is_new_customer'         => $isNewCustomer,
                    'lat'                     => $lat,
                    'lng'                     => $lng,
                    'map_url'                 => $mapUrl,
                ];
            });

        return response()->json([
            'status' => 'success',
            'count'  => $orders->count(),
            'data'   => $orders,
        ]);
    }

    /**
     * Endpoint Kasir: Menandai pesanan telah selesai dipindahkan ke keranjang kasir
     * POST /api/v1/kasir/ai-orders/{id}/processed
     */
    public function markAsProcessed(int $id): JsonResponse
    {
        $order = TemporaryOrder::findOrFail($id);
        // Perbaikan: gunakan status 'processed' eksplisit
        $order->update(['status' => 'processed']);

        return response()->json([
            'status'  => 'success',
            'message' => "Order #{$id} berhasil dipindahkan ke transaksi kasir.",
        ]);
    }
}
