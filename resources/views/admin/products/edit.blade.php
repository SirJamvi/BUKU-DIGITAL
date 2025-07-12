{{-- resources/views/admin/products/edit.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Edit Produk: ' . $product->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">Produk</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
    <x-card title="Formulir Edit Produk">
        <form action="{{ route('admin.products.update', $product->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                {{-- Kolom Kiri --}}
                <div class="col-md-8">
                    <x-input name="name" label="Nama Produk" :value="$product->name" required />
                    <x-input type="textarea" name="description" label="Deskripsi Produk" :value="$product->description" />
                    <div class="row">
                        <div class="col-md-6">
                             <x-input type="number" name="base_price" label="Harga Jual (Rp)" :value="$product->base_price" required />
                        </div>
                        <div class="col-md-6">
                             <x-input type="number" name="cost_price" label="Harga Pokok (Rp)" :value="$product->cost_price" required />
                        </div>
                    </div>
                    {{-- Stok tidak di-edit di sini, melainkan melalui modul Inventaris --}}
                </div>

                {{-- Kolom Kanan --}}
                <div class="col-md-4">
                    <x-select name="category_id" label="Kategori Produk" :options="$categories->pluck('name', 'id')" :selected="$product->category_id" required />
                    <x-input name="sku" label="SKU (Stock Keeping Unit)" :value="$product->sku" required />
                    <x-input name="unit" label="Satuan" :value="$product->unit" required />
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" @if($product->is_active) checked @endif>
                        <label class="form-check-label" for="isActive">Produk Aktif (dapat dijual)</label>
                    </div>
                     <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured" value="1" @if($product->is_featured) checked @endif>
                        <label class="form-check-label" for="isFeatured">Produk Unggulan</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <x-button href="{{ route('admin.products.index') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary"><i class="fas fa-save me-2"></i>Simpan Perubahan</x-button>
            </div>
        </form>
    </x-card>
@endsection