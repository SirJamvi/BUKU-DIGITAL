@extends('admin.layouts.app')

@section('title', 'Dashboard Admin')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
    {{-- Menampilkan pesan error jika ada --}}
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
                <div class="col-md-4 text-end">
                    <div class="dashboard-date">
                        <i class="fas fa-calendar-alt"></i>
                        <span>{{ \Carbon\Carbon::now()->format('l, d F Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Performance Metrics Cards --}}
        <div class="row g-4 mb-4">
            {{-- Penjualan Hari Ini --}}
            <div class="col-xl-3 col-md-6">
                <div class="metric-card metric-card-sales">
                    <div class="metric-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="metric-content">
                        <h3 class="metric-title">Penjualan Hari Ini</h3>
                        <div class="metric-value">Rp {{ number_format($salesToday ?? 0, 0, ',', '.') }}</div>
                        @if(isset($salesChangePercentage))
                            @if($salesChangePercentage >= 0)
                                <div class="metric-change positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>{{ $salesChangePercentage }}% dari kemarin</span>
                                </div>
                            @else
                                <div class="metric-change negative">
                                    <i class="fas fa-arrow-down"></i>
                                    <span>{{ abs($salesChangePercentage) }}% dari kemarin</span>
                                </div>
                            @endif
                        @else
                            <div class="metric-change positive">
                                <i class="fas fa-arrow-up"></i>
                                <span>+12% dari kemarin</span>
                            </div>
                        @endif
                    </div>
                    <div class="metric-progress">
                        <div class="progress-bar" style="width: 75%"></div>
                    </div>
                </div>
            </div>

            {{-- Laba Bersih --}}
            <div class="col-xl-3 col-md-6">
                <div class="metric-card metric-card-profit">
                    <div class="metric-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="metric-content">
                        <h3 class="metric-title">Laba Bersih Bulan Ini</h3>
                        <div class="metric-value">Rp {{ number_format($netProfitThisMonth ?? 0, 0, ',', '.') }}</div>
                        @if(isset($profitChangePercentage))
                            @if($profitChangePercentage >= 0)
                                <div class="metric-change positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>{{ $profitChangePercentage }}% dari bulan lalu</span>
                                </div>
                            @else
                                <div class="metric-change negative">
                                    <i class="fas fa-arrow-down"></i>
                                    <span>{{ abs($profitChangePercentage) }}% dari bulan lalu</span>
                                </div>
                            @endif
                        @else
                            <div class="metric-change positive">
                                <i class="fas fa-arrow-up"></i>
                                <span>+8% dari bulan lalu</span>
                            </div>
                        @endif
                    </div>
                    <div class="metric-progress">
                        <div class="progress-bar" style="width: 85%"></div>
                    </div>
                </div>
            </div>

            {{-- Stok Rendah --}}
            <div class="col-xl-3 col-md-6">
                <div class="metric-card metric-card-inventory">
                    <div class="metric-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div class="metric-content">
                        <h3 class="metric-title">Stok Menipis</h3>
                        <div class="metric-value">{{ $lowStockItems ?? 0 }} <span class="metric-unit">Item</span></div>
                        <div class="metric-action">
                            <a href="{{ route('admin.inventory.index') }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-eye"></i> Lihat Detail
                            </a>
                        </div>
                    </div>
                    <div class="metric-progress warning">
                        <div class="progress-bar" style="width: 30%"></div>
                    </div>
                </div>
            </div>

            {{-- Total Pengguna --}}
            <div class="col-xl-3 col-md-6">
                <div class="metric-card metric-card-users">
                    <div class="metric-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="metric-content">
                        <h3 class="metric-title">Total Pengguna</h3>
                        <div class="metric-value">{{ $totalUsers ?? 0 }} <span class="metric-unit">User</span></div>
                        <div class="metric-info">
                            <span class="badge badge-info">{{ $activeUsers ?? 0 }} sedang online</span>
                        </div>
                    </div>
                    <div class="metric-progress">
                        <div class="progress-bar" style="width: 65%"></div>
                    </div>
                </div>
            </div>
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
                                <div class="action-content"><h5>Master Data</h5><p>Kelola produk & kategori</p></div>
                            </a>
                            <a href="{{ route('admin.inventory.index') }}" class="quick-action-item">
                                <div class="action-icon bg-info"><i class="fas fa-warehouse"></i></div>
                                <div class="action-content"><h5>Inventaris</h5><p>Monitoring stok barang</p></div>
                            </a>
                            <a href="{{ route('admin.transactions.index') }}" class="quick-action-item">
                                <div class="action-icon bg-success"><i class="fas fa-cash-register"></i></div>
                                <div class="action-content"><h5>Transaksi</h5><p>Kelola penjualan & pembelian</p></div>
                            </a>
                            <a href="{{ route('admin.financial.index') }}" class="quick-action-item">
                                <div class="action-icon bg-danger"><i class="fas fa-chart-pie"></i></div>
                                <div class="action-content"><h5>Finansial</h5><p>Laporan keuangan</p></div>
                            </a>
                            <a href="{{ route('admin.reports.index') }}" class="quick-action-item">
                                <div class="action-icon bg-warning"><i class="fas fa-file-chart-line"></i></div>
                                <div class="action-content"><h5>Laporan</h5><p>Analisis & reporting</p></div>
                            </a>
                            <a href="{{ route('admin.fund-allocation.index') }}" class="quick-action-item">
                                <div class="action-icon bg-gold"><i class="fas fa-money-bill-wave"></i></div>
                                <div class="action-content"><h5>Alokasi Dana</h5><p>Manajemen dana usaha</p></div>
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
                                    <div class="empty-text"><h6>Tidak ada aktivitas terbaru</h6><p>Aktivitas sistem akan muncul di sini</p></div>
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
@endpush