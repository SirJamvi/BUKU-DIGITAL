{{-- resources/views/kasir/reports/sales.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Laporan Penjualan Saya')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('kasir.reports.index') }}">Laporan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Penjualan</li>
@endsection

@section('content')
    {{-- Form Filter --}}
    <x-card>
        @slot('title')
            Filter Laporan Penjualan
        @endslot
        <form method="GET" action="{{ route('kasir.reports.sales') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <x-input type="date" name="start_date" label="Dari Tanggal" :value="request()->get('start_date')" />
                </div>
                <div class="col-md-5">
                    <x-input type="date" name="end_date" label="Sampai Tanggal" :value="request()->get('end_date')" />
                </div>
                <div class="col-md-2">
                    <x-button type="submit" variant="primary" class="w-100" style="background-color: var(--kasir-accent); border-color: var(--kasir-accent);">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    <div class="mt-4"></div>

    {{-- Hasil Laporan --}}
    <x-card>
        @slot('title')
            Hasil Laporan Penjualan Anda
        @endslot
        @slot('headerActions')
            {{-- Tombol ekspor bisa ditambahkan di sini nanti jika diperlukan --}}
        @endslot

        @if(isset($error))
            <div class="alert alert-danger">{{ $error }}</div>
        @else
            {{-- Ringkasan Data --}}
            <div class="row text-center mb-4 g-3">
                <div class="col-md-6">
                    <div class="card" style="background-color: var(--kasir-bg-secondary);">
                        <div class="card-body">
                            <p class="card-title text-muted">Total Penjualan Anda</p>
                            <h3 class="card-text fw-bold" style="color: var(--kasir-accent);">Rp {{ number_format($total_sales ?? 0, 0, ',', '.') }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card" style="background-color: var(--kasir-bg-secondary);">
                         <div class="card-body">
                            <p class="card-title text-muted">Total Transaksi Anda</p>
                            <h3 class="card-text fw-bold" style="color: var(--kasir-accent);">{{ number_format($total_transactions ?? 0, 0, ',', '.') }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            {{-- [BARU] Kartu Produk Terlaris --}}
            <div class="mb-4">
                <x-card title="Top 5 Produk Terlaris (Berdasarkan Filter)">
                     @if(isset($topSoldProducts) && $topSoldProducts->isNotEmpty())
                        <div class="list-group">
                            @foreach($topSoldProducts as $product)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">{{ $product->name }}</span>
                                    <span class="badge bg-secondary rounded-pill" style="font-size: 1rem;">
                                        {{ $product->total_quantity_sold }} Pcs
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted text-center">Tidak ada produk yang terjual pada periode ini.</p>
                    @endif
                </x-card>
            </div>

            {{-- Kartu Rincian Metode Pembayaran --}}
            <div class="mb-4">
                <x-card title="Rincian per Metode Pembayaran">
                     @if(isset($salesByPaymentMethod) && $salesByPaymentMethod->isNotEmpty())
                        <div class="list-group">
                            @foreach($salesByPaymentMethod as $method => $total)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">{{ ucfirst($method) }}</span>
                                    <span class="badge rounded-pill" style="background-color: var(--kasir-accent); font-size: 1rem;">
                                        Rp {{ number_format($total, 0, ',', '.') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted text-center">Tidak ada data untuk ditampilkan.</p>
                    @endif
                </x-card>
            </div>

            <div class="table-responsive">
                <x-table>
                    @slot('thead')
                        <tr>
                            <th>ID Transaksi</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    @endslot
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>#{{ $transaction->id }}</td>
                            <td>{{ $transaction->transaction_date->isoFormat('D MMM YYYY, HH:mm') }}</td>
                            <td>{{ $transaction->customer->name ?? 'Umum' }}</td>
                            <td class="text-end">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                            <td class="text-center">
                                 <x-button href="{{ route('kasir.transactions.show', $transaction->id) }}" variant="info" class="btn-sm">
                                    <i class="fas fa-receipt"></i> Lihat Struk
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">Tidak ada data penjualan untuk rentang tanggal yang dipilih.</td>
                        </tr>
                    @endforelse
                </x-table>
            </div>
        @endif
    </x-card>
@endsection