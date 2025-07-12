{{-- resources/views/kasir/products/index.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Daftar Produk')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Lihat Produk</li>
@endsection

@section('content')
    <x-card>
        @slot('title')
            Daftar Produk Tersedia
        @endslot

        <p class="text-muted">Halaman ini menampilkan semua produk yang aktif dan tersedia untuk dijual. Gunakan sebagai referensi atau untuk melihat detail stok.</p>

        @include('components.alert')

        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th>No</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th class="text-end">Harga</th>
                        <th class="text-center">Stok Saat Ini</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                @endslot

                @forelse ($products as $product)
                    <tr>
                        <td>{{ $products->firstItem() + $loop->index }}</td>
                        <td>
                            <strong>{{ $product->name }}</strong>
                            <br>
                            <small class="text-muted">SKU: {{ $product->sku }}</small>
                        </td>
                        <td>{{ $product->category->name ?? 'N/A' }}</td>
                        <td class="text-end fw-bold" style="color: var(--kasir-accent);">Rp {{ number_format($product->base_price, 0, ',', '.') }}</td>
                        <td class="text-center fw-bold">{{ $product->inventory->current_stock ?? 0 }}</td>
                        <td class="text-center">
                            <x-button href="{{ route('kasir.products.show', $product->id) }}" variant="info" class="btn-sm">
                                <i class="fas fa-eye"></i> Detail
                            </x-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">Tidak ada produk yang tersedia saat ini.</td>
                    </tr>
                @endforelse
            </x-table>
        </div>

        @if ($products->hasPages())
            <div class="mt-3">
                {{ $products->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection