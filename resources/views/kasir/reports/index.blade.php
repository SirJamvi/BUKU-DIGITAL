{{-- resources/views/kasir/reports/index.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Pusat Laporan')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Laporan</li>
@endsection

@section('content')
    <x-card title="Pilih Laporan Anda">
        <p>Lihat riwayat dan rekapitulasi dari aktivitas penjualan yang telah Anda lakukan.</p>
        
        @include('components.alert')

        <div class="list-group">
            <a href="{{ route('kasir.reports.sales') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-chart-line fa-fw me-2" style="color: var(--kasir-accent);"></i>
                    <strong>Laporan Penjualan Saya</strong>
                    <br>
                    <small class="text-muted">Lihat semua riwayat transaksi penjualan yang Anda catat.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </x-card>
@endsection