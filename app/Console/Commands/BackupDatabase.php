<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    /**
     * Nama dan signature dari console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * Deskripsi dari console command.
     *
     * @var string
     */
    protected $description = 'Mencadangkan database aplikasi ke file SQL.';

    /**
     * Menjalankan console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Memulai proses backup database...');

        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host');

        if (empty($dbName) || empty($dbUser)) {
            $this->error('Konfigurasi database tidak lengkap. Pastikan DB_DATABASE dan DB_USERNAME sudah diatur.');
            return 1;
        }

        $backupPath = storage_path('app/backups');
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $fileName = "backup-{$dbName}-" . Carbon::now()->format('Y-m-d_H-i-s') . ".sql";
        $filePath = "{$backupPath}/{$fileName}";

        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbName),
            escapeshellarg($filePath)
        );

        try {
            $result = Process::run($command);

            if ($result->successful()) {
                $this->info("Backup database berhasil dibuat: {$filePath}");
                return 0;
            } else {
                $this->error('Gagal membuat backup database.');
                $this->error('Error Output: ' . $result->errorOutput());
                logger()->error('Database Backup Failed: ' . $result->errorOutput());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Terjadi exception saat backup: {$e->getMessage()}");
            logger()->error('Database Backup Exception: ' . $e->getMessage());
            return 1;
        }
    }
}