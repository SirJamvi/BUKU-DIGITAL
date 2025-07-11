<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserSession;
use Carbon\Carbon;

class CleanupSessions extends Command
{
    /**
     * Nama dan signature dari console command.
     *
     * @var string
     */
    protected $signature = 'sessions:cleanup {--days=30 : Hapus sesi yang lebih lama dari (hari)}';

    /**
     * Deskripsi dari console command.
     *
     * @var string
     */
    protected $description = 'Membersihkan data sesi pengguna yang sudah lama dari tabel user_sessions.';

    /**
     * Menjalankan console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $this->error('Jumlah hari harus lebih besar dari 0.');
            return 1;
        }

        $this->info("Mencari sesi yang lebih lama dari {$days} hari untuk dibersihkan...");

        try {
            $cutoffDate = Carbon::now()->subDays($days);

            $deletedCount = UserSession::where('logout_time', '<', $cutoffDate)
                ->orWhere('last_activity', '<', $cutoffDate)
                ->delete();

            if ($deletedCount > 0) {
                $this->info("Berhasil membersihkan {$deletedCount} data sesi yang sudah lama.");
            } else {
                $this->info('Tidak ada data sesi lama yang perlu dibersihkan.');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Terjadi kesalahan saat membersihkan sesi: {$e->getMessage()}");
            logger()->error('CleanupSessions Command Error: ' . $e->getMessage());
            return 1;
        }
    }
}