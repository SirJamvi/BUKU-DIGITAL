<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\UserActivityLog;
use App\Models\UserSession;
use Carbon\Carbon;
use Exception;

class CleanupOldData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    protected $model;
    /**
     * @var int
     */
    protected $days;

    /**
     * Buat instance job baru.
     *
     * @param string $model (Contoh: UserSession::class)
     * @param int $days
     */
    public function __construct(string $model, int $days)
    {
        $this->model = $model;
        $this->days = $days;
    }

    /**
     * Jalankan job.
     *
     * @return void
     */
    public function handle(): void
    {
        if (!class_exists($this->model)) {
            logger()->error("CleanupOldData Job: Model {$this->model} tidak ditemukan.");
            return;
        }

        try {
            $cutoffDate = Carbon::now()->subDays($this->days);
            
            // Menggunakan 'created_at' sebagai kolom default untuk pembersihan
            $deletedCount = $this->model::where('created_at', '<', $cutoffDate)->delete();

            if ($deletedCount > 0) {
                logger()->info("Berhasil membersihkan {$deletedCount} record lama dari model {$this->model}.");
            }

        } catch (Exception $e) {
            logger()->error("Gagal menjalankan job CleanupOldData untuk model {$this->model}. Error: " . $e->getMessage());
        }
    }
}