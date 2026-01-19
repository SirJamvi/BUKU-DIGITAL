<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // 1. Cek apakah user sudah ada berdasarkan google_id
            $finduser = User::where('google_id', $googleUser->id)->first();

            if($finduser){
                // Login jika user sudah terhubung
                Auth::login($finduser);
                return redirect()->intended('/redirect-dashboard');
            }else{
                // 2. Cek apakah email sudah ada (login manual sebelumnya)
                $existingUser = User::where('email', $googleUser->email)->first();

                if($existingUser) {
                    // Update user lama agar punya google_id
                    $existingUser->update([
                        'google_id' => $googleUser->id,
                        // 'avatar' => $googleUser->avatar // Aktifkan jika sudah buat kolom avatar
                    ]);
                    Auth::login($existingUser);
                } else {
                    // 3. Buat User Baru
                    $newUser = User::create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id'=> $googleUser->id,
                        'password' => Hash::make(Str::random(16)), // Password acak
                        'role' => 'kasir' // <--- DEFAULT ROLE (Ubah jadi 'admin' jika perlu)
                    ]);
                    Auth::login($newUser);
                }

                return redirect()->intended('/redirect-dashboard');
            }

        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'Login Google Gagal. Silakan coba lagi.']);
        }
    }
}