<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Carbon\Carbon;
use Exception;

class BackupData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jalankan job.
     *
     * @return void
     */
    public function handle(): void
    {
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host');

        if (empty($dbName) || empty($dbUser)) {
            logger()->error('BackupData Job: Konfigurasi database tidak lengkap.');
            return;
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

            if (!$result->successful()) {
                logger()->error('BackupData Job Failed: ' . $result->errorOutput());
            } else {
                logger()->info("Backup database berhasil dibuat: {$filePath}");
            }
        } catch (Exception $e) {
            logger()->error("BackupData Job Exception: " . $e->getMessage());
        }
    }
}