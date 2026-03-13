{{-- resources/views/auth/register.blade.php --}}
@extends('auth.guest')

@section('title', 'Register')

@section('content')
    <h5 class="card-title">Buat Akun Baru</h5>

    @include('components.alert')

    <form action="{{ route('register') }}" method="POST">
        @csrf

        <x-input name="name" label="Nama Lengkap" placeholder="Masukkan nama lengkap Anda" required />
        <x-input type="email" name="email" label="Alamat Email" placeholder="email@anda.com" required />
        <x-input name="business_name" label="Nama Bisnis / Toko" placeholder="Contoh: Toko Naga Dingin" required />

        {{-- KODE BARU: Password --}}
        <div class="mb-3">
            <label for="reg-password" class="form-label">Kata Sandi</label>
            <div class="input-group">
                <input type="password" name="password" id="reg-password" class="form-control" placeholder="Minimal 8 karakter" required>
                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="reg-password" style="border-color: #ced4da;">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        {{-- KODE BARU: Konfirmasi Password --}}
        <div class="mb-3">
            <label for="reg-password-confirm" class="form-label">Konfirmasi Kata Sandi</label>
            <div class="input-group">
                <input type="password" name="password_confirmation" id="reg-password-confirm" class="form-control" placeholder="Ulangi kata sandi" required>
                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="reg-password-confirm" style="border-color: #ced4da;">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        {{-- SELESAI KODE BARU --}}

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
            <a href="#" class="text-decoration-underline">Syarat & Ketentuan</a> dan 
            <a href="#" class="text-decoration-underline">Kebijakan Privasi</a> kami.
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