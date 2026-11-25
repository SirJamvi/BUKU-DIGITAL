<?php

namespace App\Jobs;

use App\Events\OrderReceivedEvent;
use App\Models\Customer;
use App\Models\PosQueue;
use App\Services\OrderParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background Job untuk memproses pesan masuk WhatsApp menggunakan Jev AI.
 * Hanya akan menyimpan ke pos_queues dan memancarkan OrderReceivedEvent
 * jika primitive 'Noul' dari Jev AI mengonfirmasi niat pesanan es kristal yang valid.
 */
class ProcessWhatsAppOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan maksimal jika job gagal.
     */
    public int $tries = 3;

    /**
     * Timeout job dalam detik.
     */
    public int $timeout = 30;

    /**
     * Konstruktor job.
     */
    public function __construct(
        public string $customerPhone,
        public string $rawMessage,
        public ?string $customerName = null,
        public ?int $businessId = null,
        public ?array $rawPayload = null
    ) {}

    /**
     * Eksekusi job pemrosesan pesan.
     */
    public function handle(OrderParserService $parserService): void
    {
        Log::info("ProcessWhatsAppOrderJob: Mengevaluasi pesan dari {$this->customerPhone} menggunakan Jev AI...");

        try {
            // 1. Eksekusi evaluasi pesan via Jev AI (TypeSafe AI)
            $parsed = $parserService->parse($this->rawMessage, $this->businessId);

            $noulScore = (float) ($parsed['noul_score'] ?? 0.0);
            $isOrder = (bool) ($parsed['is_order'] ?? false);

            // 2. FILTER KETAT: Hanya lanjutkan jika primitive 'Noul' Jev AI mengonfirmasi pesanan valid
            if (!$isOrder || $noulScore < 0.70) {
                Log::info("ProcessWhatsAppOrderJob: Pesan dari {$this->customerPhone} DIABAIKAN. Jev AI 'Noul' mengonfirmasi BUKAN pesanan es kristal valid (Noul Score: {$noulScore}).", [
                    'raw_message' => $this->rawMessage,
                    'jev_product' => $parsed['product'] ?? 'unknown',
                ]);
                return;
            }

            Log::info("ProcessWhatsAppOrderJob: Jev AI 'Noul' valid ({$noulScore}). Melanjutkan pembuatan antrean POS...");

            // 3. Normalisasi nomor telepon pelanggan (08xx)
            $digitsPhone = preg_replace('/\D/', '', $this->customerPhone);
            $normalizedPhone = str_starts_with($digitsPhone, '62')
                ? '0' . substr($digitsPhone, 2)
                : $digitsPhone;

            // 4. Cari profil pelanggan terdaftar di database
            $customer = Customer::where('phone', $normalizedPhone)
                ->orWhere('phone', $digitsPhone)
                ->orWhere('phone', '62' . ltrim($normalizedPhone, '0'))
                ->first();

            $customerName = $customer?->name ?? $this->customerName;
            if (empty($customerName)) {
                $customerName = 'Pelanggan WA (' . substr($normalizedPhone, -4) . ')';
            }

            $businessId = $this->businessId ?? $customer?->business_id;

            // 5. Simpan record antrean baru ke tabel pos_queues
            $posQueue = PosQueue::create([
                'business_id'    => $businessId,
                'customer_phone' => $normalizedPhone,
                'customer_name'  => $customerName,
                'product_id'     => $parsed['product_id'],
                'product_name'   => $parsed['product_name'],
                'quantity'       => $parsed['qty'],
                'unit'           => 'ball',
                'raw_message'    => $this->rawMessage,
                'status'         => 'pending',
                'notes'          => null,
                'parsed_data'    => [
                    'provider'    => 'jev_ai',
                    'noul_score'  => $noulScore,
                    'jev_product' => $parsed['product'],
                    'confidence'  => $parsed['confidence'] ?? 0.0,
                    'raw_answers' => $parsed['raw_answers'] ?? [],
                    'raw_payload' => $this->rawPayload,
                ],
            ]);

            Log::info("ProcessWhatsAppOrderJob: Antrean #{$posQueue->id} berhasil dibuat ({$posQueue->product_name} x {$posQueue->quantity} {$posQueue->unit}).");

            // 6. Broadcast secara realtime ke Livewire POS UI via Laravel Reverb / WebSocket
            event(new OrderReceivedEvent($posQueue));

            Log::info("ProcessWhatsAppOrderJob: OrderReceivedEvent berhasil dipancarkan untuk Antrean #{$posQueue->id}.");

        } catch (Throwable $e) {
            Log::error("ProcessWhatsAppOrderJob Gagal: " . $e->getMessage(), [
                'phone'   => $this->customerPhone,
                'message' => $this->rawMessage,
                'trace'   => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
