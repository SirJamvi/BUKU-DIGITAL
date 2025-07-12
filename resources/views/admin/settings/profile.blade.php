{{-- resources/views/admin/settings/profile.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Pengaturan Profil')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}">Pengaturan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Profil Saya</li>
@endsection

@section('content')
    <x-card title="Formulir Edit Profil">
        @include('components.alert')

        <form action="{{ route('admin.settings.profile.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-6">
                    <x-input name="name" label="Nama Lengkap" :value="$user->name" placeholder="Masukkan nama lengkap Anda" required />
                </div>
                <div class="col-md-6">
                    <x-input type="email" name="email" label="Alamat Email" :value="$user->email" placeholder="Masukkan alamat email aktif" required />
                </div>
            </div>

            <hr>
            <p class="text-muted">Kosongkan kolom kata sandi jika Anda tidak ingin mengubahnya.</p>
            <div class="row">
                <div class="col-md-6">
                    <x-input type="password" name="password" label="Kata Sandi Baru" placeholder="Masukkan kata sandi baru" />
                </div>
                <div class="col-md-6">
                    <x-input type="password" name="password_confirmation" label="Konfirmasi Kata Sandi Baru" placeholder="Ulangi kata sandi baru" />
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <x-button type="submit" variant="primary"><i class="fas fa-save me-2"></i>Simpan Perubahan</x-button>
            </div>
        </form>
    </x-card>
@endsection