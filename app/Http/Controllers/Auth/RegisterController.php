<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
        // Pemanggilan middleware dihapus dari sini
    }

    /**
     * Menampilkan form registrasi.
     */
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    /**
     * Menangani permintaan registrasi pengguna baru.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        try {
            $user = $this->authService->createUser($request->validated(), 'kasir');
            Auth::login($user);
            return redirect()->route('kasir.dashboard');
        } catch (\Exception $e) {
            logger()->error('Error during public registration: ' . $e->getMessage());
            return back()->with('error', 'Gagal melakukan registrasi. Silakan coba lagi.')->withInput();
        }
    }
}