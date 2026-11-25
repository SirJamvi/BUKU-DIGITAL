{{-- resources/views/admin/financial/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Ringkasan Finansial')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Finansial</li>
@endsection

@section('content')
    <div class="container-fluid">
        @include('components.alert')
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

        {{-- Alert Snapshot HPP jika ada penjualan tanpa HPP --}}
        @if(!empty($financialSummary['missing_cogs_count']) && $financialSummary['missing_cogs_count'] > 0)
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4 p-3" style="border-radius: 10px;">
            <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
            <div>
                <h6 class="alert-heading fw-bold mb-1">Perhatian: Ditemukan {{ number_format($financialSummary['missing_cogs_count']) }} Item Penjualan Tanpa Snapshot HPP</h6>
                <p class="mb-0 small">
                    Terdapat transaksi bernilai total omzet <strong>Rp {{ number_format($financialSummary['missing_cogs_revenue'], 0, ',', '.') }}</strong> yang belum tercatat snapshot HPP pada saat nota dicetak. Sistem menggunakan fallback master produk untuk menghitung estimasi laba kotor.
                </p>
            </div>
        </div>
        @endif

        {{-- Section 1: Metrik Laba Rugi (P&L Akrual) --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-pie text-primary me-2"></i>Kinerja Laba Rugi (P&L)</h5>
            <span class="badge bg-light text-muted border ms-2">Akrual Omzet & HPP</span>
        </div>
        <div class="row g-3 mb-4">
            {{-- Total Omzet Penjualan --}}
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #10b981 !important; border-radius: 10px;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold">Total Omzet Penjualan</span>
                            <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-circle"><i class="fas fa-shopping-cart"></i></span>
                        </div>
                        <h3 class="fw-bold text-success mb-1">Rp {{ number_format($financialSummary['total_income'] ?? 0, 0, ',', '.') }}</h3>
                        <p class="card-text text-muted small mb-0">Total pendapatan kotor penjualan barang/jasa.</p>
                    </div>
                </div>
            </div>

            {{-- Laba Kotor (Gross Profit) --}}
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #06b6d4 !important; border-radius: 10px;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold">Laba Kotor (Gross Profit)</span>
                            <span class="badge bg-info bg-opacity-10 text-info p-2 rounded-circle"><i class="fas fa-boxes-stacked"></i></span>
                        </div>
                        <h3 class="fw-bold text-info mb-1">Rp {{ number_format($financialSummary['gross_profit'] ?? 0, 0, ',', '.') }}</h3>
                        <p class="card-text text-muted small mb-0">Omzet Penjualan dikurangi Beban Pokok (HPP).</p>
                    </div>
                </div>
            </div>

            {{-- Beban Operasional Usaha --}}
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #ef4444 !important; border-radius: 10px;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold">Beban Operasional</span>
                            <span class="badge bg-danger bg-opacity-10 text-danger p-2 rounded-circle"><i class="fas fa-file-invoice-dollar"></i></span>
                        </div>
                        <h3 class="fw-bold text-danger mb-1">Rp {{ number_format($financialSummary['total_expense'] ?? 0, 0, ',', '.') }}</h3>
                        <p class="card-text text-muted small mb-0">Total pengeluaran operasional usaha murni (Non-HPP).</p>
                    </div>
                </div>
            </div>

            {{-- Laba Bersih Operasional (Net Profit) --}}
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #3b82f6 !important; border-radius: 10px;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold">Laba Bersih (Net Profit)</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-circle"><i class="fas fa-coins"></i></span>
                        </div>
                        <h3 class="fw-bold {{ ($financialSummary['net_profit'] ?? 0) >= 0 ? 'text-primary' : 'text-danger' }} mb-1">
                            Rp {{ number_format($financialSummary['net_profit'] ?? 0, 0, ',', '.') }}
                        </h3>
                        <p class="card-text text-muted small mb-0">Laba Kotor dikurangi Beban Operasional.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Metrik Arus Kas Riil (Cash Flow) --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-wallet text-success me-2"></i>Likuiditas & Arus Kas Riil</h5>
            <span class="badge bg-light text-muted border ms-2">Fisik Kas Laci & Bank</span>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%); border-radius: 10px;">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <span class="badge bg-light text-dark px-2 py-1 mb-2 fw-semibold">Arus Kas Bersih (Net Cash Flow)</span>
                            <h2 class="display-6 fw-bold mb-1">Rp {{ number_format($financialSummary['net_cash_flow'] ?? 0, 0, ',', '.') }}</h2>
                            <p class="mb-0 text-white-50 small">
                                Selisih total uang masuk fisik/bank dikurangi total kas keluar fisik/bank (termasuk modal stok dan non-P&L).
                            </p>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('admin.financial.cash-flow') }}" class="btn btn-light fw-semibold">
                                <i class="fas fa-stream me-1 text-primary"></i> Buka Buku Arus Kas Lengkap
                            </a>
                        </div>
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