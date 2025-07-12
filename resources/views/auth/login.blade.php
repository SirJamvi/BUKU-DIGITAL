{{-- resources/views/auth/login.blade.php --}}
@extends('auth.guest')

@section('title', 'Login')

@section('content')
<x-card>
    <h5 class="card-title text-center mb-4">Silakan Login</h5>

    @include('components.alert')

    {{-- Menampilkan error validasi email/password --}}
    @if ($errors->has('email') || $errors->has('password'))
    <div class="alert alert-danger">
        Email atau kata sandi yang Anda masukkan salah.
    </div>
    @endif

    <form action="{{ route('login') }}" method="POST">
        @csrf

        <x-input type="email" name="email" label="Alamat Email" placeholder="email@anda.com" required />
        <x-input type="password" name="password" label="Kata Sandi" placeholder="Masukkan kata sandi" required />

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">
                    Ingat Saya
                </label>
            </div>
            @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}">Lupa Kata Sandi?</a>
            @endif
        </div>

        <div class="d-grid">
            <x-button type="submit" variant="primary">Login</x-button>
        </div>
    </form>
</x-card>
@endsection