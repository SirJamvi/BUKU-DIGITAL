{{-- resources/views/kasir/transactions/index.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Riwayat Transaksi')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Riwayat Transaksi</li>
@endsection

@section('content')
    <x-card>
        @slot('title')
            Daftar Transaksi Saya
        @endslot

        @include('components.alert')

        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Metode Bayar</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                @endslot

                @forelse ($transactions as $transaction)
                    <tr>
                        <td>#{{ $transaction->id }}</td>
                        <td>{{ $transaction->transaction_date->isoFormat('D MMMM YYYY, HH:mm') }}</td>
                        <td>{{ $transaction->customer->name ?? 'Umum' }}</td>
                        <td class="text-end fw-bold">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                        <td class="text-center"><span class="badge" style="background-color: var(--kasir-bg-secondary); color: var(--kasir-text);">{{ $transaction->payment_method }}</span></td>
                        <td class="text-center">
                            <x-button href="{{ route('kasir.transactions.show', $transaction->id) }}" variant="info" class="btn-sm">
                                <i class="fas fa-eye"></i> Lihat Detail
                            </x-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">Anda belum memiliki riwayat transaksi.</td>
                    </tr>
                @endforelse
            </x-table>
        </div>

        @if ($transactions->hasPages())
            <div class="mt-3">
                {{ $transactions->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection 