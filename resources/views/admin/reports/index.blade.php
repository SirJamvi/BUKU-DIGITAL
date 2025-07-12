 
{{-- resources/views/admin/reports/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Pusat Laporan')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Laporan</li>
@endsection

@section('content')
    <x-card title="Pilih Jenis Laporan">
        <p>Silakan pilih jenis laporan yang ingin Anda lihat. Setiap laporan dapat difilter berdasarkan rentang tanggal dan diekspor ke format PDF.</p>
        
        @include('components.alert')

        <div class="list-group">
            <a href="{{ route('admin.reports.sales') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-chart-line fa-fw me-2"></i>
                    <strong>Laporan Penjualan</strong>
                    <br>
                    <small class="text-muted">Analisis detail semua transaksi penjualan, produk terlaris, dan kinerja kasir.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
            <a href="{{ route('admin.reports.financial') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                 <div>
                    <i class="fas fa-file-invoice-dollar fa-fw me-2"></i>
                    <strong>Laporan Keuangan</strong>
                    <br>
                    <small class="text-muted">Rincian pemasukan, pengeluaran, dan keuntungan bersih bisnis Anda.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
            <a href="{{ route('admin.reports.inventory') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                 <div>
                    <i class="fas fa-warehouse fa-fw me-2"></i>
                    <strong>Laporan Inventaris</strong>
                    <br>
                    <small class="text-muted">Laporan status stok produk, valuasi inventaris, dan pergerakan barang.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </x-card>
@endsection