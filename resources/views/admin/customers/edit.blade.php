@extends('admin.layouts.app')

@section('title', 'Edit Pelanggan')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Pelanggan</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
    <x-card title="Formulir Edit Pelanggan">
        <form action="{{ route('admin.customers.update', $customer->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <x-input name="name" label="Nama Pelanggan" :value="$customer->name" required />
            <x-input type="tel" name="phone" label="Nomor Telepon" :value="$customer->phone" />
            <x-input type="email" name="email" label="Alamat Email" :value="$customer->email" />
            <x-input type="textarea" name="address" label="Alamat" :value="$customer->address" />
            
            <x-select 
                name="status" 
                label="Status"
                :options="['active' => 'Aktif', 'inactive' => 'Tidak Aktif']"
                :selected="$customer->status"
                required 
            />

            <div class="d-flex justify-content-end mt-4">
                <x-button href="{{ route('admin.customers.index') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary">Simpan Perubahan</x-button>
            </div>
        </form>
    </x-card>
@endsection