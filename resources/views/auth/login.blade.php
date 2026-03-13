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
        
        {{-- KODE BARU: Field Password dengan Ikon Mata --}}
        <div class="mb-3">
            <label for="login-password" class="form-label">Kata Sandi</label>
            <div class="input-group">
                <input type="password" name="password" id="login-password" class="form-control" placeholder="Masukkan kata sandi" required>
                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="login-password" style="border-color: #ced4da;">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        {{-- SELESAI KODE BARU --}}

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

    <div class="text-center my-3">
        <span class="text-muted" style="font-size: 0.8rem;">ATAU MASUK DENGAN</span>
    </div>

    <div class="d-grid mb-4">
        <a href="{{ route('auth.google') }}" class="btn btn-outline-danger" style="border-radius: 50px;">
            <i class="fab fa-google me-2"></i> Google
        </a>
    </div>

    <div class="text-center">
        <p class="text-muted">
            Belum punya akun? 
            <a href="{{ route('register') }}">
                <i class="fas fa-user-plus me-1"></i>
                Daftar di sini
            </a>
        </p>
    </div>

    {{-- SCRIPT UNTUK TOGGLE MATA --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.toggle-password');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const icon = this.querySelector('i');

                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
        });
    </script>
@endsection