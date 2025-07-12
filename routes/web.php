<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| File ini HANYA untuk rute publik dan otentikasi.
| Rute Admin ada di routes/admin.php
| Rute Kasir ada di routes/kasir.php
|
*/

// 1. Rute Halaman Utama (Landing Page) untuk tamu
Route::get('/', function () {
    return view('welcome');
})->middleware('guest')->name('welcome');

// 2. Rute Otentikasi
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register')->middleware('guest');
Route::post('register', [RegisterController::class, 'register']);

Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request')->middleware('guest');
Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email')->middleware('guest');
Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset')->middleware('guest');
Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update')->middleware('guest');


// 3. Rute Redirect setelah login
Route::middleware('auth')->group(function() {
    Route::get('/redirect-dashboard', function() {
        $user = Auth::user();
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('kasir.dashboard');
    })->name('redirect.dashboard');
});

// Grup rute Kasir yang sebelumnya ada di sini TELAH DIHAPUS karena sudah ditangani oleh routes/kasir.php