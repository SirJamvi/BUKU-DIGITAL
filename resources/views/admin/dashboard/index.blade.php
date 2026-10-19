@extends('admin.layouts.app')

@section('title', 'Dashboard Admin')

@section('breadcrumb')
<li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
@if(isset($error))
<div class="alert alert-danger">{{ $error }}</div>
@else
{{-- Header Section --}}
<div class="dashboard-header mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="dashboard-title">Selamat Datang, {{ Auth::user()->name ?? 'Admin' }}</h1>
            <p class="dashboard-subtitle">Berikut adalah ringkasan performa bisnis Anda hari ini</p>
        </div>
        <div class="col-md-4 text-end d-flex flex-column align-items-md-end gap-2">
            <div class="dashboard-date">
                <i class="fas fa-calendar-alt me-1"></i>
                <span>{{ \Carbon\Carbon::now()->format('l, d F Y') }}</span>
            </div>
            <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-25 px-3 py-1 rounded-pill text-white shadow-sm" style="font-size: 0.8rem; backdrop-filter: blur(10px);">
                <span class="spinner-border spinner-border-sm text-warning" id="autoReloadSpinner" style="display: none; width: 0.75rem; height: 0.75rem;" role="status"></span>
                <span class="badge bg-success rounded-circle p-1" id="livePulseDot" style="width: 8px; height: 8px; box-shadow: 0 0 8px #28a745;"> </span>
                <span>Auto-Refresh: <strong id="reloadCountdown">60s</strong></span>
                <button type="button" class="btn btn-sm btn-link text-white p-0 text-decoration-none ms-1" onclick="manualTriggerRefresh()" title="Perbarui Data Sekarang">
                    <i class="fas fa-rotate" id="manualRefreshIcon"></i>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Performance Metrics Cards --}}
<div class="row g-4 mb-4" id="dashboard-kpi-row">
    {{-- 1. Penjualan Hari Ini (Omset & Fisik Ball) --}}
    <div class="col-xl-3 col-md-6">
        <div class="metric-card metric-card-sales">
            <div class="metric-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="metric-content">
                <h3 class="metric-title">Penjualan Hari Ini</h3>
                <div class="metric-value">Rp {{ number_format($salesToday ?? 0, 0, ',', '.') }}</div>
                <div class="d-flex flex-wrap gap-1 align-items-center mt-1">
                    <span class="badge bg-primary text-white py-1 px-2 fw-bold">
                        <i class="fas fa-boxes-stacked me-1"></i>{{ number_format($totalBallSoldToday ?? 0) }} Ball
                    </span>
                    @if(isset($salesChangePercentage))
                        @if($salesChangePercentage >= 0)
                        <div class="metric-change positive py-0 px-2" style="font-size: 0.78rem;">
                            <i class="fas fa-arrow-up"></i>
                            <span>+{{ $salesChangePercentage }}% vs kemarin</span>
                        </div>
                        @else
                        <div class="metric-change negative py-0 px-2" style="font-size: 0.78rem;">
                            <i class="fas fa-arrow-down"></i>
                            <span>{{ abs($salesChangePercentage) }}% vs kemarin</span>
                        </div>
                        @endif
                    @endif
                </div>
            </div>
            <div class="metric-progress">
                <div class="progress-bar" style="width: 75%"></div>
            </div>
        </div>
    </div>

    {{-- 2. Arus Kas Riil Hari Ini (Uang Masuk vs Kasbon) --}}
    <div class="col-xl-3 col-md-6">
        <div class="metric-card metric-card-cash">
            <div class="metric-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%) !important;">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="metric-content">
                <h3 class="metric-title">Arus Kas Riil Hari Ini</h3>
                <div class="metric-value text-success">Rp {{ number_format($totalAllInflowToday ?? ($cashInToday ?? 0), 0, ',', '.') }}</div>
                <div class="d-flex flex-wrap gap-1 align-items-center mt-1">
                    <span class="badge py-1 px-2" style="background-color: #d1e7dd !important; color: #0f5132 !important; border: 1px solid #badbcc !important; font-size: 0.78rem; font-weight: 600;">
                        <i class="fas fa-check-circle me-1 text-success"></i>Lunas (Hari Ini): Rp {{ number_format($cashInToday ?? 0, 0, ',', '.') }}
                    </span>
                    @if(($pendingToday ?? 0) > 0)
                    <span class="badge py-1 px-2" style="background-color: #f8d7da !important; color: #842029 !important; border: 1px solid #f5c2c7 !important; font-size: 0.78rem; font-weight: 600;" title="Total Kasbon / Tempo Baru Hari Ini">
                        <i class="fas fa-clock me-1 text-danger"></i>Kasbon (Piutang): Rp {{ number_format($pendingToday, 0, ',', '.') }}
                    </span>
                    @else
                    <span class="badge py-1 px-2" style="background-color: #f8f9fa !important; color: #6c757d !important; border: 1px solid #dee2e6 !important; font-size: 0.78rem; font-weight: 600;">
                        <i class="fas fa-check me-1 text-secondary"></i>Nol Kasbon
                    </span>
                    @endif
                </div>

                {{-- [BARU] Badge Rincian Saluran Pembayaran (Cash, DANA, Transfer Bank, & Pelunasan Kasbon) --}}
                <div class="d-flex flex-wrap gap-1 align-items-center mt-2 pt-2 border-top" style="font-size: 0.72rem;">
                    <span class="badge bg-light text-dark border py-1 px-2" title="Total Uang Masuk Tunai (Penjualan + Pelunasan Kasbon)">
                        💵 Cash: <strong>Rp {{ number_format($totalInflowCash ?? 0, 0, ',', '.') }}</strong>
                    </span>
                    <span class="badge bg-light text-dark border py-1 px-2" title="Total Uang Masuk via DANA (Penjualan + Pelunasan Kasbon)">
                        📱 DANA: <strong>Rp {{ number_format($totalInflowDana ?? 0, 0, ',', '.') }}</strong>
                    </span>
                    <span class="badge bg-light text-dark border py-1 px-2" title="Total Uang Masuk via Transfer Bank (Penjualan + Pelunasan Kasbon)">
                        🏦 Transfer: <strong>Rp {{ number_format($totalInflowTransfer ?? 0, 0, ',', '.') }}</strong>
                    </span>
                </div>
                @if(($pelunasanCairTotal ?? 0) > 0)
                <div class="mt-1 small" style="font-size: 0.7rem; color: #475569;">
                    <i class="fas fa-rotate text-primary me-1"></i>Pelunasan Kasbon Cair: <strong>Rp {{ number_format($pelunasanCairTotal, 0, ',', '.') }}</strong> 
                    <span class="text-muted">(Tunai: Rp {{ number_format($pelunasanCairCash ?? 0, 0, ',', '.') }} | Non-Tunai: Rp {{ number_format($pelunasanCairNonCash ?? 0, 0, ',', '.') }})</span>
                </div>
                @endif
            </div>
            <div class="metric-progress">
                <div class="progress-bar" style="width: 85%; background: linear-gradient(90deg, #11998e, #38ef7d);"></div>
            </div>
        </div>
    </div>

    {{-- 3. Stok Ready di Cold Storage --}}
    <div class="col-xl-3 col-md-6">
        <div class="metric-card metric-card-storage">
            <div class="metric-icon" style="background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%) !important;">
                <i class="fas fa-snowflake"></i>
            </div>
            <div class="metric-content">
                <h3 class="metric-title">Stok Cold Storage (Ready)</h3>
                <div class="metric-value text-info">{{ number_format($totalReadyStock ?? 0) }} <span class="metric-unit">Ball / Karung</span></div>
                <div class="d-flex flex-wrap gap-1 align-items-center mt-1">
                    @forelse(($readyStockItems ?? [])->take(3) as $stk)
                    <span class="badge bg-light text-dark border py-1 px-2" style="font-size: 0.75rem;">
                        {{ $stk->product->name ?? 'Es' }}: <strong>{{ $stk->current_stock }}</strong>
                    </span>
                    @empty
                    <span class="badge bg-warning text-dark py-1 px-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>Gudang Kosong
                    </span>
                    @endforelse
                </div>
            </div>
            <div class="metric-progress">
                <div class="progress-bar" style="width: 65%; background: linear-gradient(90deg, #2193b0, #6dd5ed);"></div>
            </div>
        </div>
    </div>

    {{-- 4. Laba Bersih Bulan Ini --}}
    <div class="col-xl-3 col-md-6">
        <div class="metric-card metric-card-profit">
            <div class="metric-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="metric-content">
                <h3 class="metric-title">Laba Bersih Bulan Ini</h3>
                <div class="metric-value">Rp {{ number_format($netProfitThisMonth ?? 0, 0, ',', '.') }}</div>
                <div class="d-flex flex-wrap gap-1 align-items-center mt-1">
                    @if(isset($profitChangePercentage))
                        @if($profitChangePercentage >= 0)
                        <div class="metric-change positive py-0 px-2" style="font-size: 0.78rem;">
                            <i class="fas fa-arrow-up"></i>
                            <span>+{{ $profitChangePercentage }}% vs bln lalu</span>
                        </div>
                        @else
                        <div class="metric-change negative py-0 px-2" style="font-size: 0.78rem;">
                            <i class="fas fa-arrow-down"></i>
                            <span>{{ abs($profitChangePercentage) }}% vs bln lalu</span>
                        </div>
                        @endif
                    @endif
                    <span class="badge bg-light text-dark border py-1 px-2" style="font-size: 0.75rem;">
                        Margin: <strong>{{ $profitMargin ?? 0 }}%</strong>
                    </span>
                </div>
            </div>
            <div class="metric-progress">
                <div class="progress-bar" style="width: 85%"></div>
            </div>
        </div>
    </div>
</div>

