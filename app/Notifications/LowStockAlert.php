<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Branch;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected Product $product;
    protected ?Inventory $inventory;
    protected ?Branch $branch;

    /**
     * Buat instance notifikasi baru dengan dukungan cabang.
     */
    public function __construct(Product $product, ?Inventory $inventory = null, ?Branch $branch = null)
    {
        $this->product = $product;
        $this->inventory = $inventory;
        $this->branch = $branch;

        if (!$this->branch && $this->inventory && $this->inventory->branch_id) {
            $this->branch = $this->inventory->branch ?: Branch::find($this->inventory->branch_id);
        }
    }

    /**
     * Tentukan saluran pengiriman notifikasi.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Dapatkan representasi email dari notifikasi.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $branchName = $this->branch?->name;
        $currentStock = $this->inventory?->current_stock ?? $this->product->inventory?->current_stock ?? 0;
        $branchSuffix = $branchName ? " (Cabang: {$branchName})" : '';

        return (new MailMessage)
            ->error()
            ->subject('Peringatan Stok Rendah: ' . $this->product->name . $branchSuffix)
            ->line("Stok untuk produk '{$this->product->name}'" . ($branchName ? " di Cabang {$branchName}" : "") . " telah mencapai batas minimum.")
            ->line("Stok saat ini: {$currentStock} {$this->product->unit}.")
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
        $branchName = $this->branch?->name;
        $branchId = $this->branch?->id ?? $this->inventory?->branch_id;
        $currentStock = $this->inventory?->current_stock ?? $this->product->inventory?->current_stock ?? 0;

        return [
            'product_id'    => $this->product->id,
            'product_name'  => $this->product->name,
            'branch_id'     => $branchId,
            'branch_name'   => $branchName,
            'current_stock' => $currentStock,
            'message'       => "Stok untuk produk '{$this->product->name}'" . ($branchName ? " di Cabang {$branchName}" : "") . " telah mencapai batas minimum.",
            'url'           => '/admin/inventory',
        ];
    }
}
