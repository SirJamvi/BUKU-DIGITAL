<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Product;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected $product;

    /**
     * Buat instance notifikasi baru.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Tentukan saluran pengiriman notifikasi.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database']; // Kirim melalui email dan simpan di database
    }

    /**
     * Dapatkan representasi email dari notifikasi.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->error() // Menggunakan tema email error/peringatan
                    ->subject('Peringatan Stok Rendah: ' . $this->product->name)
                    ->line("Stok untuk produk '{$this->product->name}' telah mencapai batas minimum.")
                    ->line("Stok saat ini: {$this->product->inventory->current_stock} {$this->product->unit}.")
                    ->action('Lihat Inventaris', url('/admin/inventory'))
                    ->line('Harap segera lakukan pemesanan ulang kepada pemasok.');
    }

    /**
     * Dapatkan representasi array dari notifikasi (untuk disimpan di database).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'current_stock' => $this->product->inventory->current_stock,
            'message' => "Stok untuk produk '{$this->product->name}' telah mencapai batas minimum.",
            'url' => '/admin/inventory',
        ];
    }
}