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
                <div class="metric-value text-success">Rp {{ number_format($cashInToday ?? 0, 0, ',', '.') }}</div>
                <div class="d-flex flex-wrap gap-1 align-items-center mt-1">
                    <span class="badge py-1 px-2" style="background-color: #d1e7dd !important; color: #0f5132 !important; border: 1px solid #badbcc !important; font-size: 0.78rem; font-weight: 600;">
                        <i class="fas fa-check-circle me-1 text-success"></i>Lunas: Rp {{ number_format($cashInToday ?? 0, 0, ',', '.') }}
                    </span>
                    @if(($pendingToday ?? 0) > 0)
                    <span class="badge py-1 px-2" style="background-color: #f8d7da !important; color: #842029 !important; border: 1px solid #f5c2c7 !important; font-size: 0.78rem; font-weight: 600;" title="Total Kasbon / Tempo Baru Hari Ini">
                        <i class="fas fa-clock me-1 text-danger"></i>Kasbon: Rp {{ number_format($pendingToday, 0, ',', '.') }}
                    </span>
                    @else
                    <span class="badge py-1 px-2" style="background-color: #f8f9fa !important; color: #6c757d !important; border: 1px solid #dee2e6 !important; font-size: 0.78rem; font-weight: 600;">
                        <i class="fas fa-check me-1 text-secondary"></i>Nol Kasbon
                    </span>
                    @endif
                </div>
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

{{-- FILTER TANGGAL UNTUK OPNAME & PECAH BALL --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-2 px-3 d-flex align-items-center bg-light rounded">
                <form action="{{ route('admin.dashboard') }}" method="GET" class="d-flex align-items-center w-100">
                    <label for="filter_date" class="form-label mb-0 me-3 fw-bold text-dark">
                        <i class="fas fa-filter text-primary me-1"></i> Filter Aktivitas Stok & Pengiriman Driver:
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
                                        <div class="p-2 rounded-circle bg-warning bg-opacity-10 text-warning me-3">
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
                                                <span class="badge bg-danger px-2 py-1">
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

                // 2. Perbarui Monitoring Driver Container
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

    document.addEventListener('DOMContentLoaded', () => {
        startReloadCountdown();
    });
</script>
@endpush