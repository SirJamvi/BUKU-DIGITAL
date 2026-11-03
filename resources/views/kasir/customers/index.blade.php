{{-- resources/views/kasir/customers/index.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Manajemen Pelanggan')

@section('breadcrumb')
<li class="breadcrumb-item active" aria-current="page">Pelanggan</li>
@endsection

@section('content')
<x-card>
    @slot('title')
    Daftar Pelanggan
    @endslot
    @slot('headerActions')
    <x-button href="{{ route('kasir.customers.create') }}" variant="primary" style="background-color: var(--kasir-accent); border-color: var(--kasir-accent);">
        <i class="fas fa-plus me-2"></i>Tambah Pelanggan Baru
    </x-button>
    @endslot

    @include('components.alert')

    {{-- ============================================== --}}
    {{-- FORM SEARCH PELANGGAN --}}
    {{-- ============================================== --}}
    <form action="{{ route('kasir.customers.index') }}" method="GET" class="mb-3">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text"
                name="search"
                class="form-control"
                placeholder="Cari pelanggan berdasarkan nama, telepon, atau email..."
                value="{{ $search ?? '' }}">
            @if(!empty($search))
            <a href="{{ route('kasir.customers.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-times"></i> Reset
            </a>
            @endif
            <button type="submit" class="btn btn-primary" style="background-color: var(--kasir-accent); border-color: var(--kasir-accent);">
                Cari
            </button>
        </div>
    </form>

    @if(!empty($search))
    <p class="text-muted small">
        Menampilkan hasil pencarian untuk: <strong>"{{ $search }}"</strong>
        ({{ $customers->total() }} ditemukan)
    </p>
    @endif

    <div class="table-responsive">
        <x-table>
            @slot('thead')
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Kontak</th>
                <th>Tanggal Bergabung</th>
                <th class="text-center">Aksi</th>
            </tr>
            @endslot

            @forelse ($customers as $customer)
            <tr>
                <td>{{ $customers->firstItem() + $loop->index }}</td>
                <td>{{ $customer->name }}</td>
                <td>
                    @if($customer->phone)
                    <div><i class="fas fa-phone-alt fa-fw text-muted"></i> {{ $customer->phone }}</div>
                    @endif
                    @if(isset($customer->phones) && $customer->phones->where('is_primary', false)->count() > 0)
                    <div class="mt-1">
                        <span class="badge bg-light text-primary border" title="{{ $customer->phones->where('is_primary', false)->pluck('phone')->implode(', ') }}">
                            <i class="fas fa-plus fa-xs"></i> {{ $customer->phones->where('is_primary', false)->count() }} nomor lain
                        </span>
                    </div>
                    @endif
                    @if($customer->email)
                    <div class="text-muted small mt-1"><i class="fas fa-envelope fa-fw"></i> {{ $customer->email }}</div>
                    @endif
                </td>
                <td>{{ \Carbon\Carbon::parse($customer->join_date)->isoFormat('D MMMM YYYY') }}</td>
                <td class="text-center">
                    <div class="d-inline-flex gap-1">
                        <x-button href="{{ route('kasir.customers.show', $customer->id) }}" variant="info" class="btn-sm text-white">
                            <i class="fas fa-eye"></i> Detail
                        </x-button>
                        <x-button href="{{ route('kasir.customers.edit', $customer->id) }}" variant="warning" class="btn-sm text-white">
                            <i class="fas fa-edit"></i> Edit
                        </x-button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">
                    @if(!empty($search))
                    Tidak ada pelanggan yang cocok dengan pencarian "{{ $search }}".
                    @else
                    Belum ada data pelanggan.
                    @endif
                </td>
            </tr>
            @endforelse
        </x-table>
    </div>

    @if ($customers->hasPages())
    <div class="mt-3">
        {{ $customers->links('components.pagination') }}
    </div>
    @endif
</x-card>
@endsection