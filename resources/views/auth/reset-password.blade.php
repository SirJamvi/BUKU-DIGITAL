{{-- resources/views/auth/reset-password.blade.php --}}
@extends('auth.guest')

@section('title', 'Reset Kata Sandi')

@section('content')
    <h5 class="card-title">Atur Ulang Kata Sandi</h5>
    
    <div class="text-center mb-4">
        <div class="reset-password-icon">
            <i class="fas fa-key fa-3x" style="color: var(--luxury-gold); opacity: 0.8;"></i>
        </div>
    </div>

    <p class="text-muted text-center mb-4">
        Masukkan kata sandi baru Anda. Pastikan kata sandi yang Anda pilih aman dan mudah diingat.
    </p>

    @include('components.alert')

    <form action="{{ route('password.update') }}" method="POST">
        @csrf

        {{-- Token dan Email (tersembunyi) --}}
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        
        <x-input 
            type="email" 
            name="email" 
            label="Alamat Email" 
            :value="$request->email" 
            required 
            readonly 
        />

        {{-- Input Kata Sandi Baru --}}
        <x-input 
            type="password" 
            name="password" 
            label="Kata Sandi Baru" 
            placeholder="Masukkan kata sandi baru (min. 8 karakter)"
            required 
        />
        
        <x-input 
            type="password" 
            name="password_confirmation" 
            label="Konfirmasi Kata Sandi Baru" 
            placeholder="Ulangi kata sandi baru"
            required 
        />

        <div class="d-grid mb-3">
            <x-button type="submit" variant="primary">
                <i class="fas fa-save me-2"></i>
                Simpan Kata Sandi Baru
            </x-button>
        </div>
    </form>

    {{-- Password Requirements --}}
    <div class="password-requirements mt-4">
        <h6 class="text-muted mb-3">
            <i class="fas fa-shield-alt me-2"></i>
            Persyaratan Kata Sandi:
        </h6>
        <ul class="list-unstyled text-muted" style="font-size: 0.9rem;">
            <li class="mb-2">
                <i class="fas fa-check text-success me-2"></i>
                Minimal 8 karakter
            </li>
            <li class="mb-2">
                <i class="fas fa-check text-success me-2"></i>
                Kombinasi huruf dan angka
            </li>
            <li class="mb-2">
                <i class="fas fa-check text-success me-2"></i>
                Hindari informasi pribadi
            </li>
            <li class="mb-2">
                <i class="fas fa-check text-success me-2"></i>
                Berbeda dari kata sandi sebelumnya
            </li>
        </ul>
    </div>

    <div class="text-center mt-4">
        <p class="text-muted">
            Sudah selesai? 
            <a href="{{ route('login') }}">
                <i class="fas fa-sign-in-alt me-1"></i>
                Login dengan kata sandi baru
            </a>
        </p>
    </div>
@endsection