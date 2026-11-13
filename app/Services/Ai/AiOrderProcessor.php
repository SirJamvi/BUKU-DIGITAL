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
        // TODO-FASE-5: Map fonnte token to branch_id and run under specific branch context
        return app(\App\Support\BranchContext::class)->runUnscoped(function () use ($sender, $combinedMessage, $location, $payload) {
            return $this->processInternal($sender, $combinedMessage, $location, $payload);
        });
    }

    protected function processInternal(string $sender, string $combinedMessage, ?string $location = null, array $payload = []): array
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
        // 2. CEK STATUS HUMAN HANDOFF / ADMIN (Teruskan Pesan ke Grup)
        // ==============================================================
        $customer = $this->findCustomerByPhone($canonicalSender);
        $isNewCustomer = ($customer === null);
        $customerName = $customer ? $customer->name : 'Kak';

        if (Cache::has('human_mode_' . $canonicalSender)) {
            Log::info("[AiOrderProcessor] Pengirim {$canonicalSender} sedang dalam human mode aktif. Meneruskan chat ke grup admin.");
            $this->adminNotifier->notifyForwardCustomerChat($customerName, $canonicalSender, $combinedMessage);
            return ['status' => 'ignored_human_mode', 'reply' => null];
        }

        // ==============================================================
        // 3. KELOLA DRAFT STATE & ORDER TERAKHIR
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

        $textLower = strtolower(trim($combinedMessage));

        // ==============================================================
        // 4. KONFIRMASI PENDING (Konfirmasi YA untuk ubah di luar jendela / angka polos > 10)
        // ==============================================================
        $isConfirmYes = (bool) preg_match('/^(?:ya|iya|betul|benar|oke|ok|y)\b/i', $textLower);

        // A. Konfirmasi perubahan order di luar jendela 5 menit
        $pendingChange = Cache::get('pending_change_' . $canonicalSender);
        if ($pendingChange) {
            if ($isConfirmYes) {
                Cache::forget('pending_change_' . $canonicalSender);
                $orderToUpdate = TemporaryOrder::find($pendingChange['order_id']);
                if ($orderToUpdate && $orderToUpdate->status === 'validated') {
                    $oldQty = $orderToUpdate->jumlah_ball;
                    $oldUkuran = $orderToUpdate->ukuran_produk;
                    $newQty = $pendingChange['new_qty'];
                    $newUkuran = $pendingChange['new_ukuran'] ?? $oldUkuran;

                    $stockData = $this->llmService->getAvailableStockWithReservation($newUkuran);
                    $currentReserved = ($orderToUpdate->ukuran_produk === $newUkuran) ? $oldQty : 0;
                    $effectiveAvailable = $stockData['available'] + $currentReserved;

                    if ($newQty > $effectiveAvailable) {
                        $reply = ReplyTemplates::render('out_of_stock');
                        $reply = $this->llmService->filterStockNumbers($reply, $effectiveAvailable);
                        $this->sendReply($canonicalSender, $reply);
                        $this->adminNotifier->notifyStockInsufficient($customerName, $canonicalSender, $newQty, $newUkuran);
                        return ['status' => 'out_of_stock', 'reply' => $reply];
                    }

                    $orderToUpdate->update([
                        'jumlah_ball'   => $newQty,
                        'ukuran_produk' => $newUkuran,
                        'last_chat'     => $combinedMessage,
                    ]);

                    $reply = ReplyTemplates::renderOrderUpdated($orderToUpdate->id, $oldQty, $newQty, $newUkuran, $oldUkuran);
                    $this->sendReply($canonicalSender, $reply);
                    Log::info("[AiOrderProcessor] Order #{$orderToUpdate->id} diubah via konfirmasi YA dari {$oldQty} ball {$oldUkuran} menjadi {$newQty} ball {$newUkuran}");
                    return ['status' => 'followup_updated', 'order_id' => $orderToUpdate->id, 'reply' => $reply];
                }
            } else {
                // Pelanggan mengetik pesanan baru / membatalkan konfirmasi
                Cache::forget('pending_change_' . $canonicalSender);
            }
        }

        // B. Konfirmasi angka polos > 10 saat awaiting='qty'
        $pendingQtyConfirm = Cache::get('pending_qty_confirm_' . $canonicalSender);
        if ($pendingQtyConfirm) {
            if ($isConfirmYes) {
                Cache::forget('pending_qty_confirm_' . $canonicalSender);
                $draft->mergeData(['jumlah_ball' => $pendingQtyConfirm]);
                $draft->setAwaiting(null);
                Log::info("[AiOrderProcessor] Kuantitas {$pendingQtyConfirm} ball dikonfirmasi via YA untuk " . PhoneNumberHelper::mask($canonicalSender));
            } else {
                Cache::forget('pending_qty_confirm_' . $canonicalSender);
            }
        }

        // ==============================================================
        // 5. ATURAN SEGERA/SEKARANG DALAM JENDELA FOLLOW-UP (Catatan Order)
        // ==============================================================
        $followupMinutes = (int) config('ai_order.followup_minutes', 5);
        $hasFollowupOrder = $latestOrder &&
            in_array($latestOrder->status, ['validated', 'processed'], true) &&
            $latestOrder->updated_at &&
            $latestOrder->updated_at->diffInMinutes(now()) <= $followupMinutes;

        if ($hasFollowupOrder && preg_match('/^(?:tolong\s+)?(?:segera|sekarang|cepet|cepat|buruan)\s*(?:ya\s*)?(?:kak|min|mas|pak)?[\.!\s]*$/i', $textLower)) {
            $latestOrder->update(['last_chat' => $combinedMessage]);
            $this->adminNotifier->notifyUrgentOrder($customerName, $canonicalSender);
            $reply = "Siap Kak, pesanan #{$latestOrder->id} diusahakan dikirim secepatnya 👍";
            $this->sendReply($canonicalSender, $reply);
            Log::info("[AiOrderProcessor] Catatan kirim segera dicatat pada order #{$latestOrder->id} untuk " . PhoneNumberHelper::mask($canonicalSender));
            return ['status' => 'order_marked_urgent', 'order_id' => $latestOrder->id, 'reply' => $reply];
        }

        // ==============================================================
        // 6. DETEKSI & ROUTING INTENT (Fase 13)
        // ==============================================================
        $hasDraftSlots = $draft->hasQty() || $draft->hasUkuran() || !empty($draft->data['nama_toko']);
        $intent = $this->detectIntent($combinedMessage, $isNewCustomer, $hasDraftSlots);
        Log::info("[AiOrderProcessor] Detected intent: {$intent}");

        // (a) Komplain / status pengiriman
        if ($intent === 'delivery_status_or_complaint') {
            $handoffMinutes = (int) config('ai_order.handoff_minutes', 15);
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

        // (a2) Pertanyaan netral tentang driver
        if ($intent === 'driver_inquiry') {
            $handoffMinutes = (int) config('ai_order.handoff_minutes', 15);
            Cache::put('human_mode_' . $canonicalSender, true, now()->addMinutes($handoffMinutes));
            Cache::put('prior_chat_24h_' . $canonicalSender, true, now()->addHours(24));

            $reply = ReplyTemplates::render('delivery_complaint');
            $this->sendReply($canonicalSender, $reply);

            try {
                $this->adminNotifier->notifyDriverInquiry($customerName, $canonicalSender, $combinedMessage);
            } catch (\Throwable $e) {
                Log::warning("[AiOrderProcessor] Gagal kirim notifikasi driver: " . $e->getMessage());
            }

            Log::info("[AiOrderProcessor] driver_inquiry ditangani: human mode {$handoffMinutes}m aktif untuk " . PhoneNumberHelper::mask($canonicalSender));
            return ['status' => 'driver_inquiry', 'reply' => $reply];
        }

        // (b) Permintaan bantuan admin
        if ($intent === 'other_human_needed') {
            $handoffMinutes = (int) config('ai_order.handoff_minutes', 15);
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

        // (c) ack_smalltalk (Pencegahan Menyela Percakapan Manusia)
        if ($intent === 'ack_smalltalk') {
            $isOpeningGreeting = (bool) preg_match('/^(?:halo|hai|kak|p|permisi|assalamu\'?alaikum|selamat\s+(?:pagi|siang|sore|malam))[\s,.\-!]*$/i', $textLower);
            $hasBotSentToSender = Cache::has('bot_sent_to_' . $canonicalSender) ||
                TemporaryOrder::where('sender_phone', $canonicalSender)->where('created_at', '>=', now()->subHours(24))->exists();

            Cache::put('prior_chat_24h_' . $canonicalSender, true, now()->addHours(24));

            // Jika salam balasan / ucapan terima kasih dari pengirim yang BELUM pernah menerima pesan bot dalam 24 jam -> DIAM
            if (!$isOpeningGreeting && !$hasBotSentToSender) {
                Log::info("[AiOrderProcessor] Salam balasan diterima dari pengirim yang belum menerima pesan bot dalam 24 jam. Bot hening.");
                return ['status' => 'ack_silent', 'reply' => null];
            }

            // Jika salam balasan dari pengirim yang sudah berinteraksi dengan bot -> balas ACK
            if (!$isOpeningGreeting && $hasBotSentToSender) {
                if (config('ai_order.ack_reply', true)) {
                    $reply = ReplyTemplates::render('ack');
                    $this->sendReply($canonicalSender, $reply);
                    return ['status' => 'ack', 'reply' => $reply];
                }
                return ['status' => 'ack_silent', 'reply' => null];
            }

            // Hanya sapaan pembuka yang dijawab dengan perkenalan
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
                    return $this->sendAdaptiveQuestion($draft, $canonicalSender, $customerName, $combinedMessage, 'need_qty', ReplyTemplates::render('need_qty_only'));
                }
            }
        }

        // ==============================================================
        // 7. PENANGANAN BATAL / UBAH / "GAJADI" (Fase 13 - 1.2)
        // ==============================================================
        $isCancelKeyword = (bool) preg_match('/\b(?:batal|cancel|gak jadi|gajadi)\b/i', $textLower);
        if ($isCancelKeyword) {
            $followupCheck = $latestOrder ? $this->extractFollowupIntent($combinedMessage, (float) $latestOrder->jumlah_ball, (string) $latestOrder->ukuran_produk) : null;

            // Jika kata pembatal memuat jumlah/ukuran baru -> PERUBAHAN, bukan pembatalan!
            if ($followupCheck !== null && ($followupCheck['qty'] != $latestOrder->jumlah_ball || $followupCheck['ukuran'] != $latestOrder->ukuran_produk)) {
                Log::info("[AiOrderProcessor] Kata pembatal terdeteksi bersama nilai baru ({$followupCheck['qty']} ball {$followupCheck['ukuran']}), diproses sebagai perubahan order.");
                // Dialihkan ke alur update order
            } else {
                // Pembatalan murni
                if ($latestOrder && $latestOrder->status === 'validated') {
                    $oldQty = $latestOrder->jumlah_ball;
                    $oldUkuran = $latestOrder->ukuran_produk;
                    $latestOrder->update([
                        'status'    => 'cancelled',
                        'last_chat' => $combinedMessage,
                    ]);
                    $draft->clearDraft();

                    $reply = ReplyTemplates::render('cancelled', [
                        'id'     => $latestOrder->id,
                        'qty'    => $oldQty,
                        'ukuran' => $oldUkuran,
                    ]);
                    $this->sendReply($canonicalSender, $reply);
                    Log::info("[AiOrderProcessor] Pesanan #{$latestOrder->id} ({$oldQty} ball {$oldUkuran}) dibatalkan.");
                    return ['status' => 'cancelled', 'order_id' => $latestOrder->id, 'reply' => $reply];
                }

                if ($latestOrder && $latestOrder->status === 'processed') {
                    $reply = ReplyTemplates::render('already_processed');
                    $this->sendReply($canonicalSender, $reply);
                    return ['status' => 'already_processed', 'order_id' => $latestOrder->id, 'reply' => $reply];
                }

                $draft->clearDraft();
                $reply = ReplyTemplates::render('cancelled_no_order');
                $this->sendReply($canonicalSender, $reply);
                return ['status' => 'cancelled', 'reply' => $reply];
            }
        }

        // ==============================================================
        // 8. ATURAN UBAH DI LUAR JENDELA 5 MENIT (Konfirmasi YA)
        // ==============================================================
        $isChangeVerb = (bool) preg_match('/\b(?:jadi|ubah|ganti)\b/i', $textLower);
        if (!$hasFollowupOrder && $latestOrder && $latestOrder->status === 'validated' && $isChangeVerb) {
            $followupOutside = $this->extractFollowupIntent($combinedMessage, (float) $latestOrder->jumlah_ball, (string) $latestOrder->ukuran_produk);
            if ($followupOutside !== null && ($followupOutside['qty'] != $latestOrder->jumlah_ball || $followupOutside['ukuran'] != $latestOrder->ukuran_produk)) {
                $targetQty = $followupOutside['qty'];
                $targetUkuran = $followupOutside['ukuran'];
                $oldQty = $latestOrder->jumlah_ball;
                $oldUkuran = $latestOrder->ukuran_produk;

                Cache::put('pending_change_' . $canonicalSender, [
                    'order_id'   => $latestOrder->id,
                    'new_qty'    => $targetQty,
                    'new_ukuran' => $targetUkuran,
                ], now()->addMinutes(10));

                $qtyStr = (fmod((float) $targetQty, 1.0) === 0.0) ? (string) (int) $targetQty : (string) $targetQty;
                $oldQtyStr = (fmod((float) $oldQty, 1.0) === 0.0) ? (string) (int) $oldQty : (string) $oldQty;

                $reply = "Mau ubah pesanan #{$latestOrder->id} ({$oldQtyStr} ball {$oldUkuran}) jadi {$qtyStr} ball? Balas *YA* atau ketik pesanan baru.";
                $this->sendReply($canonicalSender, $reply);
                Log::info("[AiOrderProcessor] Meminta konfirmasi ubah order #{$latestOrder->id} di luar jendela follow-up ({$oldQtyStr} -> {$qtyStr})");
                return ['status' => 'confirm_change_outside_window', 'order_id' => $latestOrder->id, 'reply' => $reply];
            }
        }

        // ==============================================================
        // 9. ATURAN PESAN SUSULAN (Jendela: AI_ORDER_FOLLOWUP_MINUTES = 5 menit)
        // ==============================================================
        if ($hasFollowupOrder) {
            // Cek apakah ini PESANAN BARU (Terpisah)
            if ($this->isNewOrderIntent($combinedMessage)) {
                $parsedNew = $this->llmService->parseOrder(
                    $combinedMessage,
                    [],
                    null,
                    false,
                    $customerName
                );

                if (!empty($parsedNew['unknown_ukuran'])) {
                    $reply = ReplyTemplates::render('unknown_ukuran');
                    return $this->sendAdaptiveQuestion($draft, $canonicalSender, $customerName, $combinedMessage, 'unknown_ukuran', $reply);
                }

                $newQty = $parsedNew['jumlah_ball'];
                $newUkuran = $parsedNew['ukuran_produk'] ?? '20kg';
                $newTime = $parsedNew['waktu_pengiriman'];

                if ($newQty !== null) {
                    $avail = 0;
                    if ($this->llmService->isStockExceeded($newQty, $newUkuran, $avail)) {
                        $reply = ReplyTemplates::render('out_of_stock');
                        $reply = $this->llmService->filterStockNumbers($reply, $avail);
                        $this->sendReply($canonicalSender, $reply);
                        $this->adminNotifier->notifyStockInsufficient($customerName, $canonicalSender, $newQty, $newUkuran);
                        return ['status' => 'out_of_stock', 'reply' => $reply];
                    }

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

                    $jamStr = $newTime ? Carbon::parse($newTime)->timezone('Asia/Jakarta')->format('H:i') : null;
                    $reply = ReplyTemplates::renderOrderInMultiple($newOrder->id, $latestOrder->id, $newQty, $newUkuran, $jamStr);
                    $this->sendReply($canonicalSender, $reply);

                    Log::info("[AiOrderProcessor] Pesanan baru (#{$newOrder->id}) tercatat terpisah untuk {$canonicalSender}");
                    return [
                        'status'   => 'order_in_multiple',
                        'order_id' => $newOrder->id,
                        'reply'    => $reply,
                    ];
                }
            }

            // PERUBAHAN ORDER (Update order terakhir dalam jendela 5 menit)
            $followup = $this->extractFollowupIntent($combinedMessage, (float) $latestOrder->jumlah_ball, (string) $latestOrder->ukuran_produk);

            if ($followup !== null) {
                if ($latestOrder->status === 'processed') {
                    $reply = ReplyTemplates::render('already_processed');
                    $this->sendReply($canonicalSender, $reply);
                    return ['status' => 'already_processed', 'order_id' => $latestOrder->id, 'reply' => $reply];
                }

                if (!empty($followup['unknown_ukuran'])) {
                    $reply = ReplyTemplates::render('unknown_ukuran');
                    return $this->sendAdaptiveQuestion($draft, $canonicalSender, $customerName, $combinedMessage, 'unknown_ukuran', $reply);
                }

                $tVal = (float) $followup['qty'];
                $targetQty = (fmod($tVal, 1.0) === 0.0) ? (int) $tVal : round($tVal, 2);
                $targetUkuran = (string) $followup['ukuran'];
                $oVal = (float) $latestOrder->jumlah_ball;
                $oldQty = (fmod($oVal, 1.0) === 0.0) ? (int) $oVal : round($oVal, 2);
                $oldUkuran = (string) $latestOrder->ukuran_produk;

                if ($targetQty === $oldQty && $targetUkuran === $oldUkuran) {
                    $reply = ReplyTemplates::render('followup_unchanged', [
                        'qty'    => $targetQty,
                        'ukuran' => $targetUkuran,
                    ]);
                    $this->sendReply($canonicalSender, $reply);
                    Log::info("[AiOrderProcessor] Follow-up nilai sama untuk order #{$latestOrder->id} ({$targetQty} ball {$targetUkuran}). DB tidak diubah.");
                    return ['status' => 'followup_unchanged', 'order_id' => $latestOrder->id, 'reply' => $reply];
                }

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

                $reply = ReplyTemplates::renderOrderUpdated($latestOrder->id, $oldQty, $targetQty, $targetUkuran, $oldUkuran);
                $this->sendReply($canonicalSender, $reply);

                Log::info("[AiOrderProcessor] Follow-up memperbarui order #{$latestOrder->id} dari {$oldQty} ball {$oldUkuran} menjadi {$targetQty} ball {$targetUkuran}");
                return ['status' => 'followup_updated', 'order_id' => $latestOrder->id, 'reply' => $reply];
            }
        }

        // ==============================================================
        // 10. PENANGANAN SHARE LOCATION (TEMPLATE ADAPTIF)
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

            if (empty($combinedMessage) || $combinedMessage === 'non-text message') {
                if ($draft->hasQty() && $draft->hasUkuran()) {
                    if (!$customer && empty($draft->data['nama_toko'])) {
                        $draft->setAwaiting('nama_alamat');
                        return $this->sendAdaptiveQuestion($draft, $canonicalSender, $customerName, $combinedMessage, 'need_name', ReplyTemplates::render('location_need_name'));
                    }
                } elseif (!$draft->hasQty()) {
                    $draft->setAwaiting('qty');
                    return $this->sendAdaptiveQuestion($draft, $canonicalSender, $customerName, $combinedMessage, 'location_only', ReplyTemplates::render('location_need_qty_ukuran'));
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
        // 11. CEK ANGKA POLOS > 10 SAAT AWAITING QTY (Fase 13 - 1.7a)
        // ==============================================================
        if ($draft->awaiting === 'qty' && preg_match('/^\s*([1-9]\d+)\s*$/', $textLower, $numMatch)) {
            $num = (int) $numMatch[1];
            if ($num > 10) {
                Cache::put('pending_qty_confirm_' . $canonicalSender, $num, now()->addMinutes(10));
                $reply = "{$num} ball ya Kak? Balas *YA* atau ketik jumlah yang benar.";
                $this->sendReply($canonicalSender, $reply);
                return ['status' => 'confirm_qty_above_10', 'reply' => $reply];
            }
        }

        // ==============================================================
        // 12. EKSTRAKSI SLOT MENGGUNAKAN LLM / FAST-PATH REGEX
        // ==============================================================
        $parsed = $this->llmService->parseOrder(
            $combinedMessage,
            $draft->data ?? [],
            $draft->awaiting,
            $isNewCustomer,
            $customerName
        );

        if (!empty($parsed['unknown_ukuran'])) {
            $draft->setAwaiting('ukuran');
            return $this->sendAdaptiveQuestion($draft, $canonicalSender, $customerName, $combinedMessage, 'unknown_ukuran', ReplyTemplates::render('unknown_ukuran'));
        }

        $draft->mergeData([
            'nama_toko'        => $parsed['nama_toko'],
            'alamat'           => $parsed['alamat'],
            'jumlah_ball'      => $parsed['jumlah_ball'],
            'ukuran_produk'    => $parsed['ukuran_produk'],
            'waktu_pengiriman' => $parsed['waktu_pengiriman'],
            'is_registration'  => $parsed['is_registration'] ?? false,
        ]);

        // Cek apakah pesan hanya sapaan biasa
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
        // 13. PEMERIKSAAN KELENGKAPAN SLOT (AWAITING CHECK)
        // ==============================================================

        // A. Cek Kuantitas Ball
        if (!$draft->hasQty()) {
            $draft->setAwaiting('qty');
            $reply = $draft->hasUkuran()
                ? ReplyTemplates::render('need_qty_only')
                : ReplyTemplates::render('need_qty');
            return $this->sendAdaptiveQuestion($draft, $canonicalSender, $customerName, $combinedMessage, 'need_qty', $reply);
        }

        $qVal = (float) $draft->data['jumlah_ball'];
        $qty = (fmod($qVal, 1.0) === 0.0) ? (int) $qVal : round($qVal, 2);

        // B. Cek Ukuran Produk
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
            return $this->sendAdaptiveQuestion($draft, $canonicalSender, $customerName, $combinedMessage, 'need_name_address', $reply);
        }

        // ==============================================================
        // 14. VALIDASI STOK REAL-TIME & SOFT RESERVATION (24 Jam)
        // ==============================================================
        $availableStock = 0;
        if ($this->llmService->isStockExceeded($qty, $ukuran, $availableStock)) {
            $reply = ReplyTemplates::render('out_of_stock');
            $reply = $this->llmService->filterStockNumbers($reply, $availableStock);
            $this->sendReply($canonicalSender, $reply);
            $this->adminNotifier->notifyStockInsufficient($customerName, $canonicalSender, $qty, $ukuran);
            return ['status' => 'out_of_stock', 'reply' => $reply];
        }

        // ==============================================================
        // 15. OTOMASI REGISTRASI PELANGGAN BARU
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
        // 16. BUAT / PERBARUI ORDER (STATUS: VALIDATED)
        // ==============================================================
        $deliveryTime = !empty($draft->data['waktu_pengiriman']) ? $draft->data['waktu_pengiriman'] : null;

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

            $reply = ReplyTemplates::renderOrderUpdated($order->id, $oldQ, $qty, $ukuran, $oldU);
            $draft->clearDraft();
            $this->sendReply($canonicalSender, $reply);

            return [
                'status'   => 'followup_updated',
                'order_id' => $order->id,
                'reply'    => $reply,
            ];
        }

        $order = TemporaryOrder::create([
            'customer_id'     => $customer ? $customer->id : 108,
            'sender_phone'    => $canonicalSender,
            'jumlah_ball'     => $qty,
            'ukuran_produk'   => $ukuran,
            'delivery_time'   => $deliveryTime,
            'status'          => 'validated',
            'is_new_customer' => $isNewCustomer,
            'last_chat'       => $combinedMessage,
        ]);
        Log::info("[AiOrderProcessor] Order #{$order->id} dibuat dengan status 'validated' ({$qty} ball {$ukuran})");

        // ==============================================================
        // 17. FORMAT BALASAN KE PELANGGAN DARI TEMPLATE
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
            $kasbon,
            $order->id
        );

        if ($isNewCustomer) {
            $this->adminNotifier->notifyNewCustomerOrder(
                $namaTokoFinal,
                $alamatFinal ?? 'Lokasi Terdaftar',
                $qty,
                $ukuran,
                $canonicalSender
            );
        }

        // Bersihkan draft setelah order sukses
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
     * Kirim balasan pertanyaan adaptif dengan pengaman terhadap pertanyaan berulang.
     * Pertanyaan yang sama tidak boleh dikirim 2x berturut-turut tanpa kemajuan slot.
     * Setelah 2 pertanyaan buntu -> sambungkan ke admin.
     */
    protected function sendAdaptiveQuestion(
        AiOrderDraft $draft,
        string $canonicalSender,
        string $customerName,
        string $combinedMessage,
        string $questionKey,
        string $reply
    ): array {
        if ($this->shouldSuppressQuestionReply($canonicalSender)) {
            return ['status' => $questionKey, 'reply' => $reply];
        }

        $lastQuestion = $draft->data['last_question_key'] ?? null;
        $repeatCount = (int) ($draft->data['question_repeat_count'] ?? 0);

        if ($lastQuestion === $questionKey) {
            $repeatCount++;
        } else {
            $repeatCount = 0;
        }

        if ($repeatCount >= 2) {
            $handoffMins = (int) config('ai_order.handoff_minutes', 15);
            Cache::put('human_mode_' . $canonicalSender, true, now()->addMinutes($handoffMins));
            $draft->clearDraft();

            $reply = "Biar cepat, saya sambungkan ke admin ya Kak 🙏";
            $this->sendReply($canonicalSender, $reply);
            $this->adminNotifier->notifyBotStuck($customerName, $canonicalSender, $combinedMessage);
            Log::info("[AiOrderProcessor] Bot buntu: pertanyaan '{$questionKey}' berulang 2x untuk " . PhoneNumberHelper::mask($canonicalSender) . ". Mengalihkan ke staf.");
            return ['status' => 'bot_stuck_handoff', 'reply' => $reply];
        }

        $draft->mergeData([
            'last_question_key'     => $questionKey,
            'question_repeat_count' => $repeatCount,
        ]);

        // Jika ini pengulangan pertama, persempit pertanyaan bila memungkinkan
        if ($repeatCount === 1 && $questionKey === 'need_qty' && $draft->hasUkuran()) {
            $ukuran = $draft->data['ukuran_produk'];
            $reply = "Untuk es {$ukuran}, mau pesan berapa ball Kak?";
        }

        $this->sendReply($canonicalSender, $reply);
        return ['status' => $questionKey, 'reply' => $reply];
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

        if (preg_match('/^(?:jadi|jadinya|ubah|ganti|kurangi|ralat|tambah|nambah|\+)\b/i', $clean)) {
            return false;
        }

        $hasOrderKeyword = (bool) preg_match('/\b(?:pesen|pesan|mau|kirim|order|minta)\b/i', $clean);
        $hasTime = (bool) preg_match('/\b(?:jam\s*\d{1,2}|besok|pagi|siang|sore|malam)\b/i', $clean);
        $hasQtyAndUkuran = (bool) (preg_match('/\d+\s*(?:ball|bal|sak|karung)/i', $clean) && preg_match('/(?:20|19|15|10)\s*(?:kg|rb|k)?/i', $clean));

        return $hasOrderKeyword || $hasTime || $hasQtyAndUkuran;
    }

    /**
     * Ekstraksi intensi ukuran dan/atau kuantitas pada pesan susulan.
     */
    protected function extractFollowupIntent(string $message, float|int $currentQty, string $currentUkuran): ?array
    {
        $clean = strtolower(trim($message));

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

        if (preg_match('/(?:^|\b)(?:jadi|jadinya|ganti)?\s*(?:setengah|separuh|1\/2|0[,.]5)\s*(?:ball|bal|sak|karung|biji|bungkus)?(?:\b|$)/i', $clean)) {
            $newQty = 0.5;
            $hasQty = true;
        } elseif (preg_match('/(?:tambah|nambah|plus|\+)\s*(\d+(?:[.,]\d+)?)/i', $clean, $m)) {
            $add = (float) str_replace(',', '.', $m[1]);
            $sum = $currentQty + $add;
            $newQty = (fmod($sum, 1.0) === 0.0) ? (int) $sum : round($sum, 2);
            $hasQty = true;
        } elseif (preg_match('/(?:jadi|jadinya|ganti)?\s*(\d+(?:[.,]\d+)?)\s*(?:ball|bal|sak|karung|biji|bungkus)\b/i', $clean, $m)) {
            $val = (float) str_replace(',', '.', $m[1]);
            $newQty = (fmod($val, 1.0) === 0.0) ? (int) $val : round($val, 2);
            $hasQty = true;
        } elseif (preg_match('/^(?:jadi|jadinya)?\s*(\d+(?:[.,]\d+)?)\s*$/i', $clean, $m)) {
            $candidate = (float) str_replace(',', '.', $m[1]);
            if (!in_array($candidate, [10, 15, 19, 20])) {
                $newQty = (fmod($candidate, 1.0) === 0.0) ? (int) $candidate : round($candidate, 2);
                $hasQty = true;
            }
        }

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

        // Catat bahwa bot telah mengirim pesan ke nomor ini dalam 24 jam terakhir
        Cache::put('bot_sent_to_' . $canonical, true, now()->addHours(24));

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

        $customer = Customer::whereIn('phone', $variations)->first();

        if (!$customer) {
            $customerPhone = CustomerPhone::whereIn('phone', $variations)->first();
            if ($customerPhone) {
                $customer = $customerPhone->customer;
            }
        }

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
     * Klasifikasi intent pesan pelanggan (Fase 13):
     * order | repeat_last | delivery_status_or_complaint | driver_inquiry | ack_smalltalk | other_human_needed
     */
    public function detectIntent(string $message, bool $isNewCustomer = false, bool $hasDraft = false): string
    {
        $text = strtolower(trim($message));

        // 1. Cek keluhan / komplain / status pengiriman
        $hasComplaintWords = preg_match('/\b(?:belum|blm|blum)\s*(?:sampe|sampai|dianter|diantar|datang|nyampe)\b/i', $text)
            || preg_match('/\bmana\s+(?:es(?:nya)?|pesanan|orderan)\b/i', $text)
            || preg_match('/\b(?:ko|kok)\s+(?:lama|blm|belum)\b/i', $text)
            || preg_match('/\b(?:lama\s*(?:amat|banget|bgt)|telat)\b/i', $text)
            || preg_match('/\b(?:komplain|complain)\b/i', $text)
            || preg_match('/\b(?:status\s*(?:kirim|pengiriman|pengantaran|pesanan|order))\b/i', $text);

        if ($hasComplaintWords) {
            return 'delivery_status_or_complaint';
        }

        // 2. Pertanyaan netral tentang driver / pengantaran (tanpa kata komplain)
        if (preg_match('/\b(?:driver|supir|sopir|kurir)\b/i', $text)) {
            return 'driver_inquiry';
        }

        // 3. Permintaan bantuan admin eksplisit
        if (AiOrderDebouncer::isHumanHandoff($message)) {
            return 'other_human_needed';
        }

        // 4. Sapaan / ACK / Smalltalk (Pencegahan Menyela Percakapan Manusia)
        $hasOrderIntentWords = preg_match('/\b(?:pesan|pesen|kirim|order|mau|minta|butuh|beli|antar|bawa|drop|toko|cafe|warung)\b/i', $text)
            || preg_match('/(?:20|19|15|10)\s*(?:kg|rb|k|ribu)?/i', $text)
            || preg_match('/\d+\s*(?:ball|karung|sak|bal|biji)/i', $text);

        if (!$hasOrderIntentWords) {
            $smalltalkWords = 'wa\'?alaikum(?:us)?salam|assalamu\'?alaikum|halo|hai|pagi|siang|sore|malam|selamat\s+(?:pagi|siang|sore|malam)|oke|ok|siap|sip|makasih|makasi|terima\s*kasih|terimakasih|thanks|thx|iya|ya|p|permisi|kak';
            $smalltalkPattern = "/^(?:(?:{$smalltalkWords})[\s,.\-!]*)+$/i";
            if (preg_match($smalltalkPattern, $text)) {
                return 'ack_smalltalk';
            }
        }

        // 5. Pesanan berulang (repeat_last)
        $isRepeatLast = preg_match('/\b(?:kaya|kayak|seperti|sama)\s*(?:biasa(?:nya)?|kemarin|kmrn)\b/i', $text)
            || preg_match('/\b(?:biasa\s*(?:pesan|order|kirim))\b/i', $text)
            || preg_match('/\b(?:pesan|order|kirim)\s+(?:kaya|kayak|seperti)?\s*biasa(?:nya)?\b/i', $text);

        if ($isRepeatLast) {
            return 'repeat_last';
        }

        // 6. Order
        if ($this->llmService->isPotentialOrder($message, $isNewCustomer) || $hasDraft || $hasOrderIntentWords || preg_match('/^(?:batal|cancel|ubah|ganti)\b/i', $text)) {
            return 'order';
        }

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
