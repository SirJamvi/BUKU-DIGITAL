<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Transaction;

class NewTransaction extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transaction;

    /**
     * Buat instance notifikasi baru.
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Tentukan saluran pengiriman notifikasi.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Cukup simpan di database untuk dilihat di panel admin
    }

    /**
     * Dapatkan representasi array dari notifikasi.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transaction_id' => $this->transaction->id,
            'amount' => $this->transaction->total_amount,
            'kasir_name' => $this->transaction->createdBy->name,
            'message' => "Transaksi baru sebesar Rp " . number_format($this->transaction->total_amount, 0, ',', '.') . " oleh {$this->transaction->createdBy->name}.",
            'url' => route('admin.transactions.show', $this->transaction->id),
        ];
    }
}