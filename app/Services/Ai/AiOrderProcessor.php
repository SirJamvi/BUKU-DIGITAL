<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Customer;
use App\Models\CustomerPhone;
use App\Models\TemporaryOrder;
use App\Models\AiOrderDraft;
use App\Services\Whatsapp\FonnteService;
use App\Services\Whatsapp\PhoneNumberHelper;

class AiOrderProcessor
{
    protected LlmOrderService $llmService;
    protected FonnteService $fonnteService;
    protected AdminGroupNotifier $adminNotifier;

    public function __construct(
        LlmOrderService $llmService,
        FonnteService $fonnteService,
        AdminGroupNotifier $adminNotifier
    ) {
        $this->llmService = $llmService;
        $this->fonnteService = $fonnteService;
        $this->adminNotifier = $adminNotifier;
    }

    /**
     * Memproses pesan gabungan yang sudah melewati tahap debouncing.
     *
     * @param string $sender Nomor WhatsApp pengirim (kanonik)
     * @param string $combinedMessage Teks pesan gabungan
     * @param string|null $location Koordinat lokasi jika ada ("lat,lng")
     * @param array $payload Payload mentah terakhir dari Fonnte
     * @return array [status, reply, order_id]
     */
    public function process(string $sender, string $combinedMessage, ?string $location = null, array $payload = []): array
    {
        $canonicalSender = PhoneNumberHelper::normalize($sender);

        // ==============================================================
        // 1. RATE LIMITING PER NOMOR (Default 20 pesan / menit)
        // ==============================================================
        $rateLimitMax = (int) config('ai_order.rate_limit', 20);
        $rateKey = 'rate_limit_' . $canonicalSender;
        $currentHits = (int) Cache::get($rateKey, 0);

        if ($currentHits >= $rateLimitMax) {
            Log::warning("[AiOrderProcessor] Rate limit terlampaui untuk {$canonicalSender} ({$currentHits} hits/menit)");
            return ['status' => 'rate_limited', 'reply' => null];
        }
        Cache::put($rateKey, $currentHits + 1, now()->addMinute());

        // ==============================================================
        // 2. CEK STATUS HUMAN HANDOFF / ADMIN
        // ==============================================================
        if (Cache::has('human_mode_' . $canonicalSender)) {
            Log::info("[AiOrderProcessor] Pengirim {$canonicalSender} sedang dalam human mode aktif. Diabaikan.");
            return ['status' => 'ignored_human_mode', 'reply' => null];
        }

        // ==============================================================
        // 3. IDENTIFIKASI PELANGGAN (LAMA vs BARU)
        // ==============================================================
        $customer = $this->findCustomerByPhone($canonicalSender);
        $isNewCustomer = ($customer === null);
        $customerName = $customer ? $customer->name : 'Kak';

        // ==============================================================
        // 4. KELOLA DRAFT STATE & ORDER TERAKHIR
        // ==============================================================
        $draft = AiOrderDraft::getOrCreateDraft($canonicalSender);

        // Lazy-expiry pesanan validated > 24 jam
        try {
            TemporaryOrder::lazyExpireOldOrders();
        } catch (\Throwable $e) {
            Log::warning("[AiOrderProcessor] Gagal lazyExpireOldOrders: " . $e->getMessage());
        }

        // Ambil pesanan terakhir pengirim
        $latestOrder = TemporaryOrder::where('sender_phone', $canonicalSender)
            ->latest('id')
            ->first();

        // ==============================================================
        // 4b. DETEKSI & ROUTING INTENT (Fase 12)
        // ==============================================================
        $hasDraftSlots = $draft->hasQty() || $draft->hasUkuran() || !empty($draft->data['nama_toko']);
        $intent = $this->detectIntent($combinedMessage, $isNewCustomer, $hasDraftSlots);
        Log::info("[AiOrderProcessor] Detected intent: {$intent}");

        // (a) delivery_status_or_complaint
        if ($intent === 'delivery_status_or_complaint') {
            $handoffMinutes = (int) config('ai_order.handoff_minutes', 30);
            Cache::put('human_mode_' . $canonicalSender, true, now()->addMinutes($handoffMinutes));
            Cache::put('prior_chat_24h_' . $canonicalSender, true, now()->addHours(24));

            $reply = ReplyTemplates::render('delivery_complaint');
            $this->sendReply($canonicalSender, $reply);

            try {
                $this->adminNotifier->notifyDeliveryComplaint($customerName, $canonicalSender, $combinedMessage);
            } catch (\Throwable $e) {
                Log::warning("[AiOrderProcessor] Gagal kirim notifikasi komplain: " . $e->getMessage());
            }

            Log::info("[AiOrderProcessor] delivery_status_or_complaint ditangani: human mode {$handoffMinutes}m aktif untuk " . PhoneNumberHelper::mask($canonicalSender));
            return ['status' => 'delivery_status_or_complaint', 'reply' => $reply];
        }

        // (b) other_human_needed
        if ($intent === 'other_human_needed') {
            $handoffMinutes = (int) config('ai_order.handoff_minutes', 30);
            Cache::put('human_mode_' . $canonicalSender, true, now()->addMinutes($handoffMinutes));
            Cache::put('prior_chat_24h_' . $canonicalSender, true, now()->addHours(24));

            $reply = "Baik Kak, Anda telah terhubung dengan Admin. Silakan sampaikan pesan Anda, tim kami akan segera membalas.";
            $this->sendReply($canonicalSender, $reply);

            try {
                $this->adminNotifier->notifyHumanMode($customerName, $canonicalSender, $combinedMessage);
            } catch (\Throwable $e) {
                Log::warning("[AiOrderProcessor] Gagal kirim notifikasi human mode: " . $e->getMessage());
            }

            Log::info("[AiOrderProcessor] Human mode diaktifkan untuk " . PhoneNumberHelper::mask($canonicalSender) . " selama {$handoffMinutes} menit.");
            return ['status' => 'human_mode_activated', 'reply' => $reply];
        }

        // (c) ack_smalltalk
        if ($intent === 'ack_smalltalk') {
            $hasPriorChat = Cache::has('prior_chat_24h_' . $canonicalSender) ||
                TemporaryOrder::where('sender_phone', $canonicalSender)->where('created_at', '>=', now()->subHours(24))->exists();

            Cache::put('prior_chat_24h_' . $canonicalSender, true, now()->addHours(24));

            if ($hasPriorChat) {
                Log::info("[AiOrderProcessor] ack_smalltalk dari pelanggan aktif dalam 24 jam terakhir.");
                if (config('ai_order.ack_reply', true)) {
                    $reply = ReplyTemplates::render('ack');
                    $this->sendReply($canonicalSender, $reply);
                    return ['status' => 'ack', 'reply' => $reply];
                }
                return ['status' => 'ack_silent', 'reply' => null];
            }

            $reply = ReplyTemplates::render('greeting');
            if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                $this->sendReply($canonicalSender, $reply);
            }
            return ['status' => 'greeting', 'reply' => $reply];
        }

