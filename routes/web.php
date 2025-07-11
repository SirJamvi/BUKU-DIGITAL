<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Kasir\DashboardController;
use App\Http\Controllers\Kasir\PosController;
use App\Http\Controllers\Kasir\TransactionController;
use App\Http\Controllers\Kasir\CustomerController;
use App\Http\Controllers\Kasir\ProductController;
use App\Http\Controllers\Kasir\ReportController;

// Rute untuk Kasir, dilindungi oleh middleware 'auth' dan 'kasir'
Route::middleware(['auth', 'kasir'])->prefix('kasir')->name('kasir.')->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    
    // Point of Sale (POS) System - Menggunakan PosController
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('/store', [PosController::class, 'store'])->name('store');
    });

    // Manajemen Transaksi
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
    });

    // Manajemen Pelanggan
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
    });
    
    // Pencarian Produk (Read-Only untuk Kasir)
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/search', [ProductController::class, 'search'])->name('search'); // Untuk AJAX search
        Route::get('/{product}', [ProductController::class, 'show'])->name('show');
    });
    
    // Laporan (Terbatas untuk Kasir)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
    });
    
});