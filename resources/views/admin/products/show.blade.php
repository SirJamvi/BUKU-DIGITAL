{{-- resources/views/admin/products/show.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Detail Produk: ' . $product->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">Produk</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <x-card title="Informasi Produk">
                @slot('headerActions')
                    <x-button href="{{ route('admin.products.edit', $product->id) }}" variant="warning">
                        <i class="fas fa-edit me-2"></i>Edit Produk
                    </x-button>
                @endslot
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
                        <td>Rp {{ number_format($product->base_price, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Harga Pokok</th>
                        <td>Rp {{ number_format($product->cost_price, 0, ',', '.') }}</td>
                    </tr>
                     <tr>
                        <th>Margin Keuntungan</th>
                        <td>Rp {{ number_format($product->base_price - $product->cost_price, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Satuan</th>
                        <td>{{ $product->unit }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                             @if($product->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Nonaktif</span>
                            @endif
                            @if($product->is_featured)
                                <span class="badge bg-info">Unggulan</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </x-card>
        </div>
        <div class="col-md-4">
             <x-card title="Informasi Inventaris" class="h-100">
                <div class="text-center">
                    <p class="text-muted mb-1">Stok Saat Ini</p>
                    <h1 class="display-4 fw-bold">{{ $product->inventory->current_stock ?? 0 }}</h1>
                    <hr>
                     <p class="text-muted mb-1">Stok Minimum</p>
                    <h4 class="fw-normal">{{ $product->inventory->min_stock ?? 0 }}</h4>
                    <a href="{{ route('admin.inventory.stock-movements') }}?product_id={{ $product->id }}">Lihat Riwayat Pergerakan Stok</a>
                </div>
             </x-card>
        </div>
    </div>
@endsection