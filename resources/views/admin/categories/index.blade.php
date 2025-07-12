@extends('admin.layouts.app')

@section('title', 'Manajemen Kategori Produk')
@section('breadcrumb')
    <li class="breadcrumb-item active">Kategori Produk</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title">Daftar Kategori Produk</h5>
                @permission('categories.create')
                    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tambah Kategori
                    </a>
                @endpermission
            </div>
        </div>
        <div class="card-body">
            {{-- Pesan Sukses atau Error --}}
            @include('admin.components.alert')

            {{-- Fitur Pencarian --}}
            <form action="{{ route('admin.categories.index') }}" method="GET" class="mb-3">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Cari kategori..." value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit">Cari</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Nama Kategori</th>
                            <th>Kategori Induk</th>
                            <th>Jumlah Produk</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>{{ $loop->iteration + $categories->firstItem() - 1 }}</td>
                                <td>{{ $category->name }}</td>
                                <td>{{ $category->parent->name ?? 'Tidak ada' }}</td>
                                <td>{{ $category->products_count }}</td>
                                <td>
                                    <span class="badge {{ $category->is_active ? 'bg-success' : 'bg-danger' }}">
                                        {{ $category->is_active ? 'Aktif' : 'Non-Aktif' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @permission('categories.update')
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    @endpermission
                                    @permission('categories.delete')
                                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    @endpermission
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">Tidak ada data kategori.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginasi --}}
            <div class="d-flex justify-content-center">
                {{ $categories->links() }}
            </div>
        </div>
    </div>
@endsection