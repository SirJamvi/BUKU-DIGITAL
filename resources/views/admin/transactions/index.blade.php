@extends('admin.layouts.app')

@section('title', 'Daftar Transaksi')
@section('breadcrumb')
    <li class="breadcrumb-item active">Transaksi</li>
@endsection

@section('content')
    <x-card title="Semua Riwayat Transaksi">
        @include('components.alert')
        <x-table>
            @slot('thead')
                <tr>
                    <th>ID</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Kasir</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Aksi</th>
                </tr>
            @endslot
            @forelse ($transactions as $transaction)
                <tr>
                    <td>#{{ $transaction->id }}</td>
                    <td>{{ $transaction->transaction_date->isoFormat('D MMM YYYY, HH:mm') }}</td>
                    <td>{{ $transaction->customer->name ?? 'Umum' }}</td>
                    <td>{{ $transaction->createdBy->name ?? 'N/A' }}</td>
                    <td class="text-end fw-bold">Rp {{ number_format($transaction->total_amount) }}</td>
                    <td class="text-center">
                        <x-button href="{{ route('admin.transactions.show', $transaction->id) }}" variant="info" class="btn-sm">
                            <i class="fas fa-eye"></i> Detail
                        </x-button>
                         <x-button href="{{ route('admin.transactions.edit', $transaction->id) }}" variant="warning" class="btn-sm">
                            <i class="fas fa-edit"></i>
                        </x-button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Tidak ada data transaksi.</td></tr>
            @endforelse
        </x-table>
        @if ($transactions->hasPages())
            <div class="mt-3">{{ $transactions->links('components.pagination') }}</div>
        @endif
    </x-card>
@endsection