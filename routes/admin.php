<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\FinancialController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\BusinessIntelligenceController;
use App\Http\Controllers\Admin\FundAllocationController;

// TIDAK PERLU Route::middleware(...)->group(...) lagi di sini.
// RouteServiceProvider sudah menanganinya.

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/', [DashboardController::class, 'index'])->name('index'); // Fallback jika /admin diakses

// Categories, Products, Users Management
Route::resource('categories', CategoryController::class);
Route::resource('products', ProductController::class);
Route::resource('users', UserController::class);

// Financial Management
Route::prefix('financial')->name('financial.')->group(function () {
    Route::get('/', [FinancialController::class, 'index'])->name('index');
    Route::get('/cash-flow', [FinancialController::class, 'cashFlow'])->name('cash-flow');
    Route::get('/expenses', [FinancialController::class, 'expenses'])->name('expenses');
    Route::get('/roi-analysis', [FinancialController::class, 'roiAnalysis'])->name('roi-analysis');
});

// Inventory Management
Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('index');
    Route::get('/stock-movements', [InventoryController::class, 'stockMovements'])->name('stock-movements');
    Route::get('/stock-opname', [InventoryController::class, 'stockOpname'])->name('stock-opname');
    Route::post('/stock-opname', [InventoryController::class, 'processStockOpname'])->name('process-stock-opname');
});

// Reports
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
    Route::get('/financial', [ReportController::class, 'financial'])->name('financial');
    Route::get('/inventory', [ReportController::class, 'inventory'])->name('inventory');
});

// Settings
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::get('/profile', [SettingsController::class, 'profile'])->name('profile');
    Route::put('/profile', [SettingsController::class, 'updateProfile'])->name('update-profile');
    Route::get('/system', [SettingsController::class, 'system'])->name('system');
    Route::put('/system', [SettingsController::class, 'updateSystem'])->name('update-system');
});

// Business Intelligence
Route::get('/business-intelligence', [BusinessIntelligenceController::class, 'index'])->name('bi.index');

// Fund Allocation
Route::prefix('fund-allocation')->name('fund-allocation.')->group(function () {
    Route::get('/', [FundAllocationController::class, 'index'])->name('index');
    Route::get('/settings', [FundAllocationController::class, 'settings'])->name('settings');
    Route::put('/settings', [FundAllocationController::class, 'updateSettings'])->name('update-settings');
    Route::get('/history', [FundAllocationController::class, 'history'])->name('history');
});