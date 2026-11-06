<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Customer;
use App\Models\CustomerPhone;
use App\Models\Product;
use App\Models\TemporaryOrder;
use App\Models\WaOrder;
use Carbon\Carbon;
use App\Services\Ai\LlmOrderService;
use App\Services\Whatsapp\FonnteService;
use App\Services\Whatsapp\PhoneNumberHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiOrderWebhookController extends Controller
{
    protected LlmOrderService $llmService;
    protected FonnteService $fonnteService;

    public function __construct(
        LlmOrderService $llmService,
        FonnteService $fonnteService
    ) {
        $this->llmService = $llmService;
        $this->fonnteService = $fonnteService;
    }

    /**
     * Endpoint Penerima Webhook WhatsApp Fonnte (POST /api/v1/webhook/fonnte).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // ==============================================================
        // 1. FILTRASI PESAN GRUP WHATSAPP (sebelum lock, DB, atau log berat)
        // ==============================================================
        $senderRaw = (string) ($payload['sender'] ?? $payload['from'] ?? $payload['phone'] ?? '');
        $isGroup = (isset($payload['isgroup']) && ($payload['isgroup'] === true || $payload['isgroup'] === 'true' || $payload['isgroup'] == 1))
            || (isset($payload['isGroup']) && ($payload['isGroup'] === true || $payload['isGroup'] === 'true' || $payload['isGroup'] == 1))
            || str_ends_with(strtolower($senderRaw), '@g.us');

        if ($isGroup) {
            return response()->json(['status' => 'ignored', 'reason' => 'group'], 200);
        }

        // ==============================================================
        // 2. VERIFIKASI WEBHOOK SECRET (OPSIONAL)
        // ==============================================================
        $expectedSecret = env('FONNTE_WEBHOOK_SECRET');
        if (!empty($expectedSecret)) {
            $providedSecret = $request->header('X-Fonnte-Secret')
                ?? $request->header('Authorization')
                ?? $request->query('secret');
            if ($providedSecret !== $expectedSecret && $providedSecret !== 'Bearer ' . $expectedSecret) {
                return response()->json(['status' => 'unauthorized', 'message' => 'Invalid webhook secret'], 401);
            }
        }

        $message = trim((string) ($payload['message'] ?? $payload['text'] ?? ''));

        // ==============================================================
        // 3. LOG RINGKAS & AMAN (Masking 4 Digit Tengah)
        // ==============================================================
        $msgType = !empty($payload['location']) ? 'location' : ($message === 'non-text message' ? 'media' : (empty($message) ? 'empty' : 'text'));
        $maskedSender = PhoneNumberHelper::mask($senderRaw);
        Log::info("[AiOrderWebhook] Masuk: sender={$maskedSender}, len=" . strlen($message) . ", type={$msgType}");

        // ==============================================================
        // 4. ATOMIC CACHE LOCK (ANTI-DUPLIKASI WEBHOOK RETRY FONNTE)
        // ==============================================================
        $msgId = $payload['id'] ?? $payload['inboxid'] ?? null;
        if (empty($msgId)) {
            $timestamp = $payload['timestamp'] ?? '';
            $msgId = hash('sha256', $senderRaw . '_' . $timestamp . '_' . $message);
        }

        $lockKey = 'lock_msg_' . $msgId;
        if (!Cache::add($lockKey, true, now()->addMinutes(5))) {
            Log::info("[AiOrderWebhook] Duplicate webhook request ignored: {$msgId}");
            return response()->json(['status' => 'ignored', 'message' => 'Duplicate webhook request'], 200);
        }

        try {
            $sender = PhoneNumberHelper::normalize($senderRaw);

            // Ekstrak nama sender dari payload
            $customerName = $payload['name'] ?? $payload['pushname'] ?? 'Kak';
            if (empty(trim($customerName))) {
                $customerName = 'Kak';
            }

            // Abaikan pesan media/VN jika non-text message (kecuali share location)
            if (($message === 'non-text message' || $message === 'non-button message') && empty($payload['location'])) {
                return response()->json(['status' => 'ignored', 'message' => 'Pesan media tidak didukung oleh konfigurasi saat ini'], 200);
            }

            if (empty($sender) || (empty($message) && empty($payload['location']))) {
                return response()->json([
                    'status'  => 'ignored',
                    'message' => 'Pengirim atau pesan kosong',
                ], 200);
            }

        // ==============================================================
        // 3. LOGIKA HUMAN HANDOFF (ADMIN MODE)
        // ==============================================================
        // Cek jika sedang dalam human mode aktif
        if (Cache::has('human_mode_' . $sender)) {
            Log::info("[AiOrderWebhook] Pengirim {$sender} sedang dalam human mode. Pesan diabaikan oleh bot.");
            return response()->json(['message' => 'ignored, human mode']);
        }

        // Cek jika isi pesan (lowercase) adalah "admin"
        if (strtolower($message) === 'admin') {
            Cache::put('human_mode_' . $sender, true, now()->addMinutes(15));
            $reply = "Baik {$customerName}, Anda telah terhubung dengan Admin. Silakan sampaikan pesan Anda, Admin kami akan segera membalas.";
            $this->fonnteService->sendMessage($sender, $reply);

            Log::info("[AiOrderWebhook] Human mode diaktifkan untuk {$sender} ({$customerName}) selama 15 menit.");

            return response()->json([
                'status'  => 'success',
                'action'  => 'human_mode_activated',
                'message' => $reply,
            ], 200);
        }

        // ==============================================================
        // 4. IDENTIFIKASI STATUS PELANGGAN (Model Customer & customer_phones)
        // ==============================================================
        $customer = $this->findCustomerByPhone($sender);
        $isNewCustomer = !Customer::where('phone', $sender)->exists() && !$customer;

        if ($customer && $customerName === 'Kak' && !empty($customer->name)) {
            $customerName = $customer->name;
        }

        $finalCustomerId = $customer ? $customer->id : 108;

        // ==============================================================
        // 5. AMBIL DATA STOK AKTUAL REAL-TIME
        // ==============================================================
        $stok15 = $this->getStockByUkuran('15kg');
        $stok20 = $this->getStockByUkuran('20kg');
        $infoStok = "Stok 15kg: {$stok15} ball. Stok 20kg: {$stok20} ball.";

        // ==============================================================
        // 6. CEK PEMBATALAN PESANAN (MENDUKUNG PENDING & VALIDATED / MASUK POS)
        // ==============================================================
        $activeOrder = TemporaryOrder::where('sender_phone', $sender)
            ->whereIn('status', ['pending', 'validated'])
            ->whereDate('created_at', Carbon::today())
            ->latest('id')
            ->first();

        // Pelanggan membalas pembatalan (misal: "batal", "cancel", "gak jadi")
        if ($this->isCancellation($message)) {
            if ($activeOrder) {
                $activeOrder->update([
                    'status'    => 'cancelled',
                    'last_chat' => $message,
                ]);

                $reply = "❌ Pesanan Anda ({$activeOrder->jumlah_ball} Ball Es Kristal {$activeOrder->ukuran_produk}) telah dibatalkan. Silakan chat kembali jika ingin membuat pesanan baru.";
                $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
                $this->fonnteService->sendMessage($sender, $finalMessage);

                Log::info("[AiOrderWebhook] Order #{$activeOrder->id} ({$activeOrder->status}) berhasil dibatalkan oleh pelanggan.");

                return response()->json([
                    'status'        => 'success',
                    'action'        => 'cancelled',
                    'order_id'      => $activeOrder->id,
                    'reply_message' => $finalMessage,
                ], 200);
            }

            $reply = "Tidak ada pesanan aktif (menunggu konfirmasi atau antrean kasir) yang dapat dibatalkan saat ini.";
            $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
            $this->fonnteService->sendMessage($sender, $finalMessage);

            return response()->json([
                'status'        => 'ignored',
                'message'       => 'Tidak ada pesanan aktif untuk dibatalkan',
                'reply_message' => $finalMessage,
            ], 200);
        }

        // --------------------------------------------------------------
        // KASUS A: CUSTOMER SEDANG MEMILIKI PESANAN PENDING
        // --------------------------------------------------------------
        $pendingOrder = ($activeOrder && $activeOrder->status === 'pending')
            ? $activeOrder
            : TemporaryOrder::where('sender_phone', $sender)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

        if ($pendingOrder) {
            // Pelanggan membalas konfirmasi "oke" / "ya"
            if ($this->isConfirmation($message)) {
                $pendingOrder->update([
                    'status'    => 'validated',
                    'last_chat' => $message,
                ]);

                // Kirim notifikasi konfirmasi ke pelanggan
                $reply = "Pesanan terkonfirmasi! Pesanan {$pendingOrder->jumlah_ball} Ball Es Kristal {$pendingOrder->ukuran_produk} Anda telah masuk ke antrean kasir. Terima kasih!";
                $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
                $this->fonnteService->sendMessage($sender, $finalMessage);

                Log::info("[AiOrderWebhook] Order #{$pendingOrder->id} diubah menjadi 'validated' dan siap di-drag kasir.");

                return response()->json([
                    'status'        => 'success',
                    'action'        => 'validated',
                    'order_id'      => $pendingOrder->id,
                    'reply_message' => $finalMessage,
                ], 200);
            }

            // Pelanggan mengirim revisi (misal: "bukan, yang 15kg saja")
            $parsed = $this->llmService->parseOrder($message, $isNewCustomer, $customerName, $infoStok);

            // Cek jika pesanan melebihi stok yang tersedia
            if (!empty($parsed['is_out_of_stock'])) {
                $reply = $parsed['reply'];
                $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
                $this->fonnteService->sendMessage($sender, $finalMessage);

                return response()->json([
                    'status'        => 'out_of_stock',
                    'message'       => 'Revisi pesanan melebihi sisa stok',
                    'reply_message' => $finalMessage,
                ], 200);
            }

            // Cek jika respons AI berupa pendaftaran data pelanggan baru
            if (isset($parsed['is_registration']) && ($parsed['is_registration'] === true || $parsed['is_registration'] === 'true')) {
                $namaToko = !empty($parsed['nama_toko']) ? trim((string)$parsed['nama_toko']) : 'Pelanggan Baru (' . $sender . ')';
                $alamat = !empty($parsed['alamat']) ? trim((string)$parsed['alamat']) : '-';

                $defaultBusinessId = Customer::first()?->business_id ?? 1;

                $newCustomer = Customer::create([
                    'business_id' => $defaultBusinessId,
                    'name'        => $namaToko,
                    'phone'       => $sender,
                    'address'     => $alamat,
                    'join_date'   => now(),
                    'status'      => 'active',
                ]);

                if ($newCustomer) {
                    try {
                        $newCustomer->phones()->create([
                            'phone'      => $sender,
                            'label'      => 'Utama',
                            'is_primary' => true,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("[AiOrderWebhook] Gagal insert customer_phones: " . $e->getMessage());
                    }

                    $pendingOrder->update([
                        'customer_id' => $newCustomer->id,
                    ]);
                }

                Log::info("[AiOrderWebhook] Auto-Registration berhasil untuk {$sender}: {$namaToko} - {$alamat} (ID: {$newCustomer->id})");

                $reply = "Terima kasih, data toko Anda ({$namaToko}, {$alamat}) telah berhasil didaftarkan! Silakan ketik ulang pesanan Es Kristal Anda.";
                $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
                $this->fonnteService->sendMessage($sender, $finalMessage);

                return response()->json([
                    'status'        => 'success',
                    'action'        => 'customer_registered',
                    'customer_id'   => $newCustomer->id,
                    'reply_message' => $finalMessage,
                ], 200);
            }

            if (!$parsed || !empty($parsed['is_new_customer'])) {
                $reply = "Pesanan Anda ({$pendingOrder->jumlah_ball} Ball Es Kristal {$pendingOrder->ukuran_produk}) sedang menunggu konfirmasi. Balas OKE untuk memproses ke kasir atau BATAL jika ingin membatalkan.";
                $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
                $this->fonnteService->sendMessage($sender, $finalMessage);

                return response()->json([
                    'status'        => 'pending_reminder',
                    'message'       => 'Chat bukan revisi pesanan',
                    'order_id'      => $pendingOrder->id,
                    'reply_message' => $finalMessage,
                ], 200);
            }

            $pendingOrder->update([
                'jumlah_ball'   => $parsed['jumlah_ball'],
                'ukuran_produk' => $parsed['ukuran_produk'],
                'delivery_time' => $parsed['waktu_pengiriman'] ?? null,
                'last_chat'     => $message,
            ]);

            $reply = "Pesanan diterima: {$parsed['jumlah_ball']} Ball Es Kristal {$parsed['ukuran_produk']}.";

            if ($customer && $customer->getTotalKasbon() > 0) {
                $totalKasbonFormatted = number_format($customer->getTotalKasbon(), 0, ',', '.');
                $reply .= " Namun saat ini sistem mencatat ada tagihan kasbon sebesar Rp {$totalKasbonFormatted} yang belum lunas. Mohon titipkan pelunasan ke supir kami saat pengiriman ya. Balas OKE untuk konfirmasi.";
            } else {
                $reply .= " Balas OKE untuk konfirmasi.";
            }
            $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
            $this->fonnteService->sendMessage($sender, $finalMessage);

            return response()->json([
                'status'        => 'success',
                'action'        => 'revised',
                'order_id'      => $pendingOrder->id,
                'reply_message' => $finalMessage,
                'data'          => $parsed,
            ], 200);
        }

        // --------------------------------------------------------------
        // KASUS B: PESANAN BARU (BELUM ADA PENDING ORDER)
        // --------------------------------------------------------------
        $parsed = $this->llmService->parseOrder($message, $isNewCustomer, $customerName, $infoStok);

        // Cek jika pesanan melebihi stok yang tersedia
        if (!empty($parsed['is_out_of_stock'])) {
            $reply = $parsed['reply'];
            $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
            $this->fonnteService->sendMessage($sender, $finalMessage);

            return response()->json([
                'status'        => 'out_of_stock',
                'message'       => 'Pesanan melebihi stok yang tersedia',
                'reply_message' => $finalMessage,
            ], 200);
        }

        // Jika pesan bernilai null (sapaan seperti "bang", "halo", "p", dll), sistem JANGAN membuat TemporaryOrder
        if (!$parsed) {
            $reply = "Halo {$customerName}! Ada yang bisa kami bantu untuk pemesanan Es Kristalnya? (Sedia ukuran 20kg, 15kg, 10kg).";
            $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
            $this->fonnteService->sendMessage($sender, $finalMessage);

            return response()->json([
                'status'        => 'greeting_replied',
                'message'       => 'Pesan berupa sapaan/bukan format pesanan',
                'reply_message' => $finalMessage,
            ], 200);
        }

        // Cek jika respons AI berupa pendaftaran data pelanggan baru (is_registration: true)
        if (isset($parsed['is_registration']) && ($parsed['is_registration'] === true || $parsed['is_registration'] === 'true')) {
            $namaToko = !empty($parsed['nama_toko']) ? trim((string)$parsed['nama_toko']) : 'Pelanggan Baru (' . $sender . ')';
            $alamat = !empty($parsed['alamat']) ? trim((string)$parsed['alamat']) : '-';

            $defaultBusinessId = Customer::first()?->business_id ?? 1;

            $newCustomer = Customer::create([
                'business_id' => $defaultBusinessId,
                'name'        => $namaToko,
                'phone'       => $sender,
                'address'     => $alamat,
                'join_date'   => now(),
                'status'      => 'active',
            ]);

            if ($newCustomer) {
                try {
                    $newCustomer->phones()->create([
                        'phone'      => $sender,
                        'label'      => 'Utama',
                        'is_primary' => true,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("[AiOrderWebhook] Gagal insert customer_phones: " . $e->getMessage());
                }
            }

            Log::info("[AiOrderWebhook] Auto-Registration berhasil untuk {$sender}: {$namaToko} - {$alamat} (ID: {$newCustomer->id})");

            // PERBAIKAN LOGIKA REGISTRASI (One-Step Registration):
            // Cek apakah response juga memiliki key jumlah_ball dan ukuran_produk
            if (!empty($parsed['jumlah_ball']) && !empty($parsed['ukuran_produk'])) {
                $newOrder = TemporaryOrder::create([
                    'customer_id'   => $newCustomer->id,
                    'sender_phone'  => $sender,
                    'jumlah_ball'   => $parsed['jumlah_ball'],
                    'ukuran_produk' => $parsed['ukuran_produk'],
                    'delivery_time' => $parsed['waktu_pengiriman'] ?? null,
                    'status'        => 'pending',
                    'last_chat'     => $message,
                ]);

                $reply = "Data toko berhasil didaftarkan! Pesanan Anda: {$parsed['jumlah_ball']} ball {$parsed['ukuran_produk']}. Balas 'oke' untuk konfirmasi masuk antrean kasir.";
                $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
                $this->fonnteService->sendMessage($sender, $finalMessage);

                return response()->json([
                    'status'        => 'success',
                    'action'        => 'registered_and_pending_created',
                    'customer_id'   => $newCustomer->id,
                    'order_id'      => $newOrder->id,
                    'reply_message' => $finalMessage,
                    'data'          => $parsed,
                ], 200);
            }

            // Jika hanya registrasi tanpa pesanan
            $reply = "Terima kasih, data toko Anda ({$namaToko}, {$alamat}) telah berhasil didaftarkan! Silakan ketik ulang pesanan Es Kristal Anda.";
            $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
            $this->fonnteService->sendMessage($sender, $finalMessage);

            return response()->json([
                'status'        => 'success',
                'action'        => 'customer_registered',
                'customer_id'   => $newCustomer->id,
                'reply_message' => $finalMessage,
            ], 200);
        }

        // JIKA PELANGGAN BARU (Pesanan ditolak/tidak diproses sampai data lengkap diberikan)
        if (!empty($parsed['is_new_customer']) || $isNewCustomer) {
            $reply = $parsed['reply'] ?? "Halo {$customerName}! Sebelum kami dapat memproses pesanan Anda, mohon kirimkan Nama Toko/Cafe dan Alamat Lengkap Anda terlebih dahulu ya.";
            $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
            $this->fonnteService->sendMessage($sender, $finalMessage);

            return response()->json([
                'status'        => 'new_customer_validation',
                'message'       => 'Pelanggan baru wajib memberikan Nama Toko dan Alamat',
                'reply_message' => $finalMessage,
            ], 200);
        }

        $newOrder = TemporaryOrder::create([
            'customer_id'   => $finalCustomerId,
            'sender_phone'  => $sender,
            'jumlah_ball'   => $parsed['jumlah_ball'],
            'ukuran_produk' => $parsed['ukuran_produk'],
            'delivery_time' => $parsed['waktu_pengiriman'] ?? null,
            'status'        => 'pending',
            'last_chat'     => $message,
        ]);

        // Logika AI Penagih Kasbon & Balasan WhatsApp (Pelanggan Lama)
        $reply = "Pesanan diterima: {$parsed['jumlah_ball']} Ball Es Kristal {$parsed['ukuran_produk']}.";

        if ($customer && $customer->getTotalKasbon() > 0) {
            $totalKasbonFormatted = number_format($customer->getTotalKasbon(), 0, ',', '.');
            $reply .= " Namun saat ini sistem mencatat ada tagihan kasbon sebesar Rp {$totalKasbonFormatted} yang belum lunas. Mohon titipkan pelunasan ke supir kami saat pengiriman ya. Balas OKE untuk konfirmasi.";
        } else {
            $reply .= " Balas OKE untuk konfirmasi.";
        }
        $finalMessage = $reply . "\n\n_Ketik *admin* untuk chat langsung dengan admin adin crystal_";
        $this->fonnteService->sendMessage($sender, $finalMessage);

        return response()->json([
            'status'        => 'success',
            'action'        => 'pending_created',
            'order_id'      => $newOrder->id,
            'reply_message' => $finalMessage,
            'data'          => $parsed,
        ], 200);
        } catch (\Throwable $e) {
            Cache::forget($lockKey);
            Log::error("[AiOrderWebhook] Error saat memproses webhook: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Endpoint API untuk Kasir: Mengambil antrean pesanan AI berstatus 'validated'
     * GET /api/v1/kasir/ai-orders
     */
    public function getValidatedOrders(Request $request): JsonResponse
    {
        $orders = TemporaryOrder::where('status', 'validated')
            ->with(['customer:id,name,phone,address'])
            ->latest('updated_at')
            ->get()
            ->map(function ($order) {
                $customerName = ($order->customer_id == 108 || !$order->customer)
                    ? 'Pelanggan Baru (' . $order->sender_phone . ')'
                    : $order->customer->name;

                return [
                    'id'                      => $order->id,
                    'customer_id'             => $order->customer_id,
                    'customer_name'           => $customerName,
                    'sender_phone'            => $order->sender_phone,
                    'customer_address'        => $order->customer ? $order->customer->address : '-',
                    'jumlah_ball'             => (int) $order->jumlah_ball,
                    'ukuran_produk'           => $order->ukuran_produk,
                    'delivery_time'           => $order->delivery_time ? $order->delivery_time->format('Y-m-d H:i:s') : null,
                    'delivery_time_formatted' => $order->delivery_time 
                        ? (\Carbon\Carbon::parse($order->delivery_time)->isToday() 
                            ? \Carbon\Carbon::parse($order->delivery_time)->timezone('Asia/Jakarta')->format('H:i') 
                            : (\Carbon\Carbon::parse($order->delivery_time)->isTomorrow()
                                ? 'Besok, ' . \Carbon\Carbon::parse($order->delivery_time)->timezone('Asia/Jakarta')->format('H:i')
                                : \Carbon\Carbon::parse($order->delivery_time)->timezone('Asia/Jakarta')->translatedFormat('d M H:i')))
                        : null,
                    'status'                  => $order->status,
                    'last_chat'               => $order->last_chat,
                    'created_at'              => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : null,
                    'time_formatted'          => $order->created_at ? $order->created_at->timezone('Asia/Jakarta')->format('H:i') : '',
                    'time_ago'                => $order->created_at ? $order->created_at->locale('id')->diffForHumans() : '',
                ];
            });

        return response()->json([
            'status' => 'success',
            'count'  => $orders->count(),
            'data'   => $orders,
        ]);
    }

    /**
     * Endpoint Opsional: Menandai pesanan telah selesai dipindahkan ke keranjang kasir
     * POST /api/v1/kasir/ai-orders/{id}/processed
     */
    public function markAsProcessed(int $id): JsonResponse
    {
        $order = TemporaryOrder::findOrFail($id);
        $order->update(['status' => 'cancelled']);

        return response()->json([
            'status'  => 'success',
            'message' => "Order #{$id} berhasil dipindahkan ke transaksi kasir.",
        ]);
    }

    /**
     * Cari customer berdasarkan variasi nomor telepon WhatsApp (mendukung multi-nomor di customer_phones).
     */
    protected function findCustomerByPhone(string $phone): ?Customer
    {
        $variations = PhoneNumberHelper::variations($phone);
        if (empty($variations)) {
            return null;
        }

        // 1. Cari langsung di tabel customers (nomor utama)
        $customer = Customer::whereIn('phone', $variations)->first();

        // 2. Jika tidak ditemukan, cari di relasi customer_phones (multi-nomor)
        if (!$customer) {
            $customerPhone = CustomerPhone::whereIn('phone', $variations)->first();
            if ($customerPhone) {
                $customer = $customerPhone->customer;
            }
        }

        // 3. Fallback suffix 9 digit jika masih belum cocok tepat
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (!$customer && strlen($clean) >= 9) {
            $suffix = substr($clean, -9);
            $customer = Customer::where('phone', 'like', "%{$suffix}")->first();
            if (!$customer) {
                $customerPhone = CustomerPhone::where('phone', 'like', "%{$suffix}")->first();
                if ($customerPhone) {
                    $customer = $customerPhone->customer;
                }
            }
        }

        return $customer;
    }

    /**
     * Deteksi kata kunci konfirmasi manual.
     */
    protected function isConfirmation(string $message): bool
    {
        $clean = strtolower(trim($message));
        $keywords = [
            'oke', 'ok', 'ya', 'okedeh', 'lanjut', 'sip', 'iya', 'gass', 'kirim',
            'sesuai', 'siap', 'betul', 'benar', 'deal', 'gas', 'acc', 'yup'
        ];

        if (in_array($clean, $keywords)) {
            return true;
        }

        foreach ($keywords as $kw) {
            if (str_starts_with($clean, $kw . ' ') || str_starts_with($clean, $kw . ',') || str_starts_with($clean, $kw . '!')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deteksi kata kunci pembatalan.
     */
    protected function isCancellation(string $message): bool
    {
        $clean = strtolower(trim($message));
        $keywords = [
            'batal', 'cancel', 'ga jadi', 'gajadi', 'gak jadi', 'gakjadi', 'ngga dulu', 'ga dulu', 'tidak', 'engga', 'stop',
            'nggak jadi', 'tidak jadi'
        ];

        if (in_array($clean, $keywords)) {
            return true;
        }

        foreach ($keywords as $kw) {
            if (str_contains($clean, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mengambil stok aktual produk berdasarkan ukuran.
     */
    protected function getStockByUkuran(string $ukuran): int
    {
        try {
            if (Schema::hasColumn('products', 'ukuran')) {
                $stockCol = Schema::hasColumn('products', 'stok') ? 'stok' : 'current_stock';
                $val = Product::where('ukuran', $ukuran)->value($stockCol);
                if ($val !== null) {
                    return (int) $val;
                }
            }

            $cleanUkuran = str_replace('kg', '', strtolower($ukuran));
            $product = Product::where('name', 'like', "%{$cleanUkuran}%")
                ->with('inventory')
                ->first();

            if ($product) {
                if ($product->inventory && isset($product->inventory->current_stock)) {
                    return (int) $product->inventory->current_stock;
                }
                if (isset($product->stok)) {
                    return (int) $product->stok;
                }
                if (isset($product->current_stock)) {
                    return (int) $product->current_stock;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[AiOrderWebhook] Gagal mengambil data stok {$ukuran}: " . $e->getMessage());
        }

        return 0;
    }
}
