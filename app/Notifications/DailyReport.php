<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyReport extends Notification implements ShouldQueue
{
    use Queueable;

    protected $reportData;

    /**
     * Buat instance notifikasi baru.
     */
    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    /**
     * Tentukan saluran pengiriman notifikasi.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Dapatkan representasi email dari notifikasi.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Laporan Harian Anda Telah Siap')
                    ->line('Berikut adalah ringkasan laporan harian untuk tanggal ' . now()->subDay()->format('d F Y') . ':')
                    ->line('Total Penjualan: Rp ' . number_format($this->reportData['total_sales'], 0, ',', '.'))
                    ->line('Total Transaksi: ' . $this->reportData['total_transactions'] . ' transaksi')
                    ->action('Lihat Laporan Lengkap', url('/admin/reports/sales'))
                    ->line('Terima kasih telah menggunakan sistem kami.');
    }
}