{{-- resources/views/auth/forgot-password.blade.php --}}
@extends('auth.guest')

@section('title', 'Lupa Kata Sandi')

@section('content')
    <h5 class="card-title">Lupa Kata Sandi</h5>
    
    <div class="text-center mb-4">
        <div class="forgot-password-icon">
            <i class="fas fa-lock fa-3x" style="color: var(--luxury-gold); opacity: 0.8;"></i>
        </div>
    </div>

    <p class="text-muted text-center mb-4">
        Lupa kata sandi Anda? Tidak masalah. Masukkan alamat email Anda dan kami akan mengirimkan link untuk mengatur ulang kata sandi Anda.
    </p>

    @if (session('status'))
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        {{ session('status') }}
    </div>
    @endif

    @include('components.alert')

    <form action="{{ route('password.email') }}" method="POST">
        @csrf

        <x-input 
            type="email" 
            name="email" 
            label="Alamat Email" 
            placeholder="Masukkan alamat email Anda"
            required 
        />

        <div class="d-grid mb-3">
            <x-button type="submit" variant="primary">
                <i class="fas fa-paper-plane me-2"></i>
                Kirim Link Reset
            </x-button>
        </div>
    </form>

    <div class="text-center">
        <p class="text-muted">
            Ingat kata sandi Anda? 
            <a href="{{ route('login') }}">
                <i class="fas fa-arrow-left me-1"></i>
                Kembali ke Login
            </a>
        </p>
    </div>

    {{-- Help Section --}}
    <div class="text-center mt-4">
        <div class="help-section">
            <p class="text-muted mb-2" style="font-size: 0.9rem;">
                <i class="fas fa-info-circle me-1"></i>
                Bantuan
            </p>
            <ul class="list-unstyled text-muted" style="font-size: 0.85rem;">
                <li>• Periksa folder spam/junk email Anda</li>
                <li>• Link reset berlaku selama 60 menit</li>
                <li>• Pastikan email yang dimasukkan sudah terdaftar</li>
            </ul>
        </div>
    </div>
@endsection