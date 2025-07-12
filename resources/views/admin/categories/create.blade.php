{{-- resources/views/admin/categories/create.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Tambah Kategori Baru')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">Kategori Produk</a></li>
    <li class="breadcrumb-item active">Tambah Baru</li>
@endsection

@section('content')
    <x-card title="Formulir Tambah Kategori">
        <form action="{{ route('admin.categories.store') }}" method="POST">
            @csrf
            
            @include('admin.categories._form')
            
            <div class="d-flex justify-content-end mt-4">
                <x-button href="{{ route('admin.categories.index') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary" style="background-color: var(--admin-accent); border-color: var(--admin-accent);">
                    <i class="fas fa-save me-2"></i>Simpan
                </x-button>
            </div>
        </form>
    </x-card>
@endsection