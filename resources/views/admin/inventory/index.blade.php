{{-- resources/views/admin/inventory/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Manajemen Inventaris')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Inventaris</li>
@endsection

@section('content')
    <x-card>
        @slot('title')
            Daftar Stok Produk
        @endslot
        @slot('headerActions')
            <div class="d-flex">
                <x-button href="{{ route('admin.inventory.add_stock') }}" variant="primary" class="me-2">
                    <i class="fas fa-plus me-2"></i>Tambah Stok
                </x-button>
                <x-button href="{{ route('admin.inventory.stock-movements') }}" variant="info" class="me-2">
                    <i class="fas fa-history me-2"></i>Riwayat Pergerakan
                </x-button>
                <x-button href="{{ route('admin.inventory.stock-opname') }}" variant="success">
                    <i class="fas fa-tasks me-2"></i>Stock Opname
                </x-button>
            </div>
        @endslot

        @include('components.alert')

        <x-table>
            @slot('thead')
                <tr>
                    <th>No</th>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th>Stok Saat Ini</th>
                    <th>Stok Minimum</th>
                    <th>Status</th>
                    <th>Terakhir Diperbarui</th>
                </tr>
            @endslot

            @forelse ($inventory as $item)
                <tr>
                    <td>{{ $inventory->firstItem() + $loop->index }}</td>
                    <td>
                        <strong>{{ $item->product->name ?? 'Produk Dihapus' }}</strong>
                        <br>
                        <small class="text-muted">SKU: {{ $item->product->sku ?? 'N/A' }}</small>
                    </td>
                    <td>{{ $item->product->category->name ?? 'N/A' }}</td>
                    <td class="fs-5 fw-bold text-center">{{ $item->current_stock }}</td>
                    <td class="text-center">{{ $item->min_stock }}</td>
                    <td>
                        @if ($item->current_stock <= 0)
                            <span class="badge bg-danger">Stok Habis</span>
                        @elseif ($item->current_stock <= $item->min_stock)
                            <span class="badge bg-warning text-dark">Stok Menipis</span>
                        @else
                            <span class="badge bg-success">Tersedia</span>
                        @endif
                    </td>
                    <td>{{ $item->updated_at->isoFormat('D MMM YYYY, HH:mm') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Data inventaris tidak ditemukan.</td>
                </tr>
            @endforelse
        </x-table>

        {{-- Pagination --}}
        @if ($inventory->hasPages())
            <div class="mt-3">
                {{ $inventory->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection