@extends('admin.layouts.app')

@section('title', 'Manajemen Pelanggan')
@section('breadcrumb')
    <li class="breadcrumb-item active">Pelanggan</li>
@endsection

@section('content')
    <x-card title="Daftar Semua Pelanggan">
        @include('components.alert')
        <x-table>
            @slot('thead')
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Kontak</th>
                    <th>Tanggal Bergabung</th>
                    <th class="text-center">Aksi</th>
                </tr>
            @endslot
            @forelse ($customers as $customer)
                <tr>
                    <td>{{ $customer->id }}</td>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->phone ?? $customer->email }}</td>
                    <td>{{ $customer->join_date->isoFormat('D MMMM YYYY') }}</td>
                    <td class="text-center">
                        <x-button href="{{ route('admin.customers.show', $customer->id) }}" variant="info" class="btn-sm">
                            <i class="fas fa-eye"></i> Detail
                        </x-button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center">Tidak ada data pelanggan.</td></tr>
            @endforelse
        </x-table>
        @if ($customers->hasPages())
            <div class="mt-3">{{ $customers->links('components.pagination') }}</div>
        @endif
    </x-card>
@endsection