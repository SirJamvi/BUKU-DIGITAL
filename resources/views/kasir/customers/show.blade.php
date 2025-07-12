{{-- resources/views/kasir/customers/show.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Detail Pelanggan')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('kasir.customers.index') }}">Pelanggan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <x-card title="Informasi Pelanggan">
             <div class="text-center mb-3">
                <i class="fas fa-user-circle fa-5x" style="color: var(--kasir-accent);"></i>
                <h4 class="mt-3">{{ $customer->name }}</h4>
                <span class="text-muted">Bergabung sejak {{ \Carbon\Carbon::parse($customer->join_date)->isoFormat('D MMM YYYY') }}</span>
            </div>
            <hr>
            <ul class="list-unstyled">
                <li class="mb-2"><i class="fas fa-phone-alt fa-fw me-2"></i> {{ $customer->phone ?? '-' }}</li>
                <li class="mb-2"><i class="fas fa-envelope fa-fw me-2"></i> {{ $customer->email ?? '-' }}</li>
                <li class="mb-2"><i class="fas fa-map-marker-alt fa-fw me-2"></i> {{ $customer->address ?? '-' }}</li>
            </ul>
        </x-card>
    </div>
    <div class="col-md-8">
        <x-card title="Riwayat Transaksi Pelanggan">
            <div class="table-responsive">
                <x-table>
                    @slot('thead')
                        <tr>
                            <th>ID Transaksi</th>
                            <th>Tanggal</th>
                            <th class="text-end">Total Belanja</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    @endslot
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>#{{ $transaction->id }}</td>
                            <td>{{ $transaction->transaction_date->isoFormat('D MMM YYYY, HH:mm') }}</td>
                            <td class="text-end">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                            <td class="text-center">
                                <x-button href="{{ route('kasir.transactions.show', $transaction->id) }}" variant="info" class="btn-sm">
                                    <i class="fas fa-receipt"></i> Lihat Struk
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">Pelanggan ini belum memiliki riwayat transaksi.</td>
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
    </div>
</div>
@endsection