<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class NewUserCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $tempPassword;

    /**
     * Buat instance pesan baru.
     *
     * @param User $user
     * @param string|null $tempPassword Kata sandi sementara jika ada
     */
    public function __construct(User $user, ?string $tempPassword = null)
    {
        $this->user = $user;
        $this->tempPassword = $tempPassword;
    }

    /**
     * Dapatkan "amplop" pesan.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Selamat Datang di Sistem Bisnis Kami!',
        );
    }

    /**
     * Dapatkan konten definisi pesan.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.users.new_user_created',
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