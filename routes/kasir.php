<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Kasir\DashboardController as KasirDashboardController;
use App\Http\Controllers\Kasir\TransactionController;
use App\Http\Controllers\Kasir\ProductController as KasirProductController;
use App\Http\Controllers\Kasir\ReportController as KasirReportController;
use App\Http\Controllers\Kasir\PosController;
use App\Http\Controllers\Kasir\CustomerController;

// TIDAK PERLU Route::middleware(...)->group(...) lagi di sini.

// Dashboard
Route::get('/dashboard', [KasirDashboardController::class, 'index'])->name('dashboard');
Route::get('/', [KasirDashboardController::class, 'index'])->name('index');

// POS System (Menggunakan PosController)
Route::prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::post('/store', [PosController::class, 'store'])->name('store');
});

// Transaction Management
Route::prefix('transactions')->name('transactions.')->group(function () {
    Route::get('/', [TransactionController::class, 'index'])->name('index');
    Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
    
    // RUTE BARU UNTUK EDIT
    Route::get('/{transaction}/edit', [TransactionController::class, 'edit'])->name('edit');
    Route::put('/{transaction}', [TransactionController::class, 'update'])->name('update');
});

// Customer Management
Route::resource('customers', CustomerController::class)->except(['edit', 'update', 'destroy']);


// Product Lookup
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [KasirProductController::class, 'index'])->name('index');
    Route::get('/search', [KasirProductController::class, 'search'])->name('search');
    Route::get('/{product}', [KasirProductController::class, 'show'])->name('show');
});

// Reports
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [KasirReportController::class, 'index'])->name('index');
    Route::get('/sales', [KasirReportController::class, 'sales'])->name('sales');
});