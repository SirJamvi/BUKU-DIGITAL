 
{{-- resources/views/admin/reports/sales.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Laporan Penjualan')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.reports.index') }}">Laporan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Penjualan</li>
@endsection

@section('content')
    {{-- Form Filter --}}
    <x-card>
        @slot('title')
            Filter Laporan Penjualan
        @endslot
        <form method="GET" action="{{ route('admin.reports.sales') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <x-input type="date" name="start_date" label="Tanggal Mulai" :value="request()->get('start_date')" />
                </div>
                <div class="col-md-5">
                    <x-input type="date" name="end_date" label="Tanggal Akhir" :value="request()->get('end_date')" />
                </div>
                <div class="col-md-2">
                    <x-button type="submit" variant="primary" class="w-100">Terapkan</x-button>
                </div>
            </div>
        </form>
    </x-card>

    <div class="mt-4"></div>

    {{-- Hasil Laporan --}}
    <x-card>
        @slot('title')
            Hasil Laporan Penjualan
        @endslot
        @slot('headerActions')
            <x-button variant="secondary"><i class="fas fa-print me-2"></i>Ekspor PDF</x-button>
        @endslot

        {{-- Ringkasan Data --}}
        <div class="row text-center mb-4">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <p class="card-title text-muted">Total Penjualan</p>
                        <h3 class="card-text">Rp {{ number_format($reportData['total_sales'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                     <div class="card-body">
                        <p class="card-title text-muted">Total Transaksi</p>
                        <h3 class="card-text">{{ number_format($reportData['total_transactions'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <x-table>
            @slot('thead')
                <tr>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Kasir</th>
                    <th>Total</th>
                </tr>
            @endslot
            @forelse ($reportData['transactions'] as $transaction)
                <tr>
                    <td>#{{ $transaction->id }}</td>
                    <td>{{ $transaction->transaction_date->isoFormat('D MMM YYYY, HH:mm') }}</td>
                    <td>{{ $transaction->customer->name ?? 'Umum' }}</td>
                    <td>{{ $transaction->createdBy->name ?? 'N/A' }}</td>
                    <td class="text-end">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Tidak ada data penjualan untuk rentang tanggal yang dipilih.</td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
@endsection