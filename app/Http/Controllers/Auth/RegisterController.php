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
    }

    /**
     * Menampilkan form registrasi.
     */
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    /**
     * Menangani permintaan registrasi pengguna dan bisnis baru.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        try {
            // Pastikan validasi di RegisterRequest sudah menyertakan 'business_name'
            $validatedData = $request->validated();
            $validatedData['business_name'] = $request->input('business_name');

            $user = $this->authService->createUserAndBusiness($validatedData);
            Auth::login($user);

            return redirect()->route('admin.dashboard');
        } catch (\Exception $e) {
            logger()->error('Error during public registration: ' . $e->getMessage());
            return back()
                ->with('error', 'Gagal melakukan registrasi. Silakan coba lagi.')
                ->withInput();
        }
    }
}
