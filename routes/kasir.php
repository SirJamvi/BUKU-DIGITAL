<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Kasir\DashboardController as KasirDashboardController;
use App\Http\Controllers\Kasir\TransactionController;
use App\Http\Controllers\Kasir\ProductController as KasirProductController;
use App\Http\Controllers\Kasir\ReportController as KasirReportController;
use App\Http\Controllers\Kasir\PosController;
use App\Http\Controllers\Kasir\CustomerController;
use App\Http\Controllers\Kasir\InventoryController as KasirInventoryController;

// Dashboard
Route::get('/dashboard', [KasirDashboardController::class, 'index'])->name('dashboard');
Route::get('/', [KasirDashboardController::class, 'index'])->name('index');

// POS System
Route::prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::post('/store', [PosController::class, 'store'])->name('store');
    Route::get('/receipt/{transaction}', [PosController::class, 'receipt'])->name('receipt');
});

// Transaction Management
Route::prefix('transactions')->name('transactions.')->group(function () {
    Route::get('/', [TransactionController::class, 'index'])->name('index');
    Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
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

// ✅ SEMUA RUTE INVENTORY (termasuk stock opname) sekarang dalam satu grup
Route::prefix('inventory')->name('inventory.')->group(function () {
    // Input Stok dari Supplier
    Route::get('/add-stock', [KasirInventoryController::class, 'addStockForm'])->name('add_stock');
    Route::post('/add-stock', [KasirInventoryController::class, 'storeStock'])->name('store_stock');

    // Pecah Ball (Konversi)
    Route::get('/break-unit', [KasirInventoryController::class, 'breakUnitForm'])->name('break_unit');
    Route::post('/break-unit', [KasirInventoryController::class, 'processBreakUnit'])->name('process_break_unit');

    // Tutup Shift (Stock Opname)
    Route::get('/stock-opname', [KasirInventoryController::class, 'stockOpnameForm'])->name('stock_opname');
    Route::post('/stock-opname', [KasirInventoryController::class, 'processStockOpname'])->name('process_stock_opname');
});
