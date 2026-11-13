@extends('admin.layouts.app')

@section('title', 'Manajemen Cabang')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Manajemen Cabang</li>
@endsection

@section('content')
    <x-card>
        @slot('title')
            Daftar Cabang Bisnis
        @endslot

        @slot('headerActions')
            <x-button href="{{ route('admin.branches.create') }}" variant="primary" style="background-color: var(--admin-accent); border-color: var(--admin-accent);">
                <i class="fas fa-plus me-2"></i> Tambah Cabang
            </x-button>
        @endslot

        @include('components.alert')

        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Nama & Kode Cabang</th>
                        <th>Penanggung Jawab</th>
                        <th>Kontak & Alamat</th>
                        <th class="text-center">Produk Terdata</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 180px;">Aksi</th>
                    </tr>
                @endslot

                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>{{ $loop->iteration + $branches->firstItem() - 1 }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div>
                                        <span class="fw-bold fs-6">{{ $branch->name }}</span>
                                        <div class="d-flex align-items-center gap-1 mt-1">
                                            <span class="badge bg-dark font-monospace">{{ $branch->code }}</span>
                                            @if($branch->is_main)
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-crown me-1"></i>Pusat
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($branch->manager)
                                    <div class="fw-medium">{{ $branch->manager->name }}</div>
                                    <small class="text-muted">{{ $branch->manager->email }}</small>
                                @else
                                    <span class="text-muted fst-italic">— Belum Ditentukan —</span>
                                @endif
                            </td>
                            <td>
                                <div><i class="fas fa-phone-alt me-1 text-muted small"></i> {{ $branch->phone ?? '—' }}</div>
                                <small class="text-muted d-block text-truncate" style="max-width: 250px;" title="{{ $branch->address }}">
                                    <i class="fas fa-map-marker-alt me-1 small"></i> {{ $branch->address ?? '—' }}
                                </small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info text-dark">
                                    <i class="fas fa-boxes me-1"></i>{{ $branch->inventories_count }} Produk
                                </span>
                            </td>
                            <td class="text-center">
                                @if($branch->is_active)
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Aktif
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-times-circle me-1"></i>Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-sm btn-outline-warning" title="Edit Cabang">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>

                                    @if(!$branch->is_main)
                                        <form action="{{ route('admin.branches.toggle-active', $branch) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Apakah Anda yakin ingin {{ $branch->is_active ? 'menonaktifkan' : 'mengaktifkan' }} cabang ini?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $branch->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" 
                                                    title="{{ $branch->is_active ? 'Nonaktifkan Cabang' : 'Aktifkan Cabang' }}">
                                                <i class="fas {{ $branch->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                                {{ $branch->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-store-slash fa-2x mb-2 d-block"></i>
                                Belum ada data cabang.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-table>
        </div>

        @if ($branches->hasPages())
            <div class="mt-3">
                {{ $branches->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection

