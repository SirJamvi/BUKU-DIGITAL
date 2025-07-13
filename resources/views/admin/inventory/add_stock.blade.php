{{-- resources/views/admin/inventory/add_stock.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Tambah Stok Produk')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventaris</a></li>
    <li class="breadcrumb-item active">Tambah Stok</li>
@endsection

@section('content')
    <x-card title="Formulir Tambah Stok Masuk">
        <p class="text-muted">Gunakan formulir ini untuk mencatat penambahan stok baru, misalnya penerimaan barang dari supplier.</p>
        <form action="{{ route('admin.inventory.store_stock') }}" method="POST">
            @csrf

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <x-select 
                        name="product_id" 
                        label="Pilih Produk" 
                        :options="$products->pluck('name', 'id')" 
                        required 
                    />
                </div>
                <div class="col-md-6">
                    <x-input 
                        type="number"
                        name="quantity"
                        label="Jumlah Stok Masuk"
                        placeholder="Contoh: 50"
                        required
                        min="1"
                    />
                </div>
            </div>
            <x-input 
                type="textarea"
                name="notes"
                label="Catatan (Opsional)"
                placeholder="Contoh: Penerimaan barang dari Supplier Naga Sakti, PO #123"
            />
            
            <div class="d-flex justify-content-end mt-4">
                <x-button href="{{ route('admin.inventory.index') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary">
                    <i class="fas fa-plus-circle me-2"></i>Tambah Stok
                </x-button>
            </div>
        </form>
    </x-card>
@endsection