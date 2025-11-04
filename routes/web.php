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
// ... (rute auth lainnya) ...


// ====================================================================
// PERBAIKAN DI SINI: Pindahkan semua rute yang butuh login ke dalam grup ini
// ====================================================================
Route::middleware('auth')->group(function() {
    
    // 3. Rute Redirect setelah login
    Route::get('/redirect-dashboard', function() {
        $user = Auth::user();
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('kasir.dashboard');
    })->name('redirect.dashboard');

    // 4. MUAT RUTE ADMIN DI SINI (dengan middleware 'admin')
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        require __DIR__.'/admin.php';
    });

    // 5. MUAT RUTE KASIR DI SINI (dengan middleware 'kasir')
    Route::middleware('kasir')->prefix('kasir')->name('kasir.')->group(function () {
        require __DIR__.'/kasir.php';
    });
    
});