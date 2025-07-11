<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Exception;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var mixed
     */
    protected $notifiable;
    /**
     * @var Notification
     */
    protected $notification;

    /**
     * Buat instance job baru.
     *
     * @param mixed $notifiable (Contoh: User atau koleksi User)
     * @param Notification $notification (Contoh: new LowStockAlert($product))
     */
    public function __construct($notifiable, Notification $notification)
    {
        $this->notifiable = $notifiable;
        $this->notification = $notification;
    }

    /**
     * Jalankan job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            NotificationFacade::send($this->notifiable, $this->notification);
        } catch (Exception $e) {
            logger()->error("Gagal menjalankan job SendNotification: " . $e->getMessage());
        }
    }
}