{{-- FILTER TANGGAL UNTUK AKTIVITAS HARIAN --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-2 px-3 d-flex align-items-center bg-light rounded">
                <form action="{{ route('admin.dashboard') }}" method="GET" class="d-flex align-items-center w-100">
                    <label for="filter_date" class="form-label mb-0 me-3 fw-bold text-dark">
                        <i class="fas fa-filter text-primary me-1"></i> Filter Aktivitas Harian (Kasir, Driver & Stok):
                    </label>
                    <input type="date" name="filter_date" id="filter_date" class="form-control w-auto me-2 border-primary" value="{{ $filterDate }}">
                    <button type="submit" class="btn btn-primary btn-sm px-3">Tampilkan</button>

                    @if(!$isToday)
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm ms-2">
                        <i class="fas fa-undo"></i> Kembali ke Hari Ini
                    </a>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ================================================================= --}}
{{-- [PRIORITAS] MONITORING CASH ON HAND REAL PER AKUN KASIR           --}}
{{-- ================================================================= --}}
<div id="cashier-priority-container">
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden; border-top: 4px solid #10b981 !important;">
            <div class="card-header text-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;">
                <div>
                    <h5 class="mb-0 fw-bold d-flex align-items-center">
                        <span class="badge badge-soft-success me-2 px-2 py-1" style="font-size: 0.75rem; letter-spacing: 0.5px; background-color: #dcfce7 !important; color: #15803d !important; border: 1px solid #86efac !important;">PRIORITAS</span>
                        <i class="fas fa-cash-register text-warning me-2"></i> Rekonsiliasi Kas Laci & Performa Kasir ({{ $isToday ? 'Hari Ini' : $formattedDate }})
                    </h5>
                    <small class="text-white-50">Monitoring uang kas fisik di laci kasir (Cash on Hand) dan estimasi laba bersih operasional shift.</small>
                </div>
                @php
                    $allCashiers = collect($cashierMonitoring ?? []);
                    $activeCashiersCount = $allCashiers->where('has_activity', true)->count();
                    $standbyCashiersCount = $allCashiers->where('has_activity', false)->count();
                    $totalAllCashIn = $allCashiers->sum('total_cash_in');
                    $totalAllCogs = $allCashiers->sum('cogs');
                    $totalAllExpenses = $allCashiers->sum('expenses');
                    $totalAllDrawer = $allCashiers->sum('physical_drawer');
                    $totalAllShiftProfit = $allCashiers->sum('shift_net_profit');
                @endphp
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-secondary px-3 py-2 fs-6">
                        <i class="fas fa-users me-1"></i> {{ $allCashiers->count() }} Akun Kasir
                    </span>
                    <span class="badge bg-light text-dark px-3 py-2 fs-6" title="Total Uang Tunai Masuk">
                        <i class="fas fa-arrow-down text-success me-1"></i> Masuk: <strong>Rp {{ number_format($totalAllCashIn, 0, ',', '.') }}</strong>
                    </span>
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6" title="Total Semua Kas Keluar Laci">
                        <i class="fas fa-receipt me-1"></i> Keluar Laci: <strong>- Rp {{ number_format($totalAllExpenses, 0, ',', '.') }}</strong>
                    </span>
                    <span class="badge bg-success px-3 py-2 fs-6 fw-bold shadow-sm" title="Grand Total Saldo Kas Laci Semua Kasir">
                        <i class="fas fa-cash-register me-1"></i> Saldo Laci: <strong>Rp {{ number_format($totalAllDrawer, 0, ',', '.') }}</strong>
                    </span>
                    <span class="badge {{ $totalAllShiftProfit >= 0 ? 'bg-info bg-opacity-75 text-white' : 'bg-danger text-white' }} px-3 py-2 fs-6 fw-bold shadow-sm" title="Grand Total Estimasi Laba Bersih Shift">
                        <i class="fas fa-chart-line me-1"></i> Laba Shift: <strong>{{ $totalAllShiftProfit >= 0 ? '+' : '' }}Rp {{ number_format($totalAllShiftProfit, 0, ',', '.') }}</strong>
                    </span>
                </div>
            </div>

            {{-- Toolbar Filter Cepat: Aktif Bertugas / Standby / Semua --}}
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small fw-bold"><i class="fas fa-filter text-primary me-1"></i>Filter Kasir:</span>
                    <div class="btn-group btn-group-sm" role="group" id="cashierFilterBtnGroup" aria-label="Filter Kasir">
                        <button type="button" class="btn btn-primary active px-3 fw-semibold cashier-filter-btn" data-filter="active" onclick="switchCashierFilter('active')">
                            <i class="fas fa-circle-dot text-success me-1"></i>Aktif Bertugas <span class="badge bg-white text-primary ms-1 rounded-pill">{{ $activeCashiersCount }}</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary px-3 fw-semibold cashier-filter-btn" data-filter="standby" onclick="switchCashierFilter('standby')">
                            <i class="fas fa-clock text-muted me-1"></i>Standby (Nol Trx) <span class="badge bg-secondary ms-1 rounded-pill">{{ $standbyCashiersCount }}</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary px-3 fw-semibold cashier-filter-btn" data-filter="all" onclick="switchCashierFilter('all')">
                            <i class="fas fa-users me-1"></i>Semua Kasir <span class="badge bg-secondary ms-1 rounded-pill">{{ $allCashiers->count() }}</span>
                        </button>
                    </div>
                </div>
                <div class="small text-muted d-flex align-items-center gap-2">
                    <span id="cashierFilterInfo">
                        Menampilkan <strong>{{ $activeCashiersCount }}</strong> kasir aktif bertugas
                    </span>
                </div>
            </div>

            <div class="card-body bg-light p-3">
                @if(count($cashierMonitoring ?? []) > 0)
                <div class="row g-3" id="cashierCardsGrid">
                    {{-- Alert jika filter aktif dipilih tapi belum ada transaksi --}}
                    <div id="cashier-no-active-alert" class="col-12 {{ $activeCashiersCount > 0 ? 'd-none' : '' }}">
                        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center justify-content-between p-3 mb-0" style="border-radius: 10px;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-3 fs-4 text-info"></i>
                                <div>
                                    <strong class="d-block">Belum Ada Kasir yang Memulai Transaksi Hari Ini</strong>
                                    <span class="small text-muted">Semua akun kasir saat ini berstatus Standby (Rp 0).</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="switchCashierFilter('standby')">
                                <i class="fas fa-eye me-1"></i>Lihat Kasir Standby ({{ $standbyCashiersCount }})
                            </button>
                        </div>
                    </div>

                    {{-- Alert jika filter standby dipilih tapi semua kasir sedang aktif --}}
                    <div id="cashier-no-standby-alert" class="col-12 d-none">
                        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center p-3 mb-0" style="border-radius: 10px;">
                            <i class="fas fa-check-circle me-3 fs-4 text-success"></i>
                            <div>
                                <strong class="d-block">Semua Kasir Sedang Aktif!</strong>
                                <span class="small text-muted">Seluruh akun kasir hari ini memiliki aktivitas transaksi tunai atau pengeluaran.</span>
                            </div>
                        </div>
                    </div>

                    @foreach($cashierMonitoring as $c)
                    <div class="col-xl-4 col-md-6 cashier-card-col" data-status="{{ $c['has_activity'] ? 'active' : 'standby' }}">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 10px; transition: transform 0.2s, box-shadow 0.2s;">
                            {{-- Header Card Kasir --}}
                            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background-color: #eff6ff !important; color: #1d4ed8 !important;">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div>
                                        <strong class="text-dark fs-6">{{ $c['name'] }}</strong>
                                        @if($c['phone'] && $c['phone'] !== '-')
                                        <br><small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $c['phone'] }}</small>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    @if($c['has_activity'])
                                    <span class="badge badge-soft-success px-2 py-1" style="background-color: #dcfce7 !important; color: #15803d !important; border: 1px solid #86efac !important;">
                                        <i class="fas fa-circle-dot me-1"></i>Aktif
                                    </span>
                                    @else
                                    <span class="badge badge-soft-secondary border px-2 py-1" style="background-color: #f1f5f9 !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;">
                                        Standby
                                    </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Body Card Kasir: Hero Dual-Metric (Saldo Laci & Laba Shift) --}}
                            <div class="card-body p-3">
                                <div class="text-center p-3 rounded mb-3" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.12) 100%); border: 1px solid rgba(16, 185, 129, 0.25);">
                                    <span class="text-uppercase fw-bold text-muted small" style="letter-spacing: 0.5px;">SALDO KAS LACI (FISIK)</span>
                                    <h3 class="fw-bold mb-1 mt-1 {{ $c['physical_drawer'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        Rp {{ number_format($c['physical_drawer'], 0, ',', '.') }}
                                    </h3>
                                    <div class="d-flex justify-content-center align-items-center mt-2">
                                        <span class="badge {{ $c['shift_net_profit'] >= 0 ? 'badge-soft-success' : 'badge-soft-danger' }} px-2 py-1" style="font-size: 0.78rem; {{ $c['shift_net_profit'] >= 0 ? 'background-color: #dcfce7 !important; color: #15803d !important; border: 1px solid #86efac !important;' : 'background-color: #fee2e2 !important; color: #b91c1c !important; border: 1px solid #fca5a5 !important;' }}">
                                            <i class="fas {{ $c['shift_net_profit'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} me-1"></i>
                                            Estimasi Laba Shift: <strong>{{ $c['shift_net_profit'] >= 0 ? '+' : '' }}Rp {{ number_format($c['shift_net_profit'], 0, ',', '.') }}</strong>
                                        </span>
                                    </div>
                                </div>

                                {{-- Rincian Waterfall Perhitungan --}}
                                <div class="bg-white rounded border p-2 mb-3">
                                    @if(($c['opening_cash'] ?? 0) > 0)
                                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                        <span class="small text-muted"><i class="fas fa-vault text-secondary me-1"></i> Modal Awal (Kas Kemarin):</span>
                                        <span class="fw-bold text-dark">+ Rp {{ number_format($c['opening_cash'], 0, ',', '.') }}</span>
                                    </div>
                                    @endif
                                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                        <span class="small text-muted"><i class="fas fa-money-bill-wave text-success me-1"></i> Kas Tunai Masuk:</span>
                                        <span class="fw-bold text-success">+ Rp {{ number_format($c['total_cash_in'], 0, ',', '.') }}</span>
                                    </div>
                                    @if(($c['pelunasan_cash'] ?? 0) > 0)
                                    <div class="d-flex justify-content-between align-items-center px-1 pb-1 border-bottom text-muted" style="font-size: 0.7rem; background-color: #f8fafc;">
                                        <span>↳ Meja: Rp {{ number_format($c['cash_sales'], 0, ',', '.') }}</span>
                                        <span>Setoran: Rp {{ number_format($c['pelunasan_cash'], 0, ',', '.') }}</span>
                                    </div>
                                    @endif
                                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                        <span class="small text-muted"><i class="fas fa-receipt text-danger me-1"></i> Kas Keluar dari Laci:</span>
                                        <span class="fw-bold text-danger">- Rp {{ number_format($c['expenses'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom bg-light px-1 rounded">
                                        <span class="small fw-bold text-dark"><i class="fas fa-cash-register text-success me-1"></i> Sisa Fisik Laci:</span>
                                        <span class="fw-bold {{ $c['physical_drawer'] >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($c['physical_drawer'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom mt-1">
                                        <span class="small text-muted"><i class="fas fa-boxes-stacked text-info me-1"></i> Modal Es (COGS):</span>
                                        <span class="small text-muted">Rp {{ number_format($c['cogs'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                        <span class="small text-muted"><i class="fas fa-gas-pump text-warning me-1"></i> Beban Ops Murni:</span>
                                        <span class="small text-muted">Rp {{ number_format($c['operational_expenses'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-2">
                                        <span class="small fw-bold text-dark"><i class="fas fa-chart-line text-primary me-1"></i> Estimasi Laba Bersih:</span>
                                        <span class="fw-bold {{ $c['shift_net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $c['shift_net_profit'] >= 0 ? '+' : '' }}Rp {{ number_format($c['shift_net_profit'], 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Info Tambahan: Non-Tunai & Kasbon --}}
                                <div class="d-flex justify-content-between align-items-center small text-muted px-1">
                                    <span><i class="fas fa-building-columns me-1"></i>Transfer: <strong>Rp {{ number_format($c['non_cash_sales'], 0, ',', '.') }}</strong></span>
                                    <span><i class="fas fa-clock text-danger me-1"></i>Kasbon: <strong>Rp {{ number_format($c['kasbon'], 0, ',', '.') }}</strong></span>
                                </div>
                            </div>

                            {{-- Footer Card Kasir: Tombol Detail --}}
                            <div class="card-footer bg-white border-top p-2">
                                <button type="button" class="btn btn-outline-primary btn-sm w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#modalCashierDetail_{{ $c['user_id'] }}">
                                    <i class="fas fa-file-invoice-dollar me-1"></i> Rincian Transaksi & Modal
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-4 text-muted bg-white rounded border">
                    <i class="fas fa-cash-register fa-3x mb-2 text-secondary opacity-50"></i>
                    <h6 class="fw-bold text-secondary">Belum Ada Aktivitas Kasir</h6>
                    <p class="small mb-0">Tidak ada transaksi tunai maupun pengeluaran pada tanggal {{ $formattedDate }}.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
</div>

{{-- ================================================================= --}}
{{-- [BARU] MONITORING & REKAP PENGIRIMAN DRIVER                       --}}
{{-- ================================================================= --}}
<div id="driver-monitoring-container">
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-truck-fast text-warning me-2"></i> Monitoring Pengiriman Driver ({{ $isToday ? 'Hari Ini' : $formattedDate }})
                    </h5>
                    <small class="text-white-50">Rekap ritase surat jalan, total muatan ball es kristal, dan rincian penagihan toko per driver.</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-secondary px-3 py-2 fs-6">
                        <i class="fas fa-user-tie me-1"></i> {{ count($driverRecap ?? []) }} Driver Bertugas
                    </span>
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold">
                        <i class="fas fa-boxes-stacked me-1"></i> {{ collect($driverRecap ?? [])->sum('total_qty') }} Ball / Pack
                    </span>
                    <span class="badge bg-success px-3 py-2 fs-6 fw-bold">
                        Rp {{ number_format(collect($driverRecap ?? [])->sum('total_amount'), 0, ',', '.') }}
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                @if(isset($driverRecap) && count($driverRecap) > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 py-3">Nama Driver</th>
                                <th class="text-center">Ritase (Trip)</th>
                                <th class="text-center">Total Pesanan</th>
                                <th>Total Muatan (Semua Produk)</th>
                                <th class="text-end">Total Tagihan</th>
                                <th>Status Pembayaran</th>
                                <th class="text-center pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($driverRecap as $d)
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center">
                                        <div class="p-2 rounded-circle me-3" style="background-color: #fef3c7 !important; color: #d97706 !important;">
                                            <i class="fas fa-motorcycle fa-lg"></i>
                                        </div>
                                        <div>
                                            <strong class="fs-6 text-dark">{{ $d['driver_name'] }}</strong>
                                            @if($d['driver_phone'] && $d['driver_phone'] !== '-')
                                             <br><small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $d['driver_phone'] }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-dark rounded-pill px-3 py-2 fs-6">
                                        {{ $d['total_trips'] }} Ritase
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                                        {{ $d['total_orders'] }} Toko / Struk
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary fs-6">{{ $d['total_qty'] }} Ball / Qty</div>
                                    <div class="small text-muted mt-1">
                                        @foreach($d['product_quantities'] as $pName => $qty)
                                        <span class="badge bg-light text-dark border me-1 mb-1">{{ $pName }}: <strong>{{ $qty }}</strong></span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-end fw-bold fs-6 text-dark">
                                    Rp {{ number_format($d['total_amount'], 0, ',', '.') }}
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-success fw-bold">
                                            <i class="fas fa-check-circle me-1"></i>Lunas: Rp {{ number_format($d['total_paid'], 0, ',', '.') }}
                                        </span>
                                        @if($d['total_pending'] > 0)
                                        <br>
                                        <span class="text-danger fw-bold">
                                            <i class="fas fa-clock me-1"></i>Kasbon: Rp {{ number_format($d['total_pending'], 0, ',', '.') }}
                                        </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center pe-3">
                                    <button type="button" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalDriverDetail_{{ $d['driver_id'] }}">
                                        <i class="fas fa-list-check me-1"></i> Lihat Detail
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-truck-ramp-box fa-3x mb-2 text-secondary opacity-50"></i>
                    <h6 class="fw-bold text-secondary">Belum Ada Pengiriman Driver</h6>
                    <p class="small mb-0">Tidak ada pesanan yang ditugaskan ke driver pada tanggal {{ $formattedDate }}.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
</div>

{{-- MONITORING STOCK OPNAME & SELISIH (MISS) --}}
<div id="opname-container">
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-{{ (isset($missedOpnames) && $missedOpnames->count() > 0) ? 'danger' : 'success' }} shadow-sm">
            <div class="card-header bg-{{ (isset($missedOpnames) && $missedOpnames->count() > 0) ? 'danger' : 'success' }} text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    @if(isset($missedOpnames) && $missedOpnames->count() > 0)
                    <i class="fas fa-exclamation-triangle me-2"></i> Peringatan: Ada Selisih Stok ({{ $isToday ? 'Hari Ini' : $formattedDate }})
                    @else
                    <i class="fas fa-check-circle me-2"></i> Opname Stok Aman ({{ $isToday ? 'Hari Ini' : $formattedDate }})
                    @endif
                </h5>
            </div>
            <div class="card-body">
                @if(isset($missedOpnames) && $missedOpnames->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu Opname</th>
                                <th>Kasir</th>
                                <th>Selisih (Miss)</th>
                                <th>Alasan / Catatan Kasir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $groupedMissed = $missedOpnames->groupBy(function($item) {
                                    return $item->product->name ?? 'Produk Dihapus';
                                });
                            @endphp

                            @foreach($groupedMissed as $productName => $items)
                            <tr class="table-secondary">
                                <td colspan="4" class="fw-bold text-dark">
                                    <i class="fas fa-box text-primary me-2"></i> {{ $productName }}
                                    <span class="badge bg-white text-dark border ms-2">{{ $items->count() }} Aktivitas</span>
                                </td>
                            </tr>
                            @foreach($items as $miss)
                            <tr>
                                <td class="ps-4"><span class="badge bg-secondary">{{ $miss->created_at->format('H:i') }}</span></td>
                                <td><strong>{{ $miss->createdBy->name ?? 'Kasir' }}</strong></td>
                                <td>
                                    <span class="badge bg-danger fs-6">{{ $miss->quantity }}</span>
                                </td>
                                <td class="text-danger fw-bold">"{{ $miss->notes }}"</td>
                            </tr>
                            @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">Belum ada laporan selisih stok (miss) dari kasir pada tanggal {{ $formattedDate }}.</p>
                @endif

                <hr>

                <h6 class="fw-bold mt-3"><i class="fas fa-box me-1"></i> Stok Realtime Saat Ini (Tidak Terpengaruh Filter):</h6>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach($realtimeStocks ?? [] as $stock)
                    <span class="badge bg-info text-dark fs-6 p-2 border">
                        {{ $stock->product->name ?? 'Item' }} : {{ $stock->current_stock }}
                    </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
</div>

{{-- ================================================================= --}}
{{-- [BARU] LOG AKTIVITAS PELUNASAN KASBON & PENGIRIMAN HARI INI       --}}
{{-- ================================================================= --}}
<div id="settlement-audit-container">
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3">
            @php
                $allLogs = $settlementLogs ?? collect();
                $logTotalCount = $allLogs->count();
                $logTotalAmount = $allLogs->sum('amount');

                // Pilah kategori log:
                // 1. Order Trip Driver: deskripsi mengandung 'Pelunasan Pengiriman'
                // 2. Kasbon Murni: deskripsi TIDAK mengandung 'Pelunasan Pengiriman' (misal: 'Pelunasan Transaksi Kasbon', 'Pelunasan Kasbon (Bulk)', dll)
                $logTripList = $allLogs->filter(function($l) {
                    return str_contains($l->description ?? '', 'Pelunasan Pengiriman');
                });
                $logKasbonList = $allLogs->filter(function($l) {
                    return !str_contains($l->description ?? '', 'Pelunasan Pengiriman');
                });

                $logKasbonCount = $logKasbonList->count();
                $logKasbonAmount = $logKasbonList->sum('amount');
                $kasbonCashAmount = $logKasbonList->filter(fn($l) => strtolower($l->payment_method ?? '') === 'cash')->sum('amount');
                $kasbonDanaAmount = $logKasbonList->filter(fn($l) => strtolower($l->payment_method ?? '') === 'dana')->sum('amount');
                $kasbonTransferAmount = $logKasbonList->filter(fn($l) => in_array(strtolower($l->payment_method ?? ''), ['transfer', 'transfer-bank', 'transfer bank', 'transfer_bank', 'bank_transfer']))->sum('amount');

                $logTripCount = $logTripList->count();
                $logTripAmount = $logTripList->sum('amount');
                $tripCashAmount = $logTripList->filter(fn($l) => strtolower($l->payment_method ?? '') === 'cash')->sum('amount');
                $tripDanaAmount = $logTripList->filter(fn($l) => strtolower($l->payment_method ?? '') === 'dana')->sum('amount');
                $tripTransferAmount = $logTripList->filter(fn($l) => in_array(strtolower($l->payment_method ?? ''), ['transfer', 'transfer-bank', 'transfer bank', 'transfer_bank', 'bank_transfer']))->sum('amount');

                $logCashAmount = $allLogs->filter(fn($l) => strtolower($l->payment_method ?? '') === 'cash')->sum('amount');
                $logDanaAmount = $allLogs->filter(fn($l) => strtolower($l->payment_method ?? '') === 'dana')->sum('amount');
                $logTransferAmount = $allLogs->filter(fn($l) => in_array(strtolower($l->payment_method ?? ''), ['transfer', 'transfer-bank', 'transfer bank', 'transfer_bank', 'bank_transfer']))->sum('amount');

                $logBackdateList = $allLogs->filter(function($l) {
                    return $l->date && $l->created_at && ($l->date->format('Y-m-d') !== $l->created_at->format('Y-m-d'));
                });
                $logBackdateCount = $logBackdateList->count();
                $logBackdateAmount = $logBackdateList->sum('amount');
            @endphp

            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-file-invoice-dollar text-success"></i> 
                        <span>Log Aktivitas Pelunasan ({{ $isToday ? 'Hari Ini' : $formattedDate }})</span>
                    </h5>
                    <small class="text-muted">Audit transaksi kasbon & trip pengiriman yang divalidasi kasir.</small>
                </div>

                {{-- FILTER TABS NAVIGATION --}}
                <div class="d-flex align-items-center gap-1 bg-light p-1 rounded-3 border" id="settlementFilterTabs">
                    <button type="button" 
                            class="btn btn-sm settlement-tab-btn active fw-bold px-3 py-1 rounded-2 shadow-sm" 
                            data-tab="kasbon" 
                            onclick="switchSettlementFilter('kasbon')"
                            style="background-color: #b91c1c; color: #ffffff; border: 1px solid #991b1b; transition: all 0.2s ease;">
                        <i class="fas fa-hand-holding-usd me-1"></i> Pelunasan Kasbon
                        <span class="badge bg-white text-danger ms-1 fw-bold">{{ $logKasbonCount }}</span>
                    </button>
                    <button type="button" 
                            class="btn btn-sm settlement-tab-btn btn-light text-secondary fw-semibold px-3 py-1 rounded-2" 
                            data-tab="trip" 
                            onclick="switchSettlementFilter('trip')"
                            style="transition: all 0.2s ease;">
                        <i class="fas fa-motorcycle me-1"></i> Order Trip Driver
                        <span class="badge bg-secondary text-white ms-1">{{ $logTripCount }}</span>
                    </button>
                    <button type="button" 
                            class="btn btn-sm settlement-tab-btn btn-light text-secondary fw-semibold px-3 py-1 rounded-2" 
                            data-tab="all" 
                            onclick="switchSettlementFilter('all')"
                            style="transition: all 0.2s ease;">
                        <i class="fas fa-list me-1"></i> Semua Aktivitas
                        <span class="badge bg-secondary text-white ms-1">{{ $logTotalCount }}</span>
                    </button>
                    @if($logBackdateCount > 0)
                    <button type="button" 
                            class="btn btn-sm settlement-tab-btn btn-light text-warning fw-semibold px-3 py-1 rounded-2" 
                            data-tab="backdate" 
                            onclick="switchSettlementFilter('backdate')"
                            style="transition: all 0.2s ease;">
                        <i class="fas fa-calendar-alt me-1"></i> Koreksi Backdate
                        <span class="badge bg-warning text-dark ms-1">{{ $logBackdateCount }}</span>
                    </button>
                    @endif
                </div>
            </div>

            {{-- SUMMARY BADGES BAR (BERUBAH OTOMATIS SESUAI TAB YANG AKTIF) --}}
            {{-- 1. Mode Kasbon (Default) --}}
            <div id="summary-badges-kasbon" class="px-4 py-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                <span class="badge py-2 px-3 fs-6" style="background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;">
                    📌 <strong>Pelunasan Kasbon:</strong> {{ $logKasbonCount }} Transaksi (Rp {{ number_format($logKasbonAmount, 0, ',', '.') }})
                </span>
                <span class="badge py-2 px-3 fs-6" style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                    💵 <strong>Kasbon CASH (Masuk Laci):</strong> Rp {{ number_format($kasbonCashAmount, 0, ',', '.') }}
                </span>
                <span class="badge py-2 px-3 fs-6" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                    📱 <strong>Kasbon DANA (Non-Laci):</strong> Rp {{ number_format($kasbonDanaAmount, 0, ',', '.') }}
                </span>
                <span class="badge py-2 px-3 fs-6" style="background-color: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe;">
                    🏦 <strong>Kasbon TRANSFER (Non-Laci):</strong> Rp {{ number_format($kasbonTransferAmount, 0, ',', '.') }}
                </span>
            </div>

            {{-- 2. Mode Trip Driver --}}
            <div id="summary-badges-trip" class="px-4 py-2 bg-light border-bottom d-none flex-wrap gap-2 align-items-center">
                <span class="badge py-2 px-3 fs-6" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                    🛵 <strong>Setoran Trip Driver:</strong> {{ $logTripCount }} Order (Rp {{ number_format($logTripAmount, 0, ',', '.') }})
                </span>
                <span class="badge py-2 px-3 fs-6" style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                    💵 <strong>Trip CASH (Masuk Laci):</strong> Rp {{ number_format($tripCashAmount, 0, ',', '.') }}
                </span>
                <span class="badge py-2 px-3 fs-6" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                    📱 <strong>Trip DANA (Non-Laci):</strong> Rp {{ number_format($tripDanaAmount, 0, ',', '.') }}
                </span>
                <span class="badge py-2 px-3 fs-6" style="background-color: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe;">
                    🏦 <strong>Trip TRANSFER (Non-Laci):</strong> Rp {{ number_format($tripTransferAmount, 0, ',', '.') }}
                </span>
            </div>

            {{-- 3. Mode Semua Aktivitas --}}
            <div id="summary-badges-all" class="px-4 py-2 bg-light border-bottom d-none flex-wrap gap-2 align-items-center">
                <span class="badge py-2 px-3 fs-6" style="background-color: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1;">
                    🧾 <strong>Total Semua Aktivitas:</strong> {{ $logTotalCount }} Transaksi (Rp {{ number_format($logTotalAmount, 0, ',', '.') }})
                </span>
                <span class="badge py-2 px-3 fs-6" style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                    💵 <strong>Total CASH (Masuk Laci):</strong> Rp {{ number_format($logCashAmount, 0, ',', '.') }}
                </span>
                <span class="badge py-2 px-3 fs-6" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                    📱 <strong>Total DANA (Non-Laci):</strong> Rp {{ number_format($logDanaAmount, 0, ',', '.') }}
                </span>
                <span class="badge py-2 px-3 fs-6" style="background-color: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe;">
                    🏦 <strong>Total TRANSFER BANK (Non-Laci):</strong> Rp {{ number_format($logTransferAmount, 0, ',', '.') }}
                </span>
            </div>

            {{-- 4. Mode Koreksi Backdate --}}
            <div id="summary-badges-backdate" class="px-4 py-2 bg-light border-bottom d-none flex-wrap gap-2 align-items-center">
                <span class="badge py-2 px-3 fs-6" style="background-color: #ffedd5; color: #9a3412; border: 1px solid #fed7aa;" title="Transaksi yang dialokasikan ke tanggal nota awal, tidak menambah laci hari ini">
                    ⚠️ <strong>Koreksi Backdate (Tgl Nota Lalu):</strong> {{ $logBackdateCount }} Transaksi (Rp {{ number_format($logBackdateAmount, 0, ',', '.') }})
                </span>
                <small class="text-muted fst-italic">Pelunasan untuk nota tanggal lampau yang uangnya dialokasikan ke tanggal nota (bukan laci hari ini).</small>
            </div>

            <div class="card-body p-0">
                @if(isset($settlementLogs) && $settlementLogs->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="settlementLogsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Jam & Tipe</th>
                                <th>Kasir</th>
                                <th>Detail Nota & Pelanggan</th>
                                <th class="text-end">Nominal</th>
                                <th class="text-center">Metode Bayar</th>
                                <th class="text-center">Status Alokasi Tanggal</th>
                                <th class="pe-4">Keterangan / Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlementLogs as $log)
                            @php
                                $pm = strtolower($log->payment_method ?? 'cash');
                                $isSameDay = $log->date && $log->created_at && ($log->date->format('Y-m-d') === $log->created_at->format('Y-m-d'));
                                $trx = $log->transaction;
                                $isTrip = str_contains($log->description ?? '', 'Pelunasan Pengiriman');
                                $isBackdate = !$isSameDay;
                                $category = $isTrip ? 'trip' : 'kasbon';
                            @endphp
                            {{-- Baris Trip di-hide secara default via style agar halaman langsung bersih tanpa flicker --}}
                            <tr class="settlement-log-row" data-category="{{ $category }}" data-backdate="{{ $isBackdate ? '1' : '0' }}" style="{{ $isTrip ? 'display: none;' : '' }}">
                                <td class="ps-4">
                                    <div class="d-flex flex-column gap-1 align-items-start">
                                        <span class="badge bg-secondary">
                                            <i class="far fa-clock me-1"></i>{{ $log->created_at ? $log->created_at->format('H:i') : '-' }} WIB
                                        </span>
                                        @if($isTrip)
                                            <span class="badge px-2 py-0 border" style="background-color: #f0fdf4; color: #166534; border-color: #bbf7d0 !important; font-size: 0.68rem;">
                                                <i class="fas fa-motorcycle me-1"></i>Driver Trip
                                            </span>
                                        @else
                                            <span class="badge px-2 py-0 border" style="background-color: #fef2f2; color: #991b1b; border-color: #fecaca !important; font-size: 0.68rem;">
                                                <i class="fas fa-hand-holding-usd me-1"></i>Kasbon
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">
                                        <i class="fas fa-user-circle text-primary me-1"></i>{{ $log->createdBy->name ?? 'Kasir' }}
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <span class="fw-bold text-primary">
                                            #{{ str_pad($log->reference_id ?? optional($trx)->id ?? 0, 6, '0', STR_PAD_LEFT) }}
                                        </span>
                                        - <strong class="text-dark">{{ optional(optional($trx)->customer)->name ?? 'Pelanggan Umum' }}</strong>
                                    </div>
                                    <small class="text-muted">
                                        Tgl Nota: <strong>{{ optional($trx)->transaction_date ? \Carbon\Carbon::parse($trx->transaction_date)->format('d/m/Y') : '-' }}</strong>
                                        @if(optional($trx)->driver)
                                            | Driver: <i class="fas fa-motorcycle text-info me-1"></i>{{ $trx->driver->name }}
                                        @endif
                                    </small>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    Rp {{ number_format($log->amount, 0, ',', '.') }}
                                </td>
                                <td class="text-center">
                                    @if($pm === 'cash')
                                        <span class="badge bg-success px-2 py-1 text-white">
                                            <i class="fas fa-money-bill-wave me-1"></i>CASH (Laci)
                                        </span>
                                    @elseif($pm === 'dana')
                                        <span class="badge px-2 py-1" style="background-color: #0284c7; color: white;">
                                            <i class="fas fa-mobile-screen me-1"></i>DANA (Non-Laci)
                                        </span>
                                    @elseif(in_array($pm, ['transfer', 'transfer-bank', 'transfer bank', 'transfer_bank', 'bank_transfer']))
                                        <span class="badge px-2 py-1" style="background-color: #6366f1; color: white;">
                                            <i class="fas fa-building-columns me-1"></i>TRANSFER BANK (Non-Laci)
                                        </span>
                                    @else
                                        <span class="badge bg-secondary px-2 py-1 text-white">
                                            {{ strtoupper($pm) }} (Non-Laci)
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($isSameDay)
                                        @if($pm === 'cash')
                                            <span class="badge badge-soft-success px-2 py-1" style="background-color: #dcfce7 !important; color: #15803d !important; border: 1px solid #86efac !important;">
                                                <i class="fas fa-check-circle me-1"></i> Masuk Laci Kasir Hari Ini
                                            </span>
                                        @else
                                            <span class="badge badge-soft-info px-2 py-1" style="background-color: #e0f2fe !important; color: #0369a1 !important; border: 1px solid #7dd3fc !important;">
                                                <i class="fas fa-university me-1"></i> Masuk Rekening/Non-Tunai Hari Ini
                                            </span>
                                        @endif
                                    @else
                                        <span class="badge badge-soft-warning px-2 py-1 fw-bold" style="background-color: #fef3c7 !important; color: #92400e !important; border: 1px solid #fde68a !important;" title="Uang dialokasikan ke tanggal nota awal, tidak menambah laci hari ini">
                                            <i class="fas fa-triangle-exclamation text-warning me-1"></i> ⚠️ Koreksi ke Tgl Nota ({{ $log->date ? $log->date->format('d/m/Y') : '-' }})
                                        </span>
                                        <div class="small text-muted mt-1" style="font-size: 0.72rem;">Oleh: <strong>{{ $log->createdBy->name ?? 'Kasir' }}</strong></div>
                                    @endif
                                </td>
                                <td class="pe-4">
                                    <small class="text-muted fst-italic">{{ $log->description ?? '-' }}</small>
                                </td>
                            </tr>
                            @endforeach
                            <tr id="settlement-empty-filter-row" style="{{ $logKasbonCount === 0 ? '' : 'display: none;' }}">
                                <td colspan="7" class="py-4 text-center text-muted">
                                    <i class="fas fa-hand-holding-usd fa-2x mb-2 text-secondary opacity-50"></i>
                                    <h6 class="fw-bold text-secondary mb-1" id="settlement-empty-filter-title">Tidak Ada Pelunasan Kasbon</h6>
                                    <p class="small mb-0" id="settlement-empty-filter-desc">Belum ada transaksi kasbon/piutang yang dilunasi pada tanggal {{ $formattedDate }}.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @else
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-file-invoice-dollar fa-3x mb-2 text-secondary opacity-50"></i>
                    <h6 class="fw-bold text-secondary">Belum Ada Pelunasan Kasbon / Pengiriman</h6>
                    <p class="small mb-0">Tidak ada aktivitas pelunasan yang diproses kasir pada tanggal {{ $formattedDate }}.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
</div>

{{-- MONITORING HISTORY PECAH BALL --}}
<div id="pecah-ball-container">
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-primary shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exchange-alt me-2"></i> Riwayat Pecah Ball / Repacking ({{ $isToday ? 'Hari Ini' : $formattedDate }})
                </h5>
            </div>
            <div class="card-body">
                @if(isset($breakUnitHistory) && count($breakUnitHistory) > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Kasir (Petugas)</th>
                                <th>Karung/Ball Dipecah</th>
                                <th>Menjadi (Hasil Kemasan)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($breakUnitHistory as $history)
                            <tr>
                                <td><span class="badge bg-secondary">{{ $history['time']->format('H:i') }}</span></td>
                                <td><strong>{{ $history['kasir'] }}</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-danger fs-6 me-2">- {{ $history['source_qty'] }}</span>
                                        <span class="fw-bold text-dark">{{ $history['source_product'] }}</span>
                                    </div>
                                </td>
                                <td>
                                    <ul class="list-unstyled mb-0">
                                        @foreach($history['targets'] as $target)
                                        <li class="mb-1">
                                            <i class="fas fa-arrow-right text-success me-2"></i>
                                            <span class="badge bg-success fs-6 me-1">+ {{ $target->quantity }}</span>
                                            {{ $target->product->name ?? 'Produk Dihapus' }}
                                        </li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">Belum ada aktivitas pecah karung/ball oleh kasir pada tanggal {{ $formattedDate }}.</p>
                @endif
            </div>
        </div>
    </div>
</div>
</div>

{{-- MODAL POP-UP DETAIL PENGIRIMAN PER DRIVER --}}
<div id="driver-modals-wrapper">
@if(isset($driverRecap) && count($driverRecap) > 0)
@foreach($driverRecap as $d)
<div class="modal fade" id="modalDriverDetail_{{ $d['driver_id'] }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fas fa-motorcycle text-warning me-2"></i> Rincian Pengiriman Driver: <span class="text-warning">{{ $d['driver_name'] }}</span>
                    </h5>
                    <small class="text-white-50">
                        Tanggal: <strong>{{ $formattedDate }}</strong> | Total Muatan: <strong>{{ $d['total_qty'] }} Ball</strong> | Total Tagihan: <strong>Rp {{ number_format($d['total_amount'], 0, ',', '.') }}</strong>
                    </small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                {{-- Kartu Ringkasan Atas Modal --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded border text-center shadow-sm">
                            <div class="text-muted small fw-semibold">TOTAL RITASE / TRIP</div>
                            <h4 class="fw-bold mb-0 text-dark">{{ $d['total_trips'] }} Trip</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded border text-center shadow-sm">
                            <div class="text-muted small fw-semibold">TOTAL TOKO DIKIRIM</div>
                            <h4 class="fw-bold mb-0 text-primary">{{ $d['total_orders'] }} Toko</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded border text-center shadow-sm">
                            <div class="text-muted small fw-semibold">TOTAL MUATAN FISIK</div>
                            <h4 class="fw-bold mb-0 text-success">{{ $d['total_qty'] }} Ball</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded border text-center shadow-sm">
                            <div class="text-muted small fw-semibold">TOTAL NILAI TRANSAKSI</div>
                            <h4 class="fw-bold mb-0 text-danger">Rp {{ number_format($d['total_amount'], 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>

                {{-- Perulangan per Trip / Surat Jalan --}}
                @foreach($d['trips'] as $tIdx => $trip)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-dark fs-6 px-3 py-2">
                                <i class="fas fa-file-invoice text-warning me-1"></i> Surat Jalan: {{ $trip['trip_code'] }}
                            </span>
                            <span class="badge bg-secondary">
                                <i class="fas fa-clock me-1"></i> {{ $trip['time']->format('H:i') }} WIB
                            </span>
                            <span class="badge bg-primary">
                                Muatan: {{ $trip['total_qty'] }} Ball
                            </span>
                            @foreach($trip['product_breakdown'] as $pName => $qty)
                            <span class="badge bg-light text-dark border">{{ $pName }}: {{ $qty }}</span>
                            @endforeach
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark me-2">Subtotal Trip: <span class="text-danger">Rp {{ number_format($trip['total_amount'], 0, ',', '.') }}</span></span>
                            @if($trip['is_batch'])
                            <a href="{{ route('kasir.pos.deliveryReceipt', $trip['trip_code']) }}" target="_blank" class="btn btn-sm btn-outline-warning text-dark bg-warning bg-opacity-25 fw-bold">
                                <i class="fas fa-print me-1"></i> Cetak Surat Jalan
                            </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 50px;">No</th>
                                        <th>No. Struk</th>
                                        <th>Nama Pelanggan / Toko</th>
                                        <th>Rincian Barang</th>
                                        <th class="text-end">Nilai Tagihan</th>
                                        <th>Metode & Status Bayar</th>
                                        <th class="pe-3">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($trip['transactions'] as $idx => $trx)
                                    <tr>
                                        <td class="ps-3 fw-bold text-muted">{{ $idx + 1 }}</td>
                                        <td class="fw-bold text-primary">#{{ str_pad($trx->id, 6, '0', STR_PAD_LEFT) }}</td>
                                        <td>
                                            <strong class="text-dark fs-6">{{ $trx->customer->name ?? 'Pelanggan Umum' }}</strong>
                                            @if($trx->customer && $trx->customer->phone)
                                            <br><small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $trx->customer->phone }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <ul class="list-unstyled mb-0 small">
                                                @foreach($trx->details as $item)
                                                @php
                                                    $pName = $item->product->name ?? 'Produk';
                                                    $isOngkir = str_contains(strtolower($pName), 'ongkos kirim') || str_contains(strtolower($pName), 'ongkir');
                                                @endphp
                                                <li>
                                                    • <strong>{{ $pName }}</strong>
                                                    @if($isOngkir)
                                                    <span class="badge bg-warning bg-opacity-25 text-dark border ms-1">Biaya Kirim</span>
                                                    @else
                                                    <span class="badge bg-light text-dark border ms-1">{{ $item->quantity }} {{ $item->product->unit ?? 'Ball' }}</span>
                                                    @endif
                                                </li>
                                                @endforeach
                                            </ul>
                                        </td>
                                        <td class="text-end fw-bold text-dark fs-6">
                                            Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                                        </td>
                                        <td>
                                            @if($trx->payment_status === 'paid')
                                                @php
                                                    $pm = strtolower($trx->payment_method ?? 'cash');
                                                    $badgeClass = 'bg-success';
                                                    $icon = '💵 Cash';
                                                    if (str_contains($pm, 'transfer')) {
                                                        $badgeClass = 'bg-primary';
                                                        $icon = '🏦 Transfer';
                                                    } elseif (str_contains($pm, 'dana')) {
                                                        $badgeClass = 'bg-info text-dark';
                                                        $icon = '📱 DANA';
                                                    }
                                                @endphp
                                                <span class="badge {{ $badgeClass }} px-2 py-1">
                                                    {{ $icon }} (Lunas)
                                                </span>
                                            @else
                                                <span class="badge bg-danger text-white px-2 py-1" style="background-color: #dc3545 !important; color: #ffffff !important;">
                                                    ⚠️ Kasbon / Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td class="pe-3 small text-muted fst-italic">
                                            {{ $trx->notes ?: '-' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="modal-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="text-muted small fw-bold">Rekapitulasi Pembayaran Driver:</span>
                    @foreach($d['payment_methods_summary'] as $mName => $mAmount)
                    <span class="badge bg-light text-dark border ms-1 py-1 px-2">
                        {{ $mName }}: <strong>Rp {{ number_format($mAmount, 0, ',', '.') }}</strong>
                    </span>
                    @endforeach
                </div>
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endif
</div>

{{-- ================================================================= --}}
{{-- MODAL DETAIL CASH ON HAND PER KASIR                              --}}
{{-- ================================================================= --}}
<div id="cashier-modals-wrapper">
@foreach($cashierMonitoring ?? [] as $c)
<div class="modal fade" id="modalCashierDetail_{{ $c['user_id'] }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-3" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fas fa-user-circle text-warning me-2"></i> Rincian Keuangan Kasir: <span class="text-warning">{{ $c['name'] }}</span>
                    </h5>
                    <small class="text-white-50">Tanggal: <strong>{{ $formattedDate }}</strong> | Saldo Laci: <strong class="text-success">Rp {{ number_format($c['physical_drawer'], 0, ',', '.') }}</strong> | Laba Shift: <strong class="{{ $c['shift_net_profit'] >= 0 ? 'text-info' : 'text-danger' }}">{{ $c['shift_net_profit'] >= 0 ? '+' : '' }}Rp {{ number_format($c['shift_net_profit'], 0, ',', '.') }}</strong></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                {{-- 4 Stat Boxes Atas Modal --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white rounded border shadow-sm h-100">
                            <div class="text-muted small fw-semibold text-center">KAS TUNAI MASUK</div>
                            <h5 class="fw-bold mb-2 text-success text-center">Rp {{ number_format($c['total_cash_in'], 0, ',', '.') }}</h5>
                            <div class="border-top pt-2" style="font-size: 0.72rem; line-height: 1.4;">
                                <div class="d-flex justify-content-between text-muted">
                                    <span>Penjualan Tunai Meja:</span>
                                    <strong class="text-dark">Rp {{ number_format($c['cash_sales'], 0, ',', '.') }}</strong>
                                </div>
                                <div class="d-flex justify-content-between text-muted">
                                    <span>Pelunasan & Driver (Tunai):</span>
                                    <strong class="text-dark">Rp {{ number_format($c['pelunasan_cash'], 0, ',', '.') }}</strong>
                                </div>
                                <div class="d-flex justify-content-between text-muted">
                                    <span>Modal Awal Laci:</span>
                                    <strong class="text-dark">Rp {{ number_format($c['opening_cash'] ?? 0, 0, ',', '.') }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white rounded border text-center shadow-sm">
                            <div class="text-muted small fw-semibold">TOTAL KAS KELUAR</div>
                            <h5 class="fw-bold mb-0 text-danger">- Rp {{ number_format($c['expenses'], 0, ',', '.') }}</h5>
                            <small class="text-muted" style="font-size: 0.7rem;">{{ count($c['expenses_list']) }} Catatan Pengeluaran</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white rounded border text-center shadow-sm" style="border: 2px solid #10b981 !important;">
                            <div class="text-success small fw-bold">SALDO KAS LACI (FISIK)</div>
                            <h5 class="fw-bold mb-0 text-success">Rp {{ number_format($c['physical_drawer'], 0, ',', '.') }}</h5>
                            <small class="text-muted" style="font-size: 0.7rem;">Uang Kas Fisik di Laci</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white rounded border text-center shadow-sm" style="border: 2px solid #3b82f6 !important;">
                            <div class="text-primary small fw-bold">ESTIMASI LABA SHIFT</div>
                            <h5 class="fw-bold mb-0 {{ $c['shift_net_profit'] >= 0 ? 'text-primary' : 'text-danger' }}">
                                {{ $c['shift_net_profit'] >= 0 ? '+' : '' }}Rp {{ number_format($c['shift_net_profit'], 0, ',', '.') }}
                            </h5>
                            <small class="text-muted" style="font-size: 0.7rem;">Omset - COGS - Ops Murni</small>
                        </div>
                    </div>
                </div>

                {{-- Tabel 1: Rincian Modal Es (COGS) Seluruh Produk Terjual (Tunai, Non-Tunai & Kasbon) --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-boxes-stacked text-primary me-2"></i> Rincian Modal Es (COGS) Seluruh Produk Terjual (Tunai, Non-Tunai & Kasbon)
                        </h6>
                        <span class="badge bg-danger text-white">Total COGS: Rp {{ number_format($c['cogs'], 0, ',', '.') }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Nama Produk</th>
                                        <th class="text-center">Jumlah Keluar</th>
                                        <th class="text-end">Modal / HPP Satuan</th>
                                        <th class="text-end pe-3">Subtotal Modal (COGS)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($c['items_sold'] as $item)
                                    <tr>
                                        <td class="ps-3 fw-bold text-dark">{{ $item['name'] }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border px-2 py-1">{{ $item['qty'] }} {{ $item['unit'] }}</span>
                                        </td>
                                        <td class="text-end text-muted">Rp {{ number_format($item['cost_price'], 0, ',', '.') }}</td>
                                        <td class="text-end pe-3 fw-bold text-danger">Rp {{ number_format($item['total_cogs'], 0, ',', '.') }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Tidak ada produk es terjual pada tanggal ini.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Tabel 2: Rincian Pengeluaran dari Laci Kasir & Non-Laci --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-receipt text-warning me-2"></i> Rincian Pengeluaran dari Laci Kasir
                        </h6>
                        <span class="badge bg-warning text-dark"><i class="fas fa-cash-register me-1"></i>Total Kas Keluar Laci: Rp {{ number_format($c['expenses'], 0, ',', '.') }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Kategori & Jenis Biaya</th>
                                        <th>Keterangan / Deskripsi</th>
                                        <th class="text-end pe-3">Jumlah Pengeluaran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($c['expenses_list'] as $exp)
                                    @php
                                        $isCogs = (bool) (optional($exp->category)->is_cogs);
                                        $affectsDrawer = ($exp->affects_drawer ?? 1) == 1;
                                    @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <span class="badge {{ $isCogs ? 'bg-primary' : 'bg-warning text-dark' }}">
                                                    {{ $exp->category->name ?? ($isCogs ? 'Refill Es Batu' : 'Operasional') }}
                                                </span>
                                                <span class="badge {{ $isCogs ? 'badge-soft-info' : 'badge-soft-secondary' }}" style="font-size: 0.72rem; {{ $isCogs ? 'background-color: #e0f2fe !important; color: #0369a1 !important; border: 1px solid #7dd3fc !important;' : 'background-color: #f1f5f9 !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;' }}">
                                                    {{ $isCogs ? 'Pembelian Stok (HPP)' : 'Beban Operasional Murni' }}
                                                </span>
                                                @if(!$affectsDrawer)
                                                <span class="badge bg-light text-muted border" style="font-size: 0.7rem;" title="Dibayar menggunakan kas kemarin atau titipan bos, tidak memotong laci kasir hari ini">
                                                    <i class="fas fa-shield-alt me-1"></i>Uang Bos / Non-Laci
                                                </span>
                                                @else
                                                <span class="badge badge-soft-success" style="font-size: 0.7rem; background-color: #dcfce7 !important; color: #15803d !important; border: 1px solid #86efac !important;">
                                                    <i class="fas fa-cash-register me-1"></i>Potong Laci
                                                </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-dark small">{{ $exp->description ?? '-' }}</td>
                                        <td class="text-end pe-3 fw-bold {{ $affectsDrawer ? 'text-danger' : 'text-muted' }}">
                                            Rp {{ number_format($exp->amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Tidak ada pengeluaran kas yang dicatat oleh kasir ini.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- [BARU] Bar Rincian Lengkap: Penjualan Non-Tunai, Pelunasan Non-Tunai & Kasbon Baru --}}
                <div class="p-3 bg-white rounded border shadow-sm mb-0">
                    <div class="row g-2 align-items-center">
                        {{-- 1. Penjualan Non-Tunai Hari Ini --}}
                        <div class="col-md-4">
                            <div class="small text-muted fw-bold mb-1"><i class="fas fa-credit-card text-primary me-1"></i>Penjualan Non-Tunai Hari Ini:</div>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge py-1 px-2" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 0.75rem;">
                                    📱 DANA: <strong>Rp {{ number_format($c['non_cash_dana'] ?? 0, 0, ',', '.') }}</strong>
                                </span>
                                <span class="badge py-1 px-2" style="background-color: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; font-size: 0.75rem;">
                                    🏦 Transfer: <strong>Rp {{ number_format($c['non_cash_transfer'] ?? 0, 0, ',', '.') }}</strong>
                                </span>
                            </div>
                        </div>

                        {{-- 2. Pelunasan Kasbon via Non-Tunai (Tidak Masuk Laci Kasir) --}}
                        <div class="col-md-5">
                            <div class="small text-muted fw-bold mb-1">
                                <i class="fas fa-rotate text-info me-1"></i>Pelunasan Kasbon (DANA / Transfer):
                            </div>
                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                <span class="badge py-1 px-2" style="background-color: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; font-size: 0.75rem;">
                                    Total: <strong>Rp {{ number_format($c['pelunasan_non_cash_total'] ?? 0, 0, ',', '.') }}</strong>
                                </span>
                                <span class="small text-muted" style="font-size: 0.72rem;">
                                    (DANA: Rp {{ number_format($c['pelunasan_dana'] ?? 0, 0, ',', '.') }} | TF: Rp {{ number_format($c['pelunasan_transfer'] ?? 0, 0, ',', '.') }})
                                </span>
                            </div>
                        </div>

                        {{-- 3. Kasbon Baru Hari Ini (Belum Bayar) --}}
                        <div class="col-md-3 text-md-end">
                            <div class="small text-muted fw-bold mb-1"><i class="fas fa-clock text-danger me-1"></i>Kasbon Baru Hari Ini:</div>
                            <span class="badge py-1 px-2 {{ ($c['kasbon'] ?? 0) > 0 ? 'badge-soft-danger' : 'bg-light text-muted border' }}" style="font-size: 0.78rem; {{ ($c['kasbon'] ?? 0) > 0 ? 'background-color: #fee2e2 !important; color: #b91c1c !important; border: 1px solid #fca5a5 !important;' : '' }}">
                                <strong>Rp {{ number_format($c['kasbon'] ?? 0, 0, ',', '.') }}</strong>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white py-2">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endforeach
</div>

{{-- Main Content Row --}}
<div class="row g-4">
    {{-- Smart Fund Allocation (UPDATED WITH CUSTOM LEGEND) --}}
    <div class="col-lg-5">
        <div class="dashboard-card">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fas fa-chart-pie"></i>
                    Alokasi Dana (Berdasarkan Pengaturan)
                </h4>
                <p class="card-subtitle">Dari keuntungan bersih: <strong>Rp {{ number_format($netProfitThisMonth ?? 0, 0, ',', '.') }}</strong></p>
            </div>
            <div class="card-body">
                <div class="fund-allocation-wrapper">
                    {{-- Chart Container --}}
                    <div class="chart-container-wrapper">
                        <div class="chart-container" style="height: 300px; position: relative;">
                            <canvas id="fundAllocationChart"></canvas>
                            <div class="chart-center-text">
                                <div class="center-amount">Rp {{ number_format($netProfitThisMonth ?? 0, 0, ',', '.') }}</div>
                                <div class="center-label">Total Dana</div>
                            </div>
                        </div>
                    </div>

                    {{-- ✅ CUSTOM LEGEND CONTAINER (BARU) --}}
                    <div id="fundAllocationLegend" class="fund-legend-container"></div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('admin.fund-allocation.settings') }}" class="btn btn-gold">
                    <i class="fas fa-cog"></i> Atur Alokasi
                </a>
            </div>
        </div>
    </div>

    {{-- Comprehensive Analytics --}}
    <div class="col-lg-7">
        <div class="dashboard-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Performa 6 Bulan Terakhir
                        </h4>
                        <p class="card-subtitle">Tren penjualan dan keuntungan</p>
                    </div>
                    <div class="chart-controls">
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="chartPeriod" id="monthly" checked>
                            <label class="btn btn-outline-primary btn-sm" for="monthly">Bulanan</label>

                            <input type="radio" class="btn-check" name="chartPeriod" id="weekly">
                            <label class="btn btn-outline-primary btn-sm" for="weekly">Mingguan</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-wrapper" style="height: 300px; position: relative;">
                    <canvas id="comprehensiveChart"></canvas>
                </div>
                <div class="chart-stats">
                    <div class="stat-item">
                        <div class="stat-label">Rata-rata Penjualan</div>
                        <div class="stat-value text-primary">
                            Rp {{ number_format($avgSales ?? 0, 2, ',', '.') }} Jt
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Rata-rata Keuntungan</div>
                        <div class="stat-value text-success">
                            Rp {{ number_format($avgProfit ?? 0, 2, ',', '.') }} Jt
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Margin Keuntungan</div>
                        <div class="stat-value text-info">
                            {{ $profitMargin ?? 0 }}%
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-primary">
                    <i class="fas fa-chart-pie"></i> Lihat Laporan Lengkap
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Analytics & Activities Row --}}
<div class="row g-4 mt-4">
    {{-- Quick Actions --}}
    <div class="col-lg-8">
        <div class="dashboard-card">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fas fa-bolt"></i>
                    Aksi Cepat
                </h4>
                <p class="card-subtitle">Navigasi cepat ke fitur utama sistem</p>
            </div>
            <div class="card-body">
                <div class="quick-actions-grid">
                    <a href="{{ route('admin.products.index') }}" class="quick-action-item">
                        <div class="action-icon bg-primary"><i class="fas fa-box"></i></div>
                        <div class="action-content">
                            <h5>Master Data</h5>
                            <p>Kelola produk & kategori</p>
                        </div>
                    </a>
                    <a href="{{ route('admin.inventory.index') }}" class="quick-action-item">
                        <div class="action-icon bg-info"><i class="fas fa-warehouse"></i></div>
                        <div class="action-content">
                            <h5>Inventaris</h5>
                            <p>Monitoring stok barang</p>
                        </div>
                    </a>
                    <a href="{{ route('admin.transactions.index') }}" class="quick-action-item">
                        <div class="action-icon bg-success"><i class="fas fa-cash-register"></i></div>
                        <div class="action-content">
                            <h5>Transaksi</h5>
                            <p>Kelola penjualan & pembelian</p>
                        </div>
                    </a>
                    <a href="{{ route('admin.financial.index') }}" class="quick-action-item">
                        <div class="action-icon bg-danger"><i class="fas fa-chart-pie"></i></div>
                        <div class="action-content">
                            <h5>Finansial</h5>
                            <p>Laporan keuangan</p>
                        </div>
                    </a>
                    <a href="{{ route('admin.reports.index') }}" class="quick-action-item">
                        <div class="action-icon bg-warning"><i class="fas fa-file-chart-line"></i></div>
                        <div class="action-content">
                            <h5>Laporan</h5>
                            <p>Analisis & reporting</p>
                        </div>
                    </a>
                    <a href="{{ route('admin.fund-allocation.index') }}" class="quick-action-item">
                        <div class="action-icon bg-gold"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="action-content">
                            <h5>Alokasi Dana</h5>
                            <p>Manajemen dana usaha</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Activities --}}
    <div class="col-lg-4">
        <div class="dashboard-card">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fas fa-clock"></i>
                    Aktivitas Terbaru
                </h4>
                <p class="card-subtitle">Log aktivitas sistem</p>
            </div>
            <div class="card-body">
                <div class="activity-timeline">
                    @forelse($recentActivities ?? [] as $log)
                    <div class="activity-item">
                        <div class="activity-avatar">
                            <img src="{{ $log->user->avatar ?? '/images/default-avatar.png' }}" alt="User Avatar">
                        </div>
                        <div class="activity-content">
                            <div class="activity-header">
                                <h6 class="activity-user">{{ $log->user->name ?? 'Sistem' }}</h6>
                                <span class="activity-time">{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="activity-description">
                                <span class="activity-action">{{ $log->action }}</span>
                                <span class="activity-module">{{ $log->module }}</span>
                            </div>
                            @if($log->details)
                            <div class="activity-details">
                                <i class="fas fa-info-circle"></i>
                                {{ \Illuminate\Support\Str::limit($log->details, 60) }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                        <div class="empty-text">
                            <h6>Tidak ada aktivitas terbaru</h6>
                            <p>Aktivitas sistem akan muncul di sini</p>
                        </div>
                    </div>
                    @endforelse
                </div>
            </div>
            <div class="card-footer">
                <a href="#" class="btn btn-outline-primary btn-sm w-100">
                    <i class="fas fa-history"></i> Lihat Semua Aktivitas
                </a>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

{{-- PEMANGGILAN CSS & JS DENGAN VITE --}}
@push('styles')
@vite(['resources/css/admin/dashboard.css'])
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

{{-- Injeksi Data PHP ke Global Window Object agar bisa dibaca file JS eksternal --}}
<script>
    window.dashboardData = {
        fundAllocation: @json($fundAllocationData ?? []),
        monthlyPerformance: @json($monthlyPerformanceData ?? null),
        weeklyPerformance: @json($weeklyPerformanceData ?? null)
    };
</script>

{{-- Panggil File JS Utama --}}
@vite(['resources/js/admin/dashboard.js'])

{{-- SCRIPT AUTO-REFRESH REALTIME 60 DETIK (SEAMLESS TANPA WHITE-FLASH) --}}
<script>
    let reloadCountdownSeconds = 60;
    let reloadTimerInterval = null;
    let isRefreshing = false;

    function startReloadCountdown() {
        clearInterval(reloadTimerInterval);
        reloadCountdownSeconds = 60;
        updateCountdownUI();

        reloadTimerInterval = setInterval(() => {
            reloadCountdownSeconds--;
            updateCountdownUI();

            if (reloadCountdownSeconds <= 0) {
                reloadCountdownSeconds = 60;
                autoFetchDashboardData();
            }
        }, 1000);
    }

    function updateCountdownUI() {
        const el = document.getElementById('reloadCountdown');
        if (el) {
            el.textContent = reloadCountdownSeconds + 's';
        }
    }

    async function autoFetchDashboardData(manual = false) {
        if (isRefreshing) return;
        
        // Cek jika user sedang membuka modal Bootstrap (agar tidak tertutup/rusak)
        const activeModal = document.querySelector('.modal.show');
        if (activeModal && !manual) {
            // Tunda 15 detik lagi jika modal sedang aktif dibaca user
            reloadCountdownSeconds = 15;
            return;
        }

        isRefreshing = true;
        const spinner = document.getElementById('autoReloadSpinner');
        const liveDot = document.getElementById('livePulseDot');
        const manualIcon = document.getElementById('manualRefreshIcon');

        if (spinner) spinner.style.display = 'inline-block';
        if (liveDot) liveDot.style.display = 'none';
        if (manualIcon) manualIcon.classList.add('fa-spin');

        try {
            const url = new URL(window.location.href);
            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (response.ok) {
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // 1. Perbarui 4 Kartu KPI Atas (Omset, Kas Riil, Cold Storage, Laba)
                const newKpi = doc.querySelector('#dashboard-kpi-row');
                const currKpi = document.querySelector('#dashboard-kpi-row');
                if (newKpi && currKpi) {
                    currKpi.innerHTML = newKpi.innerHTML;
                }

                // 2. Perbarui Container Prioritas Cash on Hand Real Kasir
                const newCashier = doc.querySelector('#cashier-priority-container');
                const currCashier = document.querySelector('#cashier-priority-container');
                if (newCashier && currCashier) {
                    currCashier.innerHTML = newCashier.innerHTML;
                    if (typeof applyCashierFilter === 'function') {
                        applyCashierFilter(currentCashierFilter);
                    }
                }

                // 3. Perbarui Modal Kasir (Hanya jika tidak ada modal yang sedang aktif)
                if (!activeModal) {
                    const newCashierModals = doc.querySelector('#cashier-modals-wrapper');
                    const currCashierModals = document.querySelector('#cashier-modals-wrapper');
                    if (newCashierModals && currCashierModals) {
                        currCashierModals.innerHTML = newCashierModals.innerHTML;
                    }
                }

                // 4. Perbarui Monitoring Driver Container
                const newDriver = doc.querySelector('#driver-monitoring-container');
                const currDriver = document.querySelector('#driver-monitoring-container');
                if (newDriver && currDriver) {
                    currDriver.innerHTML = newDriver.innerHTML;
                }

                // 3. Perbarui Modal Driver (Hanya jika tidak ada modal yang sedang aktif)
                if (!activeModal) {
                    const newModals = doc.querySelector('#driver-modals-wrapper');
                    const currModals = document.querySelector('#driver-modals-wrapper');
                    if (newModals && currModals) {
                        currModals.innerHTML = newModals.innerHTML;
                    }
                }

                // 4. Perbarui Opname Stok & Realtime Stock Badges
                const newOpname = doc.querySelector('#opname-container');
                const currOpname = document.querySelector('#opname-container');
                if (newOpname && currOpname) {
                    currOpname.innerHTML = newOpname.innerHTML;
                }

                // 5. Perbarui Riwayat Pecah Ball
                const newPecah = doc.querySelector('#pecah-ball-container');
                const currPecah = document.querySelector('#pecah-ball-container');
                if (newPecah && currPecah) {
                    currPecah.innerHTML = newPecah.innerHTML;
                }

                // 6. Perbarui Settlement Audit Log
                const newSettlement = doc.querySelector('#settlement-audit-container');
                const currSettlement = document.querySelector('#settlement-audit-container');
                if (newSettlement && currSettlement) {
                    currSettlement.innerHTML = newSettlement.innerHTML;
                    if (typeof applySettlementFilter === 'function') {
                        applySettlementFilter(currentSettlementFilter);
                    }
                }

                // Efek denyut hijau tanda data baru sukses masuk
                if (liveDot) {
                    liveDot.style.boxShadow = '0 0 15px #00ff00';
                    setTimeout(() => {
                        liveDot.style.boxShadow = '0 0 8px #28a745';
                    }, 1500);
                }
            }
        } catch (err) {
            console.warn('Auto refresh error:', err);
        } finally {
            isRefreshing = false;
            if (spinner) spinner.style.display = 'none';
            if (liveDot) liveDot.style.display = 'inline-block';
            if (manualIcon) manualIcon.classList.remove('fa-spin');
            reloadCountdownSeconds = 60;
            updateCountdownUI();
        }
    }

    function manualTriggerRefresh() {
        autoFetchDashboardData(true);
    }

    // ==========================================
    // LOGIKA FILTER CEPAT KASIR: AKTIF / STANDBY / SEMUA
    // ==========================================
    let currentCashierFilter = 'active';

    window.switchCashierFilter = function(filter) {
        currentCashierFilter = filter;
        applyCashierFilter(filter);
    };

    window.applyCashierFilter = function(filter) {
        const btnGroup = document.getElementById('cashierFilterBtnGroup');
        if (btnGroup) {
            const buttons = btnGroup.querySelectorAll('.cashier-filter-btn');
            buttons.forEach(btn => {
                if (btn.getAttribute('data-filter') === filter) {
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-primary', 'active');
                } else {
                    btn.classList.remove('btn-primary', 'active');
                    btn.classList.add('btn-outline-secondary');
                }
            });
        }

        const cards = document.querySelectorAll('.cashier-card-col');
        let visibleCount = 0;
        let activeVisible = 0;
        let standbyVisible = 0;

        cards.forEach(card => {
            const status = card.getAttribute('data-status');
            if (filter === 'all') {
                card.style.display = '';
                visibleCount++;
            } else if (filter === 'active') {
                if (status === 'active') {
                    card.style.display = '';
                    visibleCount++;
                    activeVisible++;
                } else {
                    card.style.display = 'none';
                }
            } else if (filter === 'standby') {
                if (status === 'standby') {
                    card.style.display = '';
                    visibleCount++;
                    standbyVisible++;
                } else {
                    card.style.display = 'none';
                }
            }
        });

        const alertNoActive = document.getElementById('cashier-no-active-alert');
        if (alertNoActive) {
            if (filter === 'active' && activeVisible === 0) {
                alertNoActive.classList.remove('d-none');
            } else {
                alertNoActive.classList.add('d-none');
            }
        }

        const alertNoStandby = document.getElementById('cashier-no-standby-alert');
        if (alertNoStandby) {
            if (filter === 'standby' && standbyVisible === 0) {
                alertNoStandby.classList.remove('d-none');
            } else {
                alertNoStandby.classList.add('d-none');
            }
        }

        const info = document.getElementById('cashierFilterInfo');
        if (info) {
            if (filter === 'active') {
                info.innerHTML = `Menampilkan <strong>${visibleCount}</strong> kasir aktif bertugas`;
            } else if (filter === 'standby') {
                info.innerHTML = `Menampilkan <strong>${visibleCount}</strong> kasir standby (belum ada transaksi)`;
            } else {
                info.innerHTML = `Menampilkan semua <strong>${visibleCount}</strong> kasir terdaftar`;
            }
        }
    };

    // ==========================================
    // LOGIKA FILTER TAB AUDIT LOG PELUNASAN KASBON & TRIP
    // ==========================================
    let currentSettlementFilter = 'kasbon';

    window.switchSettlementFilter = function(tab) {
        currentSettlementFilter = tab;
        applySettlementFilter(tab);
    };

    window.applySettlementFilter = function(tab) {
        // 1. Update button tab active state
        const tabBtns = document.querySelectorAll('.settlement-tab-btn');
        tabBtns.forEach(btn => {
            const btnTab = btn.getAttribute('data-tab');
            if (btnTab === tab) {
                btn.classList.remove('btn-light', 'text-secondary', 'text-muted');
                btn.classList.add('active', 'shadow-sm');
                if (tab === 'kasbon') {
                    btn.style.setProperty('background-color', '#b91c1c', 'important');
                    btn.style.setProperty('color', '#ffffff', 'important');
                    btn.style.setProperty('border', '1px solid #991b1b', 'important');
                } else if (tab === 'trip') {
                    btn.style.setProperty('background-color', '#0284c7', 'important');
                    btn.style.setProperty('color', '#ffffff', 'important');
                    btn.style.setProperty('border', '1px solid #0369a1', 'important');
                } else if (tab === 'backdate') {
                    btn.style.setProperty('background-color', '#d97706', 'important');
                    btn.style.setProperty('color', '#ffffff', 'important');
                    btn.style.setProperty('border', '1px solid #b45309', 'important');
                } else {
                    btn.style.setProperty('background-color', '#334155', 'important');
                    btn.style.setProperty('color', '#ffffff', 'important');
                    btn.style.setProperty('border', '1px solid #1e293b', 'important');
                }
            } else {
                btn.classList.remove('active', 'shadow-sm');
                btn.style.removeProperty('background-color');
                btn.style.removeProperty('color');
                btn.style.removeProperty('border');
                btn.classList.add('btn-light', 'text-secondary');
            }
        });

        // 2. Toggle summary badge containers
        const summaryContainers = {
            'kasbon': document.getElementById('summary-badges-kasbon'),
            'trip': document.getElementById('summary-badges-trip'),
            'all': document.getElementById('summary-badges-all'),
            'backdate': document.getElementById('summary-badges-backdate'),
        };
        Object.keys(summaryContainers).forEach(key => {
            if (summaryContainers[key]) {
                if (key === tab) {
                    summaryContainers[key].classList.remove('d-none');
                    summaryContainers[key].classList.add('d-flex');
                } else {
                    summaryContainers[key].classList.remove('d-flex');
                    summaryContainers[key].classList.add('d-none');
                }
            }
        });

        // 3. Filter Table Rows
        const rows = document.querySelectorAll('.settlement-log-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const cat = row.getAttribute('data-category');
            const isBackdate = row.getAttribute('data-backdate') === '1';

            let show = false;
            if (tab === 'all') {
                show = true;
            } else if (tab === 'kasbon') {
                show = (cat === 'kasbon');
            } else if (tab === 'trip') {
                show = (cat === 'trip');
            } else if (tab === 'backdate') {
                show = isBackdate;
            }

            if (show) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // 4. Show empty placeholder if 0 visible rows
        const emptyRow = document.getElementById('settlement-empty-filter-row');
        const emptyTitle = document.getElementById('settlement-empty-filter-title');
        const emptyDesc = document.getElementById('settlement-empty-filter-desc');
        if (emptyRow) {
            if (visibleCount === 0 && rows.length > 0) {
                emptyRow.style.display = '';
                if (tab === 'kasbon') {
                    if (emptyTitle) emptyTitle.textContent = 'Tidak Ada Pelunasan Kasbon';
                    if (emptyDesc) emptyDesc.textContent = 'Belum ada transaksi kasbon/piutang yang dilunasi pada tanggal ini.';
                } else if (tab === 'trip') {
                    if (emptyTitle) emptyTitle.textContent = 'Tidak Ada Order Trip Driver';
                    if (emptyDesc) emptyDesc.textContent = 'Belum ada pelunasan dari trip pengiriman driver pada tanggal ini.';
                } else if (tab === 'backdate') {
                    if (emptyTitle) emptyTitle.textContent = 'Tidak Ada Koreksi Backdate';
                    if (emptyDesc) emptyDesc.textContent = 'Semua pelunasan hari ini sesuai dengan tanggal nota.';
                } else {
                    if (emptyTitle) emptyTitle.textContent = 'Belum Ada Aktivitas';
                    if (emptyDesc) emptyDesc.textContent = 'Tidak ada data aktivitas pelunasan.';
                }
            } else {
                emptyRow.style.display = 'none';
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        startReloadCountdown();

        // Inisialisasi tampilan kasir: default kasir aktif, fallback ke 'all' jika belum ada kasir aktif
        const hasActive = document.querySelectorAll('.cashier-card-col[data-status="active"]').length > 0;
        currentCashierFilter = hasActive ? 'active' : 'all';
        applyCashierFilter(currentCashierFilter);

        // Inisialisasi filter log pelunasan: default kasbon
        if (typeof applySettlementFilter === 'function') {
            applySettlementFilter(currentSettlementFilter);
        }
    });
</script>
@endpush