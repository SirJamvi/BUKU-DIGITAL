@extends('admin.layouts.app')

@section('title', 'Detail Pelanggan')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Pelanggan</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4">
            <x-card title="Profil Pelanggan">
                <div class="text-center mb-3">
                    <i class="fas fa-user-circle fa-5x text-secondary mb-3"></i>
                    <h4>{{ $customer->name }}</h4>
                </div>
                <p><strong>Email:</strong> {{ $customer->email ?? '-' }}</p>
                <p><strong>Telepon:</strong> {{ $customer->phone ?? '-' }}</p>
                <p><strong>Alamat:</strong> {{ $customer->address ?? '-' }}</p>
                <p><strong>Bergabung:</strong> {{ $customer->join_date->isoFormat('D MMMM YYYY') }}</p>
            </x-card>
        </div>
        <div class="col-md-8">
            <x-card title="Riwayat Transaksi">
                <x-table>
                    @slot('thead')
                        <tr><th>ID</th><th>Tanggal</th><th class="text-end">Total</th></tr>
                    @endslot
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>#{{ $transaction->id }}</td>
                            <td>{{ $transaction->transaction_date->isoFormat('D MMM YYYY, HH:mm') }}</td>
                            <td class="text-end">Rp {{ number_format($transaction->total_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center">Tidak ada riwayat transaksi.</td></tr>
                    @endforelse
                </x-table>
                 @if ($transactions->hasPages())
                    <div class="mt-3">{{ $transactions->links('components.pagination') }}</div>
                @endif
            </x-card>
        </div>
    </div>
@endsection