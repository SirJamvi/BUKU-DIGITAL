{{-- resources/views/admin/products/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Manajemen Produk')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Produk</li>
@endsection

@section('content')
    <x-card>
        @slot('title')
            Daftar Semua Produk
        @endslot

        @slot('headerActions')
            <x-button href="{{ route('admin.products.create') }}" variant="primary">
                <i class="fas fa-plus me-2"></i>Tambah Produk Baru
            </x-button>
        @endslot

        @include('components.alert')

        <x-table>
            @slot('thead')
                <tr>
                    <th>No</th>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th>Harga Jual</th>
                    <th>Harga Pokok</th>
                    <th>Status</th>
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
                    <td>{{ $product->category->name ?? 'Tidak ada kategori' }}</td>
                    <td>Rp {{ number_format($product->base_price, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($product->cost_price, 0, ',', '.') }}</td>
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
                    <td class="text-center">
                        <x-button href="{{ route('admin.products.show', $product->id) }}" variant="info" class="btn-sm">
                            <i class="fas fa-eye"></i>
                        </x-button>
                        <x-button href="{{ route('admin.products.edit', $product->id) }}" variant="warning" class="btn-sm">
                            <i class="fas fa-edit"></i>
                        </x-button>
                        <x-button type="button" variant="danger" class="btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $product->id }}">
                            <i class="fas fa-trash"></i>
                        </x-button>

                        {{-- Modal Konfirmasi Hapus --}}
                        <x-modal id="deleteModal-{{ $product->id }}" title="Konfirmasi Hapus Produk">
                            <p>Apakah Anda yakin ingin menghapus produk <strong>{{ $product->name }}</strong>? Tindakan ini tidak dapat dibatalkan.</p>
                            @slot('footer')
                                <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="button" variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                                    <x-button type="submit" variant="danger">Ya, Hapus</x-button>
                                </form>
                            @endslot
                        </x-modal>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Tidak ada data produk yang ditemukan.</td>
                </tr>
            @endforelse
        </x-table>
        
        @if ($products->hasPages())
            <div class="mt-3">
                {{ $products->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection