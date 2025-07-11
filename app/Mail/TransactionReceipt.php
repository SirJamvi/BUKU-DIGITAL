<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Transaction;

class TransactionReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $transaction;

    /**
     * Buat instance pesan baru.
     *
     * @param Transaction $transaction
     */
    public function __construct(Transaction $transaction)
    {
        // Memuat relasi yang dibutuhkan untuk struk
        $this->transaction = $transaction->load('customer', 'createdBy', 'details.product');
    }

    /**
     * Dapatkan "amplop" pesan.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Struk Transaksi Anda - No: ' . $this->transaction->id,
        );
    }

    /**
     * Dapatkan konten definisi pesan.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.transactions.receipt',
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