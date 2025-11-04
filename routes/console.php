<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; // <-- 1. TAMBAHKAN IMPORT INI
use App\Console\Commands\ProcessMonthlyClosing; // <-- 2. TAMBAHKAN IMPORT INI

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =======================================================
// 3. TAMBAHKAN PENJADWALAN OTOMATIS DI SINI
// =======================================================
// Menjalankan perintah 'app:process-monthly-closing'
// pada hari pertama setiap bulan, pukul 01:00 pagi.
Schedule::command(ProcessMonthlyClosing::class)->monthlyOn(1, '01:00');