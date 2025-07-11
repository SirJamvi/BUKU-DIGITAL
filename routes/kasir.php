<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Kasir\DashboardController as KasirDashboardController;
use App\Http\Controllers\Kasir\TransactionController;
use App\Http\Controllers\Kasir\ProductController as KasirProductController;
use App\Http\Controllers\Kasir\ReportController as KasirReportController;

// Kasir routes dengan middleware auth dan kasir
Route::middleware(['auth', 'kasir'])->prefix('kasir')->name('kasir.')->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [KasirDashboardController::class, 'index'])->name('dashboard');
    Route::get('/', [KasirDashboardController::class, 'index'])->name('index');
    
    // Transaction Management
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/create', [TransactionController::class, 'create'])->name('create');
        Route::post('/store', [TransactionController::class, 'store'])->name('store');
        Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
        Route::get('/{transaction}/print', [TransactionController::class, 'print'])->name('print');
        Route::put('/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('cancel');
    });
    
    // Product Lookup untuk kasir
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [KasirProductController::class, 'index'])->name('index');
        Route::get('/search', [KasirProductController::class, 'search'])->name('search');
        Route::get('/{product}', [KasirProductController::class, 'show'])->name('show');
    });
    
    // Reports untuk kasir
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [KasirReportController::class, 'index'])->name('index');
        Route::get('/daily', [KasirReportController::class, 'daily'])->name('daily');
        Route::get('/shift', [KasirReportController::class, 'shift'])->name('shift');
    });
    
    // POS System
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [TransactionController::class, 'pos'])->name('index');
        Route::post('/add-item', [TransactionController::class, 'addItem'])->name('add-item');
        Route::delete('/remove-item/{item}', [TransactionController::class, 'removeItem'])->name('remove-item');
        Route::post('/apply-discount', [TransactionController::class, 'applyDiscount'])->name('apply-discount');
        Route::post('/checkout', [TransactionController::class, 'checkout'])->name('checkout');
    });
});