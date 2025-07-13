{{-- resources/views/auth/register.blade.php --}}
@extends('auth.guest')

@section('title', 'Register')

@section('content')
    <h5 class="card-title">Buat Akun Baru</h5>

    @include('components.alert')

    <form action="{{ route('register') }}" method="POST">
        @csrf

        <x-input
            name="name"
            label="Nama Lengkap"
            placeholder="Masukkan nama lengkap Anda"
            required
        />

        <x-input
            type="email"
            name="email"
            label="Alamat Email"
            placeholder="email@anda.com"
            required
        />

        <x-input
            name="business_name"
            label="Nama Bisnis / Toko"
            placeholder="Contoh: Toko Naga Dingin"
            required
        />

        <x-input
            type="password"
            name="password"
            label="Kata Sandi"
            placeholder="Minimal 8 karakter"
            required
        />

        <x-input
            type="password"
            name="password_confirmation"
            label="Konfirmasi Kata Sandi"
            placeholder="Ulangi kata sandi"
            required
        />

        <div class="d-grid mb-3">
            <x-button type="submit" variant="primary">
                <i class="fas fa-user-plus me-2"></i>
                Daftar Sekarang
            </x-button>
        </div>
    </form>

    <div class="text-center">
        <p class="text-muted">
            Sudah punya akun? 
            <a href="{{ route('login') }}">
                <i class="fas fa-sign-in-alt me-1"></i>
                Login di sini
            </a>
        </p>
    </div>

    <div class="text-center mt-4">
        <p class="text-muted" style="font-size: 0.85rem;">
            Dengan mendaftar, Anda menyetujui 
            <a href="#" class="text-decoration-underline">Syarat & Ketentuan</a>
            dan 
            <a href="#" class="text-decoration-underline">Kebijakan Privasi</a> kami.
        </p>
    </div>
@endsection
