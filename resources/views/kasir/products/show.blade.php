{{-- resources/views/kasir/products/show.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Detail Produk')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('kasir.products.index') }}">Lihat Produk</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail</li>
@endsection

@section('content')
    <x-card title="Detail Produk: {{ $product->name }}">
        @slot('headerActions')
            <x-button href="{{ route('kasir.products.index') }}" variant="secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar
            </x-button>
        @endslot

        <div class="row">
            <div class="col-md-8">
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 30%;">Nama Produk</th>
                        <td>{{ $product->name }}</td>
                    </tr>
                    <tr>
                        <th>SKU</th>
                        <td>{{ $product->sku }}</td>
                    </tr>
                    <tr>
                        <th>Kategori</th>
                        <td>{{ $product->category->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Deskripsi</th>
                        <td>{{ $product->description ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Harga Jual</th>
                        <td class="fw-bold" style="color: var(--kasir-accent);">Rp {{ number_format($product->base_price, 0, ',', '.') }}</td>
                    </tr>
                     <tr>
                        <th>Satuan</th>
                        <td>{{ $product->unit }}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-4">
                 <div class="card text-center" style="background-color: var(--kasir-bg-secondary);">
                     <div class="card-body">
                         <p class="text-muted mb-1">Stok Tersedia</p>
                         <h1 class="display-4 fw-bold" style="color: var(--kasir-accent);">{{ $product->inventory->current_stock ?? 0 }}</h1>
                         <p class="text-muted mb-0">{{ $product->unit }}</p>
                     </div>
                 </div>
            </div>
        </div>
    </x-card>
@endsection