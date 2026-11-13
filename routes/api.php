<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExpenseSyncController;
use App\Http\Controllers\Api\V1\WhatsappReportController;
use App\Http\Controllers\Api\V1\Kasir\PosApiController;
use App\Http\Controllers\Api\V1\Auth\LoginApiController; // [TAMBAHAN BARU]
use App\Http\Controllers\Api\V1\AiOrderWebhookController;

Route::prefix('v1')->group(function () {

    // ==============================================================
    // RUTE WEBHOOK FONNTE (AI ORDER RECEIVER)
    // ==============================================================
    Route::post('/webhook/fonnte', [AiOrderWebhookController::class, 'handle'])->name('webhook.fonnte');

    // Rute Publik (Tidak butuh token)
    Route::post('/sync/salary', [ExpenseSyncController::class, 'storeFromAttendance']);
    Route::get('/whatsapp-report', [WhatsappReportController::class, 'generateDailyReport']);

    // [RUTE BARU UNTUK SHORTCUT PENGINGAT KASBON]
    Route::get('/whatsapp-kasbon-reminder', [WhatsappReportController::class, 'checkKasbonLimit']);

    // ==============================================================
    // RUTE AUTENTIKASI MOBILE
    // ==============================================================
    Route::post('/login', [LoginApiController::class, 'login']);
    Route::post('/login/google', [LoginApiController::class, 'googleLogin']);


    // ==============================================================
    // Rute Khusus Aplikasi Mobile Kasir (Android) - WAJIB LOGIN & BRANCH CONTEXT
    // ==============================================================
    Route::middleware(['auth:sanctum', 'branch.context'])->group(function () {

        // Rute Logout
        Route::post('/logout', [LoginApiController::class, 'logout']);

        Route::prefix('kasir')->name('api.kasir.')->group(function () {
            // Antrean Pesanan AI Mobile (Tervalidasi per cabang aktif)
            Route::get('/ai-orders', [AiOrderWebhookController::class, 'getValidatedOrders'])->name('ai_orders');
            Route::post('/ai-orders/{id}/processed', [AiOrderWebhookController::class, 'markAsProcessed'])->name('ai_orders.processed');

            Route::get('/pos-data', [PosApiController::class, 'getPosData'])->name('pos_data');
            Route::post('/order', [PosApiController::class, 'storeOrder'])->name('store_order');
            Route::get('/orders/delivery', [PosApiController::class, 'getDeliveryOrders'])->name('delivery_orders');
            Route::post('/orders/assign-driver', [PosApiController::class, 'assignDriver'])->name('assign_driver');
            Route::post('/orders/confirm-payment', [PosApiController::class, 'confirmPayment'])->name('confirm_payment');
        });
    });
});
