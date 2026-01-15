{{-- resources/views/admin/financial/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Ringkasan Finansial')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Finansial</li>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row g-4 mb-4">

            <div class="col-md-6">
                <x-card title="Proses Tutup Buku Bulanan">
                    <form action="{{ route('admin.financial.closing.process') }}" method="POST">
                        @csrf
                        <p>Pilih bulan dan tahun untuk menghitung laba bersih dan menyiapkannya untuk alokasi dana.</p>
                        
                        <div class="row">
                            {{-- Dropdown Bulan --}}
                            <div class="col-md-6 mb-3">
                                <label for="month" class="form-label">Bulan</label>
                                <select name="month" id="month" class="form-select" required>
                                    @foreach($months as $value => $label)
                                        <option value="{{ $value }}" {{ now()->format('m') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Dropdown Tahun --}}
                            <div class="col-md-6 mb-3">
                                <label for="year" class="form-label">Tahun</label>
                                <select name="year" id="year" class="form-select" required>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ now()->year == $year ? 'selected' : '' }}>
                                            {{ $year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <x-button type="submit" class="mt-2 w-100">
                            <i class="fas fa-calculator me-2"></i>Proses Tutup Buku
                        </x-button>
                    </form>
                </x-card>
            </div>

            {{-- Tombol Atur Modal --}}
            <div class="col-md-6">
                <x-card title="Pengaturan Modal">
                    <p>Atur jumlah modal investasi awal Anda untuk perhitungan ROI yang akurat.</p>
                    <a href="{{ route('admin.financial.capital.index') }}" class="btn btn-outline-primary">
                        <i class="fas fa-cog me-2"></i>Atur Modal Awal
                    </a>
                </x-card>
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Total Pemasukan --}}
            <div class="col-md-4">
                <div class="card text-white bg-success h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-arrow-down me-2"></i>Total Pemasukan</h5>
                        <h2 class="display-6">Rp {{ number_format($financialSummary['total_income'] ?? 0, 0, ',', '.') }}</h2>
                        <p class="card-text">Total semua pemasukan yang tercatat.</p>
                    </div>
                </div>
            </div>

            {{-- Total Pengeluaran --}}
            <div class="col-md-4">
                <div class="card text-white bg-danger h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-arrow-up me-2"></i>Total Pengeluaran</h5>
                        <h2 class="display-6">Rp {{ number_format($financialSummary['total_expense'] ?? 0, 0, ',', '.') }}</h2>
                        <p class="card-text">Total semua pengeluaran operasional.</p>
                    </div>
                </div>
            </div>

            {{-- Arus Kas Bersih --}}
            <div class="col-md-4">
                <div class="card text-white bg-primary h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-wallet me-2"></i>Arus Kas Bersih</h5>
                        <h2 class="display-6">Rp {{ number_format($financialSummary['net_cash_flow'] ?? 0, 0, ',', '.') }}</h2>
                        <p class="card-text">Selisih antara pemasukan dan pengeluaran.</p>
                    </div>
                </div>
            </div>
        </div>

        <x-card title="Navigasi Laporan Finansial">
            <p>Pilih laporan yang ingin Anda lihat lebih detail:</p>
            <div class="list-group">
                <a href="{{ route('admin.financial.cash-flow') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-exchange-alt fa-fw me-2"></i>
                        <strong>Laporan Arus Kas (Cash Flow)</strong>
                        <br>
                        <small class="text-muted">Lihat semua transaksi pemasukan dan pengeluaran secara detail.</small>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </a>
                <a href="{{ route('admin.financial.expenses') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-receipt fa-fw me-2"></i>
                        <strong>Laporan Pengeluaran</strong>
                        <br>
                        <small class="text-muted">Fokus pada rincian semua biaya operasional bisnis.</small>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </a>
                <a href="{{ route('admin.financial.roi-analysis') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-percentage fa-fw me-2"></i>
                        <strong>Analisis ROI (Return on Investment)</strong>
                        <br>
                        <small class="text-muted">Analisis pengembalian investasi dari modal yang dikeluarkan.</small>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </x-card>
    </div>
@endsection