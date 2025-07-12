{{-- resources/views/admin/products/create.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Tambah Produk Baru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">Produk</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah Baru</li>
@endsection

@section('content')
    <x-card title="Formulir Tambah Produk">
        <form action="{{ route('admin.products.store') }}" method="POST">
            @csrf
            <div class="row">
                {{-- Kolom Kiri --}}
                <div class="col-md-8">
                    <x-input name="name" label="Nama Produk" placeholder="Masukkan nama produk" required />
                    <x-input type="textarea" name="description" label="Deskripsi Produk" placeholder="Masukkan deskripsi singkat produk" />
                    <div class="row">
                        <div class="col-md-6">
                             <x-input type="number" name="base_price" label="Harga Jual (Rp)" placeholder="Contoh: 15000" required />
                        </div>
                        <div class="col-md-6">
                             <x-input type="number" name="cost_price" label="Harga Pokok (Rp)" placeholder="Contoh: 10000" required />
                        </div>
                    </div>
                     <div class="row">
                        <div class="col-md-6">
                            <x-input type="number" name="initial_stock" label="Stok Awal" placeholder="Jumlah stok saat produk dibuat" value="0" />
                        </div>
                        <div class="col-md-6">
                           <x-input type="number" name="min_stock" label="Stok Minimum" placeholder="Batas stok untuk notifikasi" value="10" />
                        </div>
                    </div>
                </div>

                {{-- Kolom Kanan --}}
                <div class="col-md-4">
                    <x-select name="category_id" label="Kategori Produk" :options="$categories->pluck('name', 'id')" required />
                    <x-input name="sku" label="SKU (Stock Keeping Unit)" placeholder="Kode unik produk" required />
                    <x-input name="unit" label="Satuan" placeholder="Contoh: Porsi, Gelas, Bungkus" required />
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" checked>
                        <label class="form-check-label" for="isActive">Produk Aktif (dapat dijual)</label>
                    </div>
                     <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured" value="1">
                        <label class="form-check-label" for="isFeatured">Produk Unggulan</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <x-button href="{{ route('admin.products.index') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary"><i class="fas fa-save me-2"></i>Simpan Produk</x-button>
            </div>
        </form>
    </x-card>
@endsection