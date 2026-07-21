<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExpenseSyncController;

Route::prefix('v1')->group(function () {
    // Endpoint untuk menerima data gaji (yang sudah ada)
    Route::post('/sync/salary', [ExpenseSyncController::class, 'storeFromAttendance']);

    // [BARU] Endpoint untuk iOS Shortcuts WhatsApp Report
    Route::get('/whatsapp-report', [\App\Http\Controllers\Api\V1\WhatsappReportController::class, 'generateDailyReport']);
});
