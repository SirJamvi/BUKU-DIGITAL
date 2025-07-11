<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Transaction;
use App\Services\NotificationService;
use Exception;

class ProcessTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * Buat instance job baru.
     *
     * @param Transaction $transaction
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Jalankan job.
     *
     * @param NotificationService $notificationService
     * @return void
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            // Contoh: Kirim notifikasi ke admin tentang transaksi baru
            $notificationService->sendNewTransactionNotification($this->transaction);

            // Contoh: Kirim struk ke pelanggan jika email ada
            if ($this->transaction->customer && $this->transaction->customer->email) {
                $notificationService->sendTransactionReceipt($this->transaction);
            }

        } catch (Exception $e) {
            logger()->error("Gagal memproses job ProcessTransaction untuk ID: {$this->transaction->id}. Error: " . $e->getMessage());
            // Anda bisa melempar kembali exception agar job diulang jika perlu
            // $this->fail($e);
        }
    }
}