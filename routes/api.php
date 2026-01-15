<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExpenseSyncController;

Route::prefix('v1')->group(function () {
    // Endpoint untuk menerima data gaji
    Route::post('/sync/salary', [ExpenseSyncController::class, 'storeFromAttendance']);
});