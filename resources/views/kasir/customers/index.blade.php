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
                                <i class="fas fa-phone-alt fa-fw text-muted"></i> {{ $customer->phone }}<br>
                            @endif
                            @if($customer->email)
                                <i class="fas fa-envelope fa-fw text-muted"></i> {{ $customer->email }}
                            @endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($customer->join_date)->isoFormat('D MMMM YYYY') }}</td>
                        <td class="text-center">
                            <x-button href="{{ route('kasir.customers.show', $customer->id) }}" variant="info" class="btn-sm">
                                <i class="fas fa-eye"></i> Detail
                            </x-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Belum ada data pelanggan.</td>
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