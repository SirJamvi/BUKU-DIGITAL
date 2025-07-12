{{-- resources/views/auth/register.blade.php --}}
@extends('auth.guest')

@section('title', 'Register')

@section('content')
<x-card>
    <h5 class="card-title text-center mb-4">Buat Akun Baru</h5>

    @include('components.alert')

    <form action="{{ route('register') }}" method="POST">
        @csrf

        <x-input name="name" label="Nama Lengkap" placeholder="Masukkan nama Anda" required />
        <x-input type="email" name="email" label="Alamat Email" placeholder="email@anda.com" required />
        <x-input type="password" name="password" label="Kata Sandi" placeholder="Minimal 8 karakter" required />
        <x-input type="password" name="password_confirmation" label="Konfirmasi Kata Sandi" placeholder="Ulangi kata sandi" required />

        <div class="d-grid my-3">
            <x-button type="submit" variant="primary">Register</x-button>
        </div>

        <p class="text-center text-muted">
            Sudah punya akun? <a href="{{ route('login') }}">Login di sini</a>
        </p>
    </form>
</x-card>
@endsection