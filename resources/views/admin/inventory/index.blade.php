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

        @if(app(\App\Support\BranchContext::class)->isConsolidated() || session('active_branch_id') === 'all')
            <div class="alert alert-info d-flex align-items-center mb-3 py-2 px-3">
                <i class="fas fa-info-circle fs-5 me-2"></i>
                <div>
                    <strong>Mode Pusat (Konsolidasi):</strong> Menampilkan akumulasi total stok dari seluruh cabang aktif. 
                    Untuk menambah stok fisik atau stock opname, silakan pilih cabang tujuan di dropdown <em>Pindah Cabang</em> di bagian header.
                </div>
            </div>
        @endif

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