<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Product;

class LowStockAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $product;

    /**
     * Buat instance pesan baru.
     *
     * @param Product $product
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Dapatkan "amplop" pesan.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Peringatan Stok Rendah: ' . $this->product->name,
        );
    }

    /**
     * Dapatkan konten definisi pesan.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.alerts.low_stock',
        );
    }

    /**
     * Dapatkan lampiran untuk pesan.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}