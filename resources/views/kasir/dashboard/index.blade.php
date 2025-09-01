{{-- resources/views/kasir/dashboard/index.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Dashboard Kasir')

{{-- Kosongkan breadcrumb untuk halaman dashboard utama --}}
@section('breadcrumb')
@endsection

@section('content')
<div class="container-fluid">
    {{-- Salam Pembuka --}}
    <div class="mb-4">
        <h3 class="fw-bold">Selamat Datang, {{ Auth::user()->name }}!</h3>
        <p class="text-muted">Berikut adalah ringkasan aktivitas penjualan Anda hari ini.</p>
    </div>

    @include('components.alert')

    {{-- Kartu Ringkasan Data --}}
    <div class="row g-4">
        {{-- Penjualan Hari Ini --}}
        <div class="col-lg-4 col-md-6">
            <div class="card h-100" style="background-color: var(--kasir-bg-secondary);">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="p-3 rounded-circle d-flex align-items-center justify-content-center" style="background-color: rgba(247, 86, 124, 0.1);">
                            <i class="fas fa-money-bill-wave fa-2x" style="color: var(--kasir-accent);"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="text-muted mb-1">Penjualan Hari Ini</h6>
                        <h4 class="fw-bold mb-0">Rp {{ number_format($mySalesToday ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- Jumlah Transaksi --}}
        <div class="col-lg-4 col-md-6">
            <div class="card h-100" style="background-color: var(--kasir-bg-dominant);">
                 <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="p-3 rounded-circle d-flex align-items-center justify-content-center" style="background-color: rgba(247, 86, 124, 0.1);">
                            <i class="fas fa-receipt fa-2x" style="color: var(--kasir-accent);"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="text-muted mb-1">Jumlah Transaksi</h6>
                        <h4 class="fw-bold mb-0">{{ number_format($myTransactionsToday ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- Notifikasi Stok --}}
        <div class="col-lg-4 col-md-6">
            <div class="card h-100" style="background-color: var(--kasir-bg-dominant);">
                 <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="p-3 rounded-circle d-flex align-items-center justify-content-center" style="background-color: rgba(247, 86, 124, 0.1);">
                            <i class="fas fa-exclamation-triangle fa-2x" style="color: var(--kasir-accent);"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="text-muted mb-1">Produk Stok Menipis</h6>
                        <h4 class="fw-bold mb-0">{{ number_format($lowStockItems ?? 0, 0, ',', '.') }} Item</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Kartu Rincian Metode Pembayaran --}}
    <div class="row mt-4">
        <div class="col-12">
            <x-card title="Rincian Penjualan Hari Ini per Metode Pembayaran">
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
                    <p class="text-muted text-center">Belum ada penjualan hari ini.</p>
                @endif
            </x-card>
        </div>
    </div>

    {{-- Kartu Aksi Cepat --}}
    <div class="row mt-4">
        <div class="col-12">
            <x-card title="Aksi Cepat">
                <p>Mulai pekerjaan Anda dengan mengakses fitur yang paling sering digunakan.</p>
                <div class="d-grid gap-2 d-md-flex">
                    <a href="{{ route('kasir.pos.index') }}" class="btn btn-lg" style="background-color: var(--kasir-accent); color: white;">
                        <i class="fas fa-desktop me-2"></i>Mulai Transaksi (POS)
                    </a>
                    <a href="{{ route('kasir.customers.create') }}" class="btn btn-lg btn-outline-secondary">
                        <i class="fas fa-user-plus me-2"></i>Tambah Pelanggan
                    </a>
                </div>
            </x-card>
        </div>
    </div>
</div>
@endsection