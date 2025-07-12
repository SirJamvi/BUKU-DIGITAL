{{-- resources/views/auth/reset-password.blade.php --}}
@extends('auth.guest')

@section('title', 'Reset Kata Sandi')

@section('content')
<x-card>
    <h5 class="card-title text-center mb-4">Atur Ulang Kata Sandi</h5>

    @include('components.alert')

    <form action="{{ route('password.update') }}" method="POST">
        @csrf

        {{-- Token dan Email (tersembunyi) --}}
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-input type="email" name="email" label="Alamat Email" :value="$request->email" required readonly />

        {{-- Input Kata Sandi Baru --}}
        <x-input type="password" name="password" label="Kata Sandi Baru" required />
        <x-input type="password" name="password_confirmation" label="Konfirmasi Kata Sandi Baru" required />

        <div class="d-grid mt-3">
            <x-button type="submit" variant="primary">Reset Kata Sandi</x-button>
        </div>
    </form>
</x-card>
@endsection