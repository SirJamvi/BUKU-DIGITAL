{{-- resources/views/kasir/customers/edit.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Edit Pelanggan')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('kasir.customers.index') }}">Pelanggan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
    <x-card title="Formulir Edit Pelanggan">
        <form action="{{ route('kasir.customers.update', $customer->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <x-input name="name" label="Nama Lengkap Pelanggan" :value="$customer->name" placeholder="Masukkan nama pelanggan" required />
            <x-input type="tel" name="phone" label="Nomor Telepon" :value="$customer->phone" placeholder="Contoh: 08123456789" />
            <x-input type="email" name="email" label="Alamat Email (Opsional)" :value="$customer->email" placeholder="email@pelanggan.com" />
            
            <div class="mb-3">
                <label for="address" class="form-label">Alamat (Opsional)</label>
                <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="3" placeholder="Masukkan alamat lengkap pelanggan">{{ old('address', $customer->address) }}</textarea>
                @error('address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <x-select 
                name="status" 
                label="Status Pelanggan"
                :options="['active' => 'Aktif', 'inactive' => 'Tidak Aktif']"
                :selected="old('status', $customer->status)"
            />

            <div class="d-flex justify-content-end mt-4">
                <x-button href="{{ route('kasir.customers.index') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary" style="background-color: var(--kasir-accent); border-color: var(--kasir-accent);">
                    <i class="fas fa-save me-2"></i>Simpan Perubahan
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