        // Catat interaksi pelanggan dalam 24 jam terakhir
        Cache::put('prior_chat_24h_' . $canonicalSender, true, now()->addHours(24));

        // (d) repeat_last
        if ($intent === 'repeat_last') {
            if ($latestOrder && in_array($latestOrder->status, ['validated', 'processed'], true)) {
                $extractedUkuran = $this->extractUkuranOnly($combinedMessage);
                $targetUkuran = $extractedUkuran ?: (string) $latestOrder->ukuran_produk;
                $targetQty = (float) $latestOrder->jumlah_ball;

                $draft->mergeData([
                    'jumlah_ball'   => $targetQty,
                    'ukuran_produk' => $targetUkuran,
                ]);
                Log::info("[AiOrderProcessor] repeat_last: menggunakan qty {$targetQty} dan ukuran {$targetUkuran} dari order #{$latestOrder->id}");
            } else {
                $extractedUkuran = $this->extractUkuranOnly($combinedMessage);
                if ($extractedUkuran) {
                    $draft->mergeData(['ukuran_produk' => $extractedUkuran]);
                    $draft->setAwaiting('qty');
                    $reply = ReplyTemplates::render('need_qty_only');
                    if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                        $this->sendReply($canonicalSender, $reply);
                    }
                    return ['status' => 'need_qty', 'reply' => $reply];
                }
            }
        }

        // Notifikasi urgent jika mengandung kata-kata mendesak
        if (self::isUrgent($combinedMessage)) {
            $urgentName = $customerName;
            if ($urgentName === 'Kak') {
                if (!empty($draft->data['nama_toko'])) {
                    $urgentName = $draft->data['nama_toko'];
                } elseif (preg_match('/(?:toko|cafe|kafe|caffe|kedai|warung|resto|depot|kopi)\s+([^,\n\.\;\|]+)/i', $combinedMessage, $tm)) {
                    $urgentName = trim($tm[0]);
                }
            }
            $this->adminNotifier->notifyUrgentOrder($urgentName, $canonicalSender);
        }

        $textLower = strtolower(trim($combinedMessage));

        // ==============================================================
        // 5. PENANGANAN BATAL / CANCEL (Hanya membatalkan order TERBARU)
        // ==============================================================
        if (preg_match('/^(?:batal|cancel|gak jadi|gajadi)\b/i', $textLower)) {
            if ($latestOrder && $latestOrder->status === 'validated') {
                $latestOrder->update([
                    'status'    => 'cancelled',
                    'last_chat' => $combinedMessage,
                ]);
                $draft->clearDraft();
                $reply = ReplyTemplates::render('cancelled');
                $this->sendReply($canonicalSender, $reply);
                return ['status' => 'cancelled', 'order_id' => $latestOrder->id, 'reply' => $reply];
            }

            if ($latestOrder && $latestOrder->status === 'processed') {
                $reply = ReplyTemplates::render('already_processed');
                $this->sendReply($canonicalSender, $reply);
                return ['status' => 'already_processed', 'order_id' => $latestOrder->id, 'reply' => $reply];
            }

            $draft->clearDraft();
            $reply = ReplyTemplates::render('cancelled');
            $this->sendReply($canonicalSender, $reply);
            return ['status' => 'cancelled', 'reply' => $reply];
        }

        // ==============================================================
        // 6. PENANGANAN UBAH / PERUBAHAN PESANAN SECARA EKSPLISIT
        // ==============================================================
        $isUbahKeyword = (bool) preg_match('/^(?:ubah|ganti)\b/i', $textLower);
        if ($isUbahKeyword) {
            if ($latestOrder && $latestOrder->status === 'validated') {
                // Buka kembali draft dari pesanan terakhir
                $draft->data = [
                    'nama_toko'          => $latestOrder->customer ? $latestOrder->customer->name : null,
                    'alamat'             => $latestOrder->customer ? $latestOrder->customer->address : null,
                    'lat'                => null,
                    'lng'                => null,
                    'jumlah_ball'        => $latestOrder->jumlah_ball,
                    'ukuran_produk'      => $latestOrder->ukuran_produk,
                    'waktu_pengiriman'   => $latestOrder->delivery_time ? $latestOrder->delivery_time->format('Y-m-d H:i:s') : null,
                    'is_registration'    => false,
                    'modifying_order_id' => $latestOrder->id,
                ];
                $draft->awaiting = null;
                $draft->save();

                $reply = ReplyTemplates::render('ubah_prompt');
                $this->sendReply($canonicalSender, $reply);
                return ['status' => 'ubah_prompt', 'order_id' => $latestOrder->id, 'reply' => $reply];
            }

            if ($latestOrder && $latestOrder->status === 'processed') {
                $reply = ReplyTemplates::render('already_processed');
                $this->sendReply($canonicalSender, $reply);
                return ['status' => 'already_processed', 'order_id' => $latestOrder->id, 'reply' => $reply];
            }
        }

        // ==============================================================
        // 7. PENANGANAN KATA ACK / TERIMA KASIH (Jendela: AI_ORDER_ACK_MINUTES)
        // ==============================================================
        $ackWords = 'oke|ok|siap|iya|ya|sip|makasih|makasi|terima\s*kasih|terimakasih|thanks|thx';
        $honorifics = 'kak|pak|min|admin|ya|mas|bang|gan';
        $ackPattern = "/^(?:(?:{$ackWords})\s*)+(?:(?:{$honorifics})\s*)*[\.!\s]*$/i";
        if (preg_match($ackPattern, $textLower)) {
            $ackMinutes = (int) config('ai_order.ack_minutes', 10);
            $hasRecentOrder = $latestOrder &&
                in_array($latestOrder->status, ['validated', 'processed'], true) &&
                $latestOrder->updated_at &&
                $latestOrder->updated_at->diffInMinutes(now()) <= $ackMinutes;

            if ($hasRecentOrder) {
                Log::info("[AiOrderProcessor] Ack diterima ('{$textLower}') dalam jendela {$ackMinutes} menit untuk order #{$latestOrder->id}");
                if (config('ai_order.ack_reply', true)) {
                    $reply = ReplyTemplates::render('ack');
                    $this->sendReply($canonicalSender, $reply);
                    return ['status' => 'ack', 'order_id' => $latestOrder->id, 'reply' => $reply];
                }
                return ['status' => 'ack_silent', 'order_id' => $latestOrder->id, 'reply' => null];
            }
        }

        // ==============================================================
        // 8. ATURAN PESAN SUSULAN (Jendela: AI_ORDER_FOLLOWUP_MINUTES = 5 menit)
        // ==============================================================
        $followupMinutes = (int) config('ai_order.followup_minutes', 5);
        $hasFollowupOrder = $latestOrder &&
            in_array($latestOrder->status, ['validated', 'processed'], true) &&
            $latestOrder->updated_at &&
            $latestOrder->updated_at->diffInMinutes(now()) <= $followupMinutes;

        if ($hasFollowupOrder) {
            // Cek apakah ini PESANAN BARU (Terpisah)
            if ($this->isNewOrderIntent($combinedMessage)) {
                // Ekstraksi slot pesanan baru
                $parsedNew = $this->llmService->parseOrder(
                    $combinedMessage,
                    [],
                    null,
                    false,
                    $customerName
                );

                if (!empty($parsedNew['unknown_ukuran'])) {
                    $reply = ReplyTemplates::render('unknown_ukuran');
                    if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                        $this->sendReply($canonicalSender, $reply);
                    }
                    return ['status' => 'unknown_ukuran', 'reply' => $reply];
                }

                $newQty = $parsedNew['jumlah_ball'];
                $newUkuran = $parsedNew['ukuran_produk'] ?? '20kg';
                $newTime = $parsedNew['waktu_pengiriman'];

                if ($newQty !== null) {
                    // Validasi stok
                    $avail = 0;
                    if ($this->llmService->isStockExceeded($newQty, $newUkuran, $avail)) {
                        $reply = ReplyTemplates::render('out_of_stock');
                        $reply = $this->llmService->filterStockNumbers($reply, $avail);
                        $this->sendReply($canonicalSender, $reply);
                        $this->adminNotifier->notifyStockInsufficient($customerName, $canonicalSender, $newQty, $newUkuran);
                        return ['status' => 'out_of_stock', 'reply' => $reply];
                    }

                    // Hitung jumlah order aktif hari ini untuk customer ini
                    $ordersToday = TemporaryOrder::where('sender_phone', $canonicalSender)
                        ->whereDate('created_at', Carbon::today())
                        ->whereIn('status', ['validated', 'processed'])
                        ->count();
                    $orderIndex = $ordersToday + 1;

                    // Buat pesanan baru terpisah!
                    $newOrder = TemporaryOrder::create([
                        'customer_id'     => $customer ? $customer->id : 108,
                        'sender_phone'    => $canonicalSender,
                        'jumlah_ball'     => $newQty,
                        'ukuran_produk'   => $newUkuran,
                        'delivery_time'   => $newTime,
                        'status'          => 'validated',
                        'is_new_customer' => false,
                        'last_chat'       => $combinedMessage,
                    ]);

                    $jamStr = $newTime ? ' jam ' . Carbon::parse($newTime)->timezone('Asia/Jakarta')->format('H:i') : '';
                    $reply = ReplyTemplates::renderOrderInMultiple($orderIndex, $newQty, $newUkuran, $jamStr);
                    $this->sendReply($canonicalSender, $reply);

                    Log::info("[AiOrderProcessor] Pesanan baru ke-{$orderIndex} (#{$newOrder->id}) tercatat terpisah untuk {$canonicalSender}");
                    return [
                        'status'   => 'order_in_multiple',
                        'order_id' => $newOrder->id,
                        'index'    => $orderIndex,
                        'reply'    => $reply,
                    ];
                }
            }

            // PERUBAHAN ORDER (Update order terakhir)
            $followup = $this->extractFollowupIntent($combinedMessage, (float) $latestOrder->jumlah_ball, (string) $latestOrder->ukuran_produk);

            if ($followup !== null) {
                // Jika order sudah diproses kasir, tidak bisa diubah lewat chat
                if ($latestOrder->status === 'processed') {
                    $reply = ReplyTemplates::render('already_processed');
                    $this->sendReply($canonicalSender, $reply);
                    return ['status' => 'already_processed', 'order_id' => $latestOrder->id, 'reply' => $reply];
                }

                // Ukuran di luar 20/15/10kg
                if (!empty($followup['unknown_ukuran'])) {
                    $reply = ReplyTemplates::render('unknown_ukuran');
                    if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                        $this->sendReply($canonicalSender, $reply);
                    }
                    return ['status' => 'unknown_ukuran', 'order_id' => $latestOrder->id, 'reply' => $reply];
                }

                $tVal = (float) $followup['qty'];
                $targetQty = (fmod($tVal, 1.0) === 0.0) ? (int) $tVal : round($tVal, 2);
                $targetUkuran = (string) $followup['ukuran'];
                $oVal = (float) $latestOrder->jumlah_ball;
                $oldQty = (fmod($oVal, 1.0) === 0.0) ? (int) $oVal : round($oVal, 2);
                $oldUkuran = (string) $latestOrder->ukuran_produk;

                // Kasus A: Nilai persis sama dengan order berjalan (penegasan)
                if ($targetQty === $oldQty && $targetUkuran === $oldUkuran) {
                    $reply = ReplyTemplates::render('followup_unchanged', [
                        'qty'    => $targetQty,
                        'ukuran' => $targetUkuran,
                    ]);
                    $this->sendReply($canonicalSender, $reply);
                    Log::info("[AiOrderProcessor] Follow-up nilai sama untuk order #{$latestOrder->id} ({$targetQty} ball {$targetUkuran}). DB tidak diubah.");
                    return ['status' => 'followup_unchanged', 'order_id' => $latestOrder->id, 'reply' => $reply];
                }

                // Kasus B: Nilai berbeda -> cek stok dan update order berjalan
                $stockData = $this->llmService->getAvailableStockWithReservation($targetUkuran);
                $currentReserved = ($latestOrder->ukuran_produk === $targetUkuran) ? $oldQty : 0;
                $effectiveAvailable = $stockData['available'] + $currentReserved;

                if ($targetQty > $effectiveAvailable) {
                    $reply = ReplyTemplates::render('out_of_stock');
                    $reply = $this->llmService->filterStockNumbers($reply, $effectiveAvailable);
                    $this->sendReply($canonicalSender, $reply);
                    $this->adminNotifier->notifyStockInsufficient($customerName, $canonicalSender, $targetQty, $targetUkuran);
                    return ['status' => 'out_of_stock', 'reply' => $reply];
                }

                $latestOrder->update([
                    'jumlah_ball'   => $targetQty,
                    'ukuran_produk' => $targetUkuran,
                    'last_chat'     => $combinedMessage,
                ]);

                // Balasan UBAH wajib memuat nilai lama -> baru
                $reply = ReplyTemplates::renderOrderUpdated($oldQty, $targetQty, $targetUkuran, $oldUkuran);
                $this->sendReply($canonicalSender, $reply);

                Log::info("[AiOrderProcessor] Follow-up memperbarui order #{$latestOrder->id} dari {$oldQty} ball {$oldUkuran} menjadi {$targetQty} ball {$targetUkuran}");
                return ['status' => 'followup_updated', 'order_id' => $latestOrder->id, 'reply' => $reply];
            }
        }

        // ==============================================================
        // 9. PENANGANAN SHARE LOCATION (TEMPLATE ADAPTIF)
        // ==============================================================
        if (!empty($location)) {
            $coords = explode(',', $location);
            if (count($coords) >= 2) {
                $lat = trim($coords[0]);
                $lng = trim($coords[1]);

                $draft->mergeData([
                    'lat' => $lat,
                    'lng' => $lng,
                ]);

                if (empty($draft->data['alamat'])) {
                    $draft->mergeData(['alamat' => 'Lokasi WhatsApp']);
                }

                if ($customer && empty($customer->address)) {
                    $customer->update(['address' => 'Lokasi WhatsApp (' . $lat . ',' . $lng . ')']);
                }
            }

            // Jika hanya share lokasi tanpa teks (atau pesan media/lokasi murni)
            if (empty($combinedMessage) || $combinedMessage === 'non-text message') {
                // Skenario 1: Draft sudah punya qty & ukuran
                if ($draft->hasQty() && $draft->hasUkuran()) {
                    if ($customer || !empty($draft->data['nama_toko'])) {
                        // Nama toko sudah ada -> langsung validasi order di langkah 13-16!
                    } else {
                        // Qty & ukuran ada, tinggal nama toko
                        $draft->setAwaiting('nama_alamat');
                        $reply = ReplyTemplates::render('location_need_name');
                        if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                            $this->sendReply($canonicalSender, $reply);
                        }
                        return ['status' => 'need_name', 'reply' => $reply];
                    }
                } elseif (!$draft->hasQty()) {
                    // Skenario 2: Belum ada kuantitas pesanan
                    $draft->setAwaiting('qty');
                    $reply = ReplyTemplates::render('location_need_qty_ukuran');
                    if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                        $this->sendReply($canonicalSender, $reply);
                    }
                    return ['status' => 'location_only', 'reply' => $reply];
                }
            }
        } elseif (($combinedMessage === 'non-text message' || $combinedMessage === 'non-button message') && empty($location)) {
            $reply = ReplyTemplates::render('unsupported_media');
            if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                $this->sendReply($canonicalSender, $reply);
            }
            return ['status' => 'unsupported_media', 'reply' => $reply];
        }

        // ==============================================================
        // 10. EKSTRAKSI SLOT MENGGUNAKAN LLM / FAST-PATH REGEX
        // ==============================================================
        $parsed = $this->llmService->parseOrder(
            $combinedMessage,
            $draft->data ?? [],
            $draft->awaiting,
            $isNewCustomer,
            $customerName
        );

        // Jika ukuran yang diminta tidak dikenal (misal "yang 5")
        if (!empty($parsed['unknown_ukuran'])) {
            $draft->setAwaiting('ukuran');
            $reply = ReplyTemplates::render('unknown_ukuran');
            if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                $this->sendReply($canonicalSender, $reply);
            }
            return ['status' => 'unknown_ukuran', 'reply' => $reply];
        }

        // Gabungkan slot hasil ekstraksi ke dalam draft (slot terisi tidak ditimpa null)
        $draft->mergeData([
            'nama_toko'        => $parsed['nama_toko'],
            'alamat'           => $parsed['alamat'],
            'jumlah_ball'      => $parsed['jumlah_ball'],
            'ukuran_produk'    => $parsed['ukuran_produk'],
            'waktu_pengiriman' => $parsed['waktu_pengiriman'],
            'is_registration'  => $parsed['is_registration'] ?? false,
        ]);

        // ==============================================================
        // 11. CEK APAKAH PESAN HANYA SAPAAN BIASA
        // ==============================================================
        if (
            !$parsed['is_order'] &&
            !$draft->hasQty() &&
            empty($draft->data['ukuran_produk']) &&
            empty($draft->data['nama_toko'])
        ) {
            $reply = ReplyTemplates::render('greeting');
            if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                $this->sendReply($canonicalSender, $reply);
            }
            return ['status' => 'greeting', 'reply' => $reply];
        }

        // ==============================================================
        // 12. PEMERIKSAAN KELENGKAPAN SLOT (AWAITING CHECK)
        // ==============================================================

        // A. Cek Kuantitas Ball
        if (!$draft->hasQty()) {
            $draft->setAwaiting('qty');
            $reply = $draft->hasUkuran()
                ? ReplyTemplates::render('need_qty_only')
                : ReplyTemplates::render('need_qty');
            if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                $this->sendReply($canonicalSender, $reply);
            }
            return ['status' => 'need_qty', 'reply' => $reply];
        }

        $qVal = (float) $draft->data['jumlah_ball'];
        $qty = (fmod($qVal, 1.0) === 0.0) ? (int) $qVal : round($qVal, 2);

        // B. Cek Ukuran Produk (Default adalah 20kg jika tidak disebut)
        if (!$draft->hasUkuran()) {
            $draft->mergeData(['ukuran_produk' => '20kg']);
        }
        $ukuran = (string) $draft->data['ukuran_produk'];

        // C. Cek Data Pelanggan Baru (Adaptive Need Name / Address)
        if ($isNewCustomer && !$draft->hasNameAndAddress()) {
            $draft->setAwaiting('nama_alamat');
            $hasName = !empty($draft->data['nama_toko']);
            $hasAddress = !empty($draft->data['alamat']) || (!empty($draft->data['lat']) && !empty($draft->data['lng']));

            $reply = ReplyTemplates::renderNeedNameAddress($qty, $ukuran, $hasName, $hasAddress);
            if (!$this->shouldSuppressQuestionReply($canonicalSender)) {
                $this->sendReply($canonicalSender, $reply);
            }
            return ['status' => 'need_name_address', 'reply' => $reply];
        }

        // ==============================================================
        // 13. VALIDASI STOK REAL-TIME & SOFT RESERVATION (24 Jam)
        // ==============================================================
        $availableStock = 0;
        if ($this->llmService->isStockExceeded($qty, $ukuran, $availableStock)) {
            $reply = ReplyTemplates::render('out_of_stock');
            // Pastikan tidak ada kebocoran angka stok
            $reply = $this->llmService->filterStockNumbers($reply, $availableStock);
            $this->sendReply($canonicalSender, $reply);
            $this->adminNotifier->notifyStockInsufficient($customerName, $canonicalSender, $qty, $ukuran);
            return ['status' => 'out_of_stock', 'reply' => $reply];
        }

        // ==============================================================
        // 14. OTOMASI REGISTRASI PELANGGAN BARU (DENGAN RE-CHECK ANTI-DUPLIKASI)
        // ==============================================================
        if ($isNewCustomer) {
            $existing = $this->findCustomerByPhone($canonicalSender);
            if ($existing) {
                $customer = $existing;
                $isNewCustomer = false;
                Log::info("[AiOrderProcessor] Customer sudah ada ({$customer->id}), mencegah registrasi ganda.");
            } else {
                $namaToko = !empty($draft->data['nama_toko']) ? trim((string) $draft->data['nama_toko']) : 'Pelanggan Baru';
                $alamatToko = !empty($draft->data['alamat']) ? trim((string) $draft->data['alamat']) : 'Lokasi Terdaftar';

                $defaultBusinessId = Customer::first()?->business_id ?? 1;

                $customer = Customer::create([
                    'business_id' => $defaultBusinessId,
                    'name'        => $namaToko,
                    'phone'       => $canonicalSender,
                    'address'     => $alamatToko,
                    'join_date'   => now(),
                    'status'      => 'active',
                ]);

                try {
                    $customer->phones()->create([
                        'phone'      => $canonicalSender,
                        'label'      => 'Utama',
                        'is_primary' => true,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("[AiOrderProcessor] Gagal simpan customer_phones: " . $e->getMessage());
                }

                Log::info("[AiOrderProcessor] Customer baru terdaftar: {$customer->id} ({$namaToko}) - {$canonicalSender}");
            }
        }

        // ==============================================================
        // 15. BUAT / PERBARUI ORDER (STATUS: VALIDATED)
        // ==============================================================
        $deliveryTime = !empty($draft->data['waktu_pengiriman']) ? $draft->data['waktu_pengiriman'] : null;

        // Cek draft modifying_order_id untuk update order lama (UBAH)
        $modifyingOrderId = $draft->data['modifying_order_id'] ?? null;
        $orderToUpdate = null;

        if ($modifyingOrderId) {
            $orderToUpdate = TemporaryOrder::find($modifyingOrderId);
        }

        if ($orderToUpdate && $orderToUpdate->status === 'validated') {
            $oldQ = $orderToUpdate->jumlah_ball;
            $oldU = $orderToUpdate->ukuran_produk;
            $orderToUpdate->update([
                'jumlah_ball'   => $qty,
                'ukuran_produk' => $ukuran,
                'delivery_time' => $deliveryTime,
                'last_chat'     => $combinedMessage,
            ]);
            $order = $orderToUpdate;
            Log::info("[AiOrderProcessor] Order #{$order->id} diperbarui (UBAH) dari {$oldQ} ball {$oldU} menjadi {$qty} ball {$ukuran}");

            $reply = ReplyTemplates::renderOrderUpdated($oldQ, $qty, $ukuran, $oldU);
            $draft->clearDraft();
            $this->sendReply($canonicalSender, $reply);

            return [
                'status'   => 'followup_updated',
                'order_id' => $order->id,
                'reply'    => $reply,
            ];
        }

        // Order baru dibuat
        $order = TemporaryOrder::create([
            'customer_id'     => $customer ? $customer->id : 108,
            'sender_phone'    => $canonicalSender,
            'jumlah_ball'     => $qty,
            'ukuran_produk'   => $ukuran,
            'delivery_time'   => $deliveryTime,
            'status'          => 'validated', // LANGSUNG VALIDATED (Masuk antrean kasir)!
            'is_new_customer' => $isNewCustomer,
            'last_chat'       => $combinedMessage,
        ]);
        Log::info("[AiOrderProcessor] Order #{$order->id} dibuat dengan status 'validated' ({$qty} ball {$ukuran})");

        // ==============================================================
        // 16. FORMAT BALASAN KE PELANGGAN DARI TEMPLATE
        // ==============================================================
        $namaTokoFinal = $customer ? $customer->name : ($draft->data['nama_toko'] ?? 'Kak');
        $alamatFinal   = $draft->data['alamat'] ?? ($customer ? $customer->address : null);
        $jamFormatted  = $deliveryTime ? Carbon::parse($deliveryTime)->timezone('Asia/Jakarta')->format('H:i') : null;

        $kasbon = ($customer && method_exists($customer, 'getTotalKasbon')) ? (float) $customer->getTotalKasbon() : 0.0;

        $reply = ReplyTemplates::renderOrderIn(
            $isNewCustomer,
            $qty,
            $ukuran,
            $namaTokoFinal,
            $alamatFinal,
            $jamFormatted,
            $kasbon
        );

        // Notifikasi grup admin bila ada pelanggan baru terdaftar & order masuk
        if ($isNewCustomer) {
            $this->adminNotifier->notifyNewCustomerOrder(
                $namaTokoFinal,
                $alamatFinal ?? 'Lokasi Terdaftar',
                $qty,
                $ukuran,
                $canonicalSender
            );
        }

        // Bersihkan draft setelah order sukses tercatat
        $draft->clearDraft();

        $this->sendReply($canonicalSender, $reply);

        return [
            'status'          => 'validated',
            'order_id'        => $order->id,
            'is_new_customer' => $isNewCustomer,
            'reply'           => $reply,
        ];
    }

    /**
     * Cek apakah pesan pelanggan mengandung kata kunci urgensi/mendesak.
     */
    public static function isUrgent(string $message): bool
    {
        $keywords = ['urgent', 'segera', 'buruan', 'cepet', 'cepat', 'darurat'];
        $clean = strtolower($message);

        foreach ($keywords as $kw) {
            if (str_contains($clean, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek apakah balasan bertipe pertanyaan harus ditahan karena ada bubble baru yang masuk.
     */
    protected function shouldSuppressQuestionReply(string $sender): bool
    {
        return AiOrderDebouncer::hasPendingBuffer($sender);
    }

    /**
     * Cek apakah pesan merupakan intensi pesanan baru yang terpisah (bukan revisi order berjalan).
     */
    protected function isNewOrderIntent(string $message): bool
    {
        $clean = strtolower(trim($message));

        // Jika diawali kata eksplisit ubah/ganti/jadi/kurangi/ralat/tambah -> BUKAN pesanan baru
        if (preg_match('/^(?:jadi|jadinya|ubah|ganti|kurangi|ralat|tambah|nambah|\+)\b/i', $clean)) {
            return false;
        }

        // Cek kata kunci pemesanan
        $hasOrderKeyword = (bool) preg_match('/\b(?:pesen|pesan|mau|kirim|order|minta)\b/i', $clean);

        // Cek waktu pengiriman (misal "jam 4 sore", "besok jam 10")
        $hasTime = (bool) preg_match('/\b(?:jam\s*\d{1,2}|besok|pagi|siang|sore|malam)\b/i', $clean);

        // Cek pola kombinasi qty + ukuran (misal "3 ball yang 19", "2 ball 20kg")
        $hasQtyAndUkuran = (bool) (preg_match('/\d+\s*(?:ball|bal|sak|karung)/i', $clean) && preg_match('/(?:20|19|15|10)\s*(?:kg|rb|k)?/i', $clean));

        return $hasOrderKeyword || $hasTime || $hasQtyAndUkuran;
    }

    /**
     * Ekstraksi intensi ukuran dan/atau kuantitas pada pesan susulan.
     */
    protected function extractFollowupIntent(string $message, float|int $currentQty, string $currentUkuran): ?array
    {
        $clean = strtolower(trim($message));

        // Cek unknown ukuran (misal "yang 5", "5kg")
        if (preg_match('/(?:^|\D)5(?:rb|k|kg)?(?:\D|$)/i', $clean) && (str_contains($clean, 'yang 5') || str_contains($clean, 'yg 5') || str_contains($clean, '5kg') || str_contains($clean, '5 kg'))) {
            return [
                'is_followup'    => true,
                'qty'            => $currentQty,
                'ukuran'         => $currentUkuran,
                'unknown_ukuran' => true,
            ];
        }

        $hasQty = false;
        $hasUkuran = false;
        $newQty = $currentQty;
        $newUkuran = $currentUkuran;

        // Cek setengah ball di follow-up ("jadi setengah", "ganti 1/2", "setengah ball")
        if (preg_match('/(?:^|\b)(?:jadi|jadinya|ganti)?\s*(?:setengah|separuh|1\/2|0[,.]5)\s*(?:ball|bal|sak|karung|biji|bungkus)?(?:\b|$)/i', $clean)) {
            $newQty = 0.5;
            $hasQty = true;
        }
        // 1. Cek Penambahan Relatif ("tambah 1", "nambah 2", "+1")
        elseif (preg_match('/(?:tambah|nambah|plus|\+)\s*(\d+(?:[.,]\d+)?)/i', $clean, $m)) {
            $add = (float) str_replace(',', '.', $m[1]);
            $sum = $currentQty + $add;
            $newQty = (fmod($sum, 1.0) === 0.0) ? (int) $sum : round($sum, 2);
            $hasQty = true;
        }
        // 2. Cek Qty Absolut ("jadi 2 ball", "jadinya 2", "ganti 2", "2 ball", "1 ball", atau hanya angka tunggal)
        elseif (preg_match('/(?:jadi|jadinya|ganti)?\s*(\d+(?:[.,]\d+)?)\s*(?:ball|bal|sak|karung|biji|bungkus)\b/i', $clean, $m)) {
            $val = (float) str_replace(',', '.', $m[1]);
            $newQty = (fmod($val, 1.0) === 0.0) ? (int) $val : round($val, 2);
            $hasQty = true;
        } elseif (preg_match('/^(?:jadi|jadinya)?\s*(\d+(?:[.,]\d+)?)\s*$/i', $clean, $m)) {
            $candidate = (float) str_replace(',', '.', $m[1]);
            // Jika angka tunggal bukan 10, 15, 19, 20 (karena 10/15/20 biasanya ukuran es)
            if (!in_array($candidate, [10, 15, 19, 20])) {
                $newQty = (fmod($candidate, 1.0) === 0.0) ? (int) $candidate : round($candidate, 2);
                $hasQty = true;
            }
        }

        // 3. Cek Ukuran ("yang 20kg", "yg 15", "20kg", "15kg", "10kg", "yang 20", "20", "15", "10")
        if (
            str_contains($clean, 'yang 20') || str_contains($clean, 'yg 20') || str_contains($clean, '20kg') || str_contains($clean, '20 kg') ||
            str_contains($clean, 'yang 19') || str_contains($clean, 'yg 19') || str_contains($clean, '19kg') || str_contains($clean, '19 kg') ||
            preg_match('/(?<!jam\s)(?<!jam\s0)(?<!jam\s1)\b(?:20|19)(?:rb|k)?\b(?!\s*(?:ball|karung|sak))/i', $clean)
        ) {
            $newUkuran = '20kg';
            $hasUkuran = true;
        } elseif (
            str_contains($clean, 'yang 15') || str_contains($clean, 'yg 15') || str_contains($clean, '15kg') || str_contains($clean, '15 kg') ||
            preg_match('/(?<!jam\s)(?<!jam\s0)(?<!jam\s1)\b15(?:rb|k)?\b(?!\s*(?:ball|karung|sak))/i', $clean)
        ) {
            $newUkuran = '15kg';
            $hasUkuran = true;
        } elseif (
            str_contains($clean, 'yang 10') || str_contains($clean, 'yg 10') || str_contains($clean, '10kg') || str_contains($clean, '10 kg') ||
            preg_match('/(?<!jam\s)(?<!jam\s0)(?<!jam\s1)\b10(?:rb|k)?\b(?!\s*(?:ball|karung|sak))/i', $clean)
        ) {
            $newUkuran = '10kg';
            $hasUkuran = true;
        }

        if (!$hasQty && !$hasUkuran) {
            return null;
        }

        return [
            'is_followup'    => true,
            'qty'            => $newQty,
            'ukuran'         => $newUkuran,
            'unknown_ukuran' => false,
        ];
    }

    /**
     * Kirim balasan WhatsApp ke pelanggan atau catat di log bila mode DRY RUN aktif.
     */
    public function sendReply(string $target, string $message): array
    {
        $canonical = PhoneNumberHelper::normalize($target);
        $adminNoticeKey = 'admin_notice_sent_' . $canonical;
        $phrase = '(Ketik *admin* untuk bicara dengan staf)';

        if (str_contains($message, $phrase)) {
            if (Cache::has($adminNoticeKey)) {
                $message = trim(str_replace(["\n" . $phrase, $phrase], '', $message));
            } else {
                Cache::put($adminNoticeKey, true, now()->addHours(24));
            }
        }

        $isDryRun = (bool) config('ai_order.dry_run', false);

        if ($isDryRun) {
            Log::info("[AiOrderProcessor - DRY RUN] Kirim WA ke " . PhoneNumberHelper::mask($canonical) . ":\n{$message}");
            return ['status' => true, 'dry_run' => true, 'message' => $message];
        }

        return $this->fonnteService->sendMessage($target, $message);
    }

    /**
     * Cari customer berdasarkan variasi nomor telepon dengan pembandingan customers.phone dan customer_phones.
     */
    protected function findCustomerByPhone(string $phone): ?Customer
    {
        $variations = PhoneNumberHelper::variations($phone);
        if (empty($variations)) {
            return null;
        }

        // 1. Cek di tabel customers secara langsung
        $customer = Customer::whereIn('phone', $variations)->first();

        // 2. Cek di relasi customer_phones
        if (!$customer) {
            $customerPhone = CustomerPhone::whereIn('phone', $variations)->first();
            if ($customerPhone) {
                $customer = $customerPhone->customer;
            }
        }

        // 3. Fallback pencarian 9 digit terakhir bila ada perbedaan format awalan
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
     * Klasifikasi intent pesan pelanggan (Fase 12):
     * order | repeat_last | delivery_status_or_complaint | ack_smalltalk | other_human_needed
     */
    public function detectIntent(string $message, bool $isNewCustomer = false, bool $hasDraft = false): string
    {
        $text = strtolower(trim($message));

        // 1. Cek keluhan / status pengiriman / driver
        // Contoh: "Es batu nya belum sampe", "Dah set 10 ko blm dianter?", "Segera ya kak", "Maap ini driver mobil apa motor ya"
        $isDeliveryComplaint = preg_match('/\b(?:belum|blm|blum)\s*(?:sampe|sampai|dianter|diantar|datang|nyampe)\b/i', $text)
            || preg_match('/\bmana\s+(?:es(?:nya)?|pesanan|orderan)\b/i', $text)
            || preg_match('/\b(?:ko|kok)\s+(?:lama|blm|belum)\b/i', $text)
            || preg_match('/\b(?:lama\s*(?:amat|banget|bgt))\b/i', $text)
            || preg_match('/\b(?:driver|supir|sopir|kurir)\b/i', $text)
            || preg_match('/\b(?:komplain|complain)\b/i', $text)
            || preg_match('/\b(?:status\s*(?:kirim|pengiriman|pengantaran|pesanan|order))\b/i', $text)
            || (preg_match('/^(?:tolong\s+)?(?:segera|buruan|cepet|cepat)\s*(?:ya\s*)?(?:kak|min|mas|pak)?[\.!\s]*$/i', $text));

        if ($isDeliveryComplaint) {
            return 'delivery_status_or_complaint';
        }

        // 2. Cek permintaan bantuan admin / manusia eksplisit
        if (AiOrderDebouncer::isHumanHandoff($message)) {
            return 'other_human_needed';
        }

        // 3. Cek sapaan / santai / ucapan terima kasih (ack_smalltalk)
        // Contoh: "Waalaikumsalam,malam", "terima kasih", "makasih", "oke", "siap", "malam", "pagi"
        // JANGAN cocokkan jika ada indikasi pesan order (misal "pagi kak pesan es 20")
        $hasOrderIntentWords = preg_match('/\b(?:pesan|pesen|kirim|order|mau|minta|butuh|beli|antar|bawa|drop|toko|cafe|warung)\b/i', $text)
            || preg_match('/(?:20|19|15|10)\s*(?:kg|rb|k|ribu)?/i', $text)
            || preg_match('/\d+\s*(?:ball|karung|sak|bal|biji)/i', $text);

        if (!$hasOrderIntentWords) {
            $smalltalkWords = 'wa\'?alaikum(?:us)?salam|assalamu\'?alaikum|halo|hai|pagi|siang|sore|malam|selamat\s+(?:pagi|siang|sore|malam)|oke|ok|siap|sip|makasih|makasi|terima\s*kasih|terimakasih|thanks|thx|iya|ya';
            $honorifics = 'kak|pak|min|admin|ya|mas|bang|gan|bos|om|bro|pagi|siang|sore|malam';
            $smalltalkPattern = "/^(?:(?:{$smalltalkWords})[\s,.\-!]*)+(?:(?:{$honorifics})[\s,.\-!]*)*$/i";
            if (preg_match($smalltalkPattern, $text)) {
                return 'ack_smalltalk';
            }
        }

        // 4. Cek pesanan berulang (repeat_last)
        // Contoh: "es 10rb seperti biasanya", "kaya biasa", "seperti biasa ya"
        $isRepeatLast = preg_match('/\b(?:kaya|kayak|seperti|sama)\s*(?:biasa(?:nya)?|kemarin|kmrn)\b/i', $text)
            || preg_match('/\b(?:biasa\s*(?:pesan|order|kirim))\b/i', $text)
            || preg_match('/\b(?:pesan|order|kirim)\s+(?:kaya|kayak|seperti)?\s*biasa(?:nya)?\b/i', $text);

        if ($isRepeatLast) {
            return 'repeat_last';
        }

        // 5. Cek Order Intent
        if ($this->llmService->isPotentialOrder($message, $isNewCustomer) || $hasDraft || $hasOrderIntentWords || preg_match('/^(?:batal|cancel|ubah|ganti)\b/i', $text)) {
            return 'order';
        }

        // 6. Gemini fallback jika tidak terklasifikasi via regex
        Log::info("[AiOrderProcessor] Intent tidak jelas via regex, memanggil Gemini API untuk klasifikasi.");
        return $this->llmService->classifyIntentWithGemini($message);
    }

    /**
     * Ekstraksi ukuran saja dari teks (20kg, 15kg, 10kg).
     */
    public function extractUkuranOnly(string $text): ?string
    {
        $clean = strtolower($text);
        if (
            str_contains($clean, 'yang 20') || str_contains($clean, 'yg 20') ||
            str_contains($clean, 'yang 19') || str_contains($clean, 'yg 19') ||
            str_contains($clean, '20kg') || str_contains($clean, '20 kg') ||
            preg_match('/(?<!jam\s)(?<!jam\s0)(?<!jam\s1)\b(?:20|19)(?:rb|k|ribu)?\b(?!\s*(?:ball|karung|sak))/i', $clean)
        ) {
            return '20kg';
        } elseif (
            str_contains($clean, 'yang 15') || str_contains($clean, 'yg 15') ||
            str_contains($clean, '15kg') || str_contains($clean, '15 kg') ||
            preg_match('/(?<!jam\s)(?<!jam\s0)(?<!jam\s1)\b15(?:rb|k|ribu)?\b(?!\s*(?:ball|karung|sak))/i', $clean)
        ) {
            return '15kg';
        } elseif (
            str_contains($clean, 'yang 10') || str_contains($clean, 'yg 10') ||
            str_contains($clean, '10kg') || str_contains($clean, '10 kg') ||
            preg_match('/(?<!jam\s)(?<!jam\s0)(?<!jam\s1)\b10(?:rb|k|ribu)?\b(?!\s*(?:ball|karung|sak))/i', $clean)
        ) {
            return '10kg';
        }
        return null;
    }
}
