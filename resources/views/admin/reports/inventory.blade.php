 
{{-- resources/views/admin/reports/inventory.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Laporan Inventaris')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.reports.index') }}">Laporan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Inventaris</li>
@endsection

@section('content')
    <x-card>
        @slot('title')
            Laporan Status Inventaris per {{ now()->isoFormat('D MMMM YYYY') }}
        @endslot
        @slot('headerActions')
            <x-button variant="secondary"><i class="fas fa-print me-2"></i>Ekspor PDF</x-button>
        @endslot

        <x-table>
            @slot('thead')
                <tr>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th class="text-center">Stok Saat Ini</th>
                    <th class="text-center">Stok Minimum</th>
                    <th>Status Stok</th>
                </tr>
            @endslot
            @forelse ($reportData['inventory'] as $item)
                <tr>
                    <td>{{ $item->product->sku ?? 'N/A' }}</td>
                    <td>{{ $item->product->name ?? 'Produk Dihapus' }}</td>
                    <td>{{ $item->product->category->name ?? 'N/A' }}</td>
                    <td class="text-center fw-bold">{{ $item->current_stock }}</td>
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
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada data inventaris yang ditemukan.</td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
@endsection