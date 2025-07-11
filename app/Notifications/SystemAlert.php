<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected $level; // 'info', 'success', 'warning', 'error'
    protected $message;
    protected $subject;

    /**
     * Buat instance notifikasi baru.
     */
    public function __construct(string $message, string $subject = 'Pemberitahuan Sistem', string $level = 'info')
    {
        $this->message = $message;
        $this->subject = $subject;
        $this->level = $level;
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
        $mailMessage = (new MailMessage)
            ->subject($this->subject)
            ->line($this->message);

        // Atur level/tema email berdasarkan tipe alert
        if (in_array($this->level, ['warning', 'error'])) {
            $mailMessage->level($this->level);
        }

        return $mailMessage;
    }

    /**
     * Dapatkan representasi array dari notifikasi.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'level' => $this->level,
            'message' => $this->message,
            'subject' => $this->subject,
        ];
    }
}