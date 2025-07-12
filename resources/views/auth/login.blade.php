{{-- resources/views/auth/login.blade.php --}}
@extends('auth.guest')

@section('title', 'Login')

@section('content')
    <h5 class="card-title">Silakan Login</h5>

    @include('components.alert')

    {{-- Menampilkan error validasi email/password --}}
    @if ($errors->has('email') || $errors->has('password'))
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Email atau kata sandi yang Anda masukkan salah.
    </div>
    @endif

    <form action="{{ route('login') }}" method="POST">
        @csrf

        <x-input 
            type="email" 
            name="email" 
            label="Alamat Email" 
            placeholder="email@anda.com" 
            required 
        />
        
        <x-input 
            type="password" 
            name="password" 
            label="Kata Sandi" 
            placeholder="Masukkan kata sandi" 
            required 
        />

        <div class="auth-options">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">
                    Ingat Saya
                </label>
            </div>
            @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}">
                <i class="fas fa-key me-1"></i>
                Lupa Kata Sandi?
            </a>
            @endif
        </div>

        <div class="d-grid mb-3">
            <x-button type="submit" variant="primary">
                <i class="fas fa-sign-in-alt me-2"></i>
                Login
            </x-button>
        </div>
    </form>

    <div class="text-center">
        <p class="text-muted">
            Belum punya akun? 
            <a href="{{ route('register') }}">
                <i class="fas fa-user-plus me-1"></i>
                Daftar di sini
            </a>
        </p>
    </div>
@endsection