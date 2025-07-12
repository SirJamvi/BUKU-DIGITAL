{{-- resources/views/kasir/customers/create.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Tambah Pelanggan Baru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('kasir.customers.index') }}">Pelanggan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah Baru</li>
@endsection

@section('content')
    <x-card title="Formulir Tambah Pelanggan">
        <form action="{{ route('kasir.customers.store') }}" method="POST">
            @csrf
            
            <x-input name="name" label="Nama Lengkap Pelanggan" placeholder="Masukkan nama pelanggan" required />
            <x-input type="tel" name="phone" label="Nomor Telepon" placeholder="Contoh: 08123456789" />
            <x-input type="email" name="email" label="Alamat Email (Opsional)" placeholder="email@pelanggan.com" />
            <x-input type="textarea" name="address" label="Alamat (Opsional)" placeholder="Masukkan alamat lengkap pelanggan" />

            <div class="d-flex justify-content-end mt-3">
                <x-button href="{{ route('kasir.customers.index') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary" style="background-color: var(--kasir-accent); border-color: var(--kasir-accent);">
                    <i class="fas fa-save me-2"></i>Simpan Pelanggan
                </x-button>
            </div>
        </form>
    </x-card>
@endsection