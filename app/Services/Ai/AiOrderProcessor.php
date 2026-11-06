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

    public function __construct(LlmOrderService $llmService, FonnteService $fonnteService)
    {
        $this->llmService = $llmService;
        $this->fonnteService = $fonnteService;
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
        $rateLimitMax = (int) env('AI_ORDER_RATE_LIMIT', 20);
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

        if (AiOrderDebouncer::isHumanHandoff($combinedMessage)) {
            Cache::put('human_mode_' . $canonicalSender, true, now()->addMinutes(15));
            $reply = "Baik Kak, Anda telah terhubung dengan Admin. Silakan sampaikan pesan Anda, tim kami akan segera membalas.";
            $this->sendReply($canonicalSender, $reply);

            Log::info("[AiOrderProcessor] Human mode diaktifkan untuk " . PhoneNumberHelper::mask($canonicalSender) . " selama 15 menit.");
            return ['status' => 'human_mode_activated', 'reply' => $reply];
        }

        // ==============================================================
        // 3. IDENTIFIKASI PELANGGAN (LAMA vs BARU)
        // ==============================================================
        $customer = $this->findCustomerByPhone($canonicalSender);
        $isNewCustomer = ($customer === null);
        $customerName = $customer ? $customer->name : 'Kak';

        // ==============================================================
        // 4. KELOLA DRAFT STATE
        // ==============================================================
        $draft = AiOrderDraft::getOrCreateDraft($canonicalSender);

        // Cek order aktif hari ini (validated / processed / pending)
        $latestOrder = TemporaryOrder::where('sender_phone', $canonicalSender)
            ->whereDate('created_at', Carbon::today())
            ->latest('id')
            ->first();

        $textLower = strtolower(trim($combinedMessage));

        // ==============================================================
        // 5. PENANGANAN BATAL / CANCEL
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
        // 6. PENANGANAN UBAH / PERUBAHAN PESANAN
        // ==============================================================
        $isUbahKeyword = (bool) preg_match('/^(?:ubah|ganti)\b/i', $textLower);
        if ($isUbahKeyword) {
            if ($latestOrder && $latestOrder->status === 'validated') {
                // Buka kembali draft dari pesanan terakhir
                $draft->data = [
                    'nama_toko'        => $latestOrder->customer ? $latestOrder->customer->name : null,
                    'alamat'           => $latestOrder->customer ? $latestOrder->customer->address : null,
                    'lat'              => null,
                    'lng'              => null,
                    'jumlah_ball'      => $latestOrder->jumlah_ball,
                    'ukuran_produk'    => $latestOrder->ukuran_produk,
                    'waktu_pengiriman' => $latestOrder->delivery_time ? $latestOrder->delivery_time->format('Y-m-d H:i:s') : null,
                    'is_registration'  => false,
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
        // 7. PENANGANAN SHARE LOCATION
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

            // Jika hanya share lokasi tanpa teks dan belum ada order yang terisi
            if (empty($combinedMessage) && !$draft->hasQty()) {
                $draft->setAwaiting('qty');
                $reply = ReplyTemplates::render('location_only');
                $this->sendReply($canonicalSender, $reply);
                return ['status' => 'location_only', 'reply' => $reply];
            }
        } elseif (($combinedMessage === 'non-text message' || $combinedMessage === 'non-button message') && empty($location)) {
            $reply = ReplyTemplates::render('unsupported_media');
            $this->sendReply($canonicalSender, $reply);
            return ['status' => 'unsupported_media', 'reply' => $reply];
        }

        // ==============================================================
        // 8. EKSTRAKSI SLOT MENGGUNAKAN LLM / FALLBACK
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
            $this->sendReply($canonicalSender, $reply);
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
        // 9. CEK APAKAH PESAN HANYA SAPAAN BIASA
        // ==============================================================
        if (
            !$parsed['is_order'] &&
            !$draft->hasQty() &&
            empty($draft->data['ukuran_produk']) &&
            empty($draft->data['nama_toko'])
        ) {
            $reply = ReplyTemplates::render('greeting');
            $this->sendReply($canonicalSender, $reply);
            return ['status' => 'greeting', 'reply' => $reply];
        }

        // ==============================================================
        // 10. PEMERIKSAAN KELENGKAPAN SLOT (AWAITING CHECK)
        // ==============================================================

        // A. Cek Kuantitas Ball
        if (!$draft->hasQty()) {
            $draft->setAwaiting('qty');
            $reply = ReplyTemplates::render('need_qty');
            $this->sendReply($canonicalSender, $reply);
            return ['status' => 'need_qty', 'reply' => $reply];
        }

        $qty = (int) $draft->data['jumlah_ball'];

        // B. Cek Ukuran Produk (Default adalah 20kg jika tidak disebut)
        if (!$draft->hasUkuran()) {
            $draft->mergeData(['ukuran_produk' => '20kg']);
        }
        $ukuran = (string) $draft->data['ukuran_produk'];

        // C. Cek Data Pelanggan Baru (Wajib Nama Toko & Alamat/Lokasi)
        if ($isNewCustomer && !$draft->hasNameAndAddress()) {
            $draft->setAwaiting('nama_alamat');
            $reply = ReplyTemplates::render('need_name_address', [
                'qty'    => $qty,
                'ukuran' => $ukuran,
            ]);
            $this->sendReply($canonicalSender, $reply);
            return ['status' => 'need_name_address', 'reply' => $reply];
        }

        // ==============================================================
        // 11. VALIDASI STOK REAL-TIME & SOFT RESERVATION
        // ==============================================================
        $availableStock = 0;
        if ($this->llmService->isStockExceeded($qty, $ukuran, $availableStock)) {
            $reply = ReplyTemplates::render('out_of_stock');
            // Pastikan tidak ada kebocoran angka stok
            $reply = $this->llmService->filterStockNumbers($reply, $availableStock);
            $this->sendReply($canonicalSender, $reply);
            return ['status' => 'out_of_stock', 'reply' => $reply];
        }

        // ==============================================================
        // 12. OTOMASI REGISTRASI PELANGGAN BARU (Bila Belum Terdaftar)
        // ==============================================================
        if ($isNewCustomer) {
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

        // ==============================================================
        // 13. BUAT / PERBARUI ORDER (STATUS: VALIDATED)
        // ==============================================================
        $deliveryTime = !empty($draft->data['waktu_pengiriman']) ? $draft->data['waktu_pengiriman'] : null;

        // Cek heuristik 5 menit atau draft modifying_order_id untuk update order lama (UBAH)
        $modifyingOrderId = $draft->data['modifying_order_id'] ?? null;
        $orderToUpdate = null;

        if ($modifyingOrderId) {
            $orderToUpdate = TemporaryOrder::find($modifyingOrderId);
        } elseif (
            $latestOrder &&
            $latestOrder->status === 'validated' &&
            $latestOrder->updated_at->diffInMinutes(now()) <= 5
        ) {
            $orderToUpdate = $latestOrder;
        }

        if ($orderToUpdate && $orderToUpdate->status === 'validated') {
            $orderToUpdate->update([
                'jumlah_ball'   => $qty,
                'ukuran_produk' => $ukuran,
                'delivery_time' => $deliveryTime,
                'last_chat'     => $combinedMessage,
            ]);
            $order = $orderToUpdate;
            Log::info("[AiOrderProcessor] Order #{$order->id} diperbarui (UBAH) menjadi {$qty} ball {$ukuran}");
        } else {
            $order = TemporaryOrder::create([
                'customer_id'   => $customer ? $customer->id : 108,
                'sender_phone'  => $canonicalSender,
                'jumlah_ball'   => $qty,
                'ukuran_produk' => $ukuran,
                'delivery_time' => $deliveryTime,
                'status'        => 'validated', // LANGSUNG VALIDATED (Masuk antrean kasir)!
                'last_chat'     => $combinedMessage,
            ]);
            Log::info("[AiOrderProcessor] Order #{$order->id} dibuat dengan status 'validated' ({$qty} ball {$ukuran})");
        }

        // ==============================================================
        // 14. FORMAT BALASAN KE PELANGGAN DARI TEMPLATE
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

        // Bersihkan draft setelah order sukses tercatat
        $draft->clearDraft();

        $this->sendReply($canonicalSender, $reply);

        return [
            'status'        => 'validated',
            'order_id'      => $order->id,
            'is_new_customer' => $isNewCustomer,
            'reply'         => $reply,
        ];
    }

    /**
     * Kirim balasan WhatsApp ke pelanggan atau catat di log bila mode DRY RUN aktif.
     */
    public function sendReply(string $target, string $message): array
    {
        $isDryRun = filter_var(env('AI_ORDER_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN);

        if ($isDryRun) {
            Log::info("[AiOrderProcessor - DRY RUN] Kirim WA ke " . PhoneNumberHelper::mask($target) . ":\n{$message}");
            return ['status' => true, 'dry_run' => true, 'message' => $message];
        }

        return $this->fonnteService->sendMessage($target, $message);
    }

    /**
     * Cari customer berdasarkan variasi nomor telepon.
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
}
