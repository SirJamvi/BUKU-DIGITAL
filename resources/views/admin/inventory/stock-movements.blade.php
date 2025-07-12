{{-- resources/views/admin/inventory/stock-movements.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Riwayat Pergerakan Stok')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventaris</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pergerakan Stok</li>
@endsection

@section('content')
    <x-card title="Riwayat Semua Pergerakan Stok">
        @slot('headerActions')
             <x-button href="{{ route('admin.inventory.index') }}" variant="secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Inventaris
            </x-button>
        @endslot
        
        <x-table>
            @slot('thead')
                <tr>
                    <th>Tanggal</th>
                    <th>Produk</th>
                    <th>Tipe</th>
                    <th>Jumlah</th>
                    <th>Catatan</th>
                    <th>Oleh</th>
                </tr>
            @endslot

            @forelse ($movements as $movement)
                <tr>
                    <td>{{ $movement->created_at->isoFormat('D MMM YYYY, HH:mm') }}</td>
                    <td>
                        <strong>{{ $movement->product->name ?? 'Produk Dihapus' }}</strong>
                        <br>
                        <small class="text-muted">SKU: {{ $movement->product->sku ?? 'N/A' }}</small>
                    </td>
                    <td>
                        @php
                            $badgeClass = match($movement->type) {
                                'in', 'purchase' => 'bg-success',
                                'out', 'sale' => 'bg-danger',
                                'adjustment' => 'bg-info',
                                'waste' => 'bg-warning text-dark',
                                default => 'bg-secondary'
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ ucfirst($movement->type) }}</span>
                    </td>
                    <td class="fw-bold text-center">
                        {{ ($movement->quantity > 0 && $movement->type != 'out') ? '+' : '' }}{{ $movement->quantity }}
                    </td>
                    <td>{{ $movement->notes ?? '-' }}</td>
                    <td>{{ $movement->createdBy->name ?? 'Sistem' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada riwayat pergerakan stok.</td>
                </tr>
            @endforelse
        </x-table>

        {{-- Pagination --}}
        @if ($movements->hasPages())
            <div class="mt-3">
                {{ $movements->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection