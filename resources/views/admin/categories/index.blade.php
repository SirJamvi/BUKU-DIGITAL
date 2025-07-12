{{-- resources/views/admin/categories/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Manajemen Kategori')
@section('breadcrumb')
    <li class="breadcrumb-item active">Kategori Produk</li>
@endsection

@section('content')
    <x-card>
        @slot('title')
            Daftar Kategori Produk
        @endslot

        @slot('headerActions')
            @permission('categories.create')
                <x-button href="{{ route('admin.categories.create') }}" variant="primary" style="background-color: var(--admin-accent); border-color: var(--admin-accent);">
                    <i class="fas fa-plus me-2"></i> Tambah Kategori
                </x-button>
            @endpermission
        @endslot

        {{-- INI PERBAIKANNYA: Menggunakan path komponen yang benar --}}
        @include('components.alert')

        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th>#</th>
                        <th>Nama Kategori</th>
                        <th>Kategori Induk</th>
                        <th>Jumlah Produk</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                @endslot
                
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $loop->iteration + $categories->firstItem() - 1 }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->parent->name ?? '—' }}</td>
                            <td>{{ $category->products_count ?? 0 }}</td>
                            <td>
                                <span class="badge {{ $category->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $category->is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @permission('categories.update')
                                    <x-button href="{{ route('admin.categories.edit', $category) }}" variant="warning" class="btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </x-button>
                                @endpermission
                                @permission('categories.delete')
                                    <x-button type="button" variant="danger" class="btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $category->id }}">
                                        <i class="fas fa-trash"></i>
                                    </x-button>
                                @endpermission

                                {{-- Modal Konfirmasi Hapus --}}
                                <x-modal id="deleteModal-{{ $category->id }}" title="Konfirmasi Hapus">
                                    <p>Apakah Anda yakin ingin menghapus kategori <strong>{{ $category->name }}</strong>?</p>
                                    @slot('footer')
                                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST">
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
                            <td colspan="6" class="text-center">Tidak ada data kategori.</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-table>
        </div>

        @if ($categories->hasPages())
            <div class="mt-3 d-flex justify-content-center">
                {{ $categories->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection