{{-- resources/views/auth/forgot-password.blade.php --}}
@extends('auth.guest')

@section('title', 'Lupa Kata Sandi')

@section('content')
<x-card>
    <p class="text-muted text-center">
        Lupa kata sandi Anda? Tidak masalah. Masukkan alamat email Anda dan kami akan mengirimkan link untuk mengatur ulang kata sandi Anda.
    </p>

    @if (session('status'))
    <div class="alert alert-success">
        {{ session('status') }}
    </div>
    @endif

    @include('components.alert')

    <form action="{{ route('password.email') }}" method="POST">
        @csrf

        <x-input type="email" name="email" label="Alamat Email" required />

        <div class="d-grid mt-3">
            <x-button type="submit" variant="primary">Kirim Link Reset</x-button>
        </div>
    </form>
    <p class="text-center text-muted mt-3">
        <a href="{{ route('login') }}">Kembali ke halaman Login</a>
    </p>
</x-card>
@endsection