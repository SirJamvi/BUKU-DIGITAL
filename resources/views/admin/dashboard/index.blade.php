@extends('admin.layouts.app')

@section('title', 'Dashboard Admin')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
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
                    <div class="metric-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12% dari kemarin</span>
                    </div>
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
                    <div class="metric-value">Rp {{ number_format($netProfitToday ?? 0, 0, ',', '.') }}</div>
                    <div class="metric-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+8% dari bulan lalu</span>
                    </div>
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

        {{-- Pengguna Aktif --}}
        <div class="col-xl-3 col-md-6">
            <div class="metric-card metric-card-users">
                <div class="metric-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="metric-content">
                    <h3 class="metric-title">Pengguna Aktif</h3>
                    <div class="metric-value">{{ $activeUsers ?? 0 }} <span class="metric-unit">Online</span></div>
                    <div class="metric-info">
                        <span class="badge badge-info">{{ $totalUsers ?? 0 }} Total User</span>
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
        {{-- Quick Actions --}}
        <div class="col-lg-6">
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
                            <div class="action-icon bg-primary">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="action-content">
                                <h5>Master Data</h5>
                                <p>Kelola produk & kategori</p>
                            </div>
                        </a>

                        <a href="{{ route('admin.inventory.index') }}" class="quick-action-item">
                            <div class="action-icon bg-info">
                                <i class="fas fa-warehouse"></i>
                            </div>
                            <div class="action-content">
                                <h5>Inventaris</h5>
                                <p>Monitoring stok barang</p>
                            </div>
                        </a>

                        <a href="{{ route('admin.transactions.index') }}" class="quick-action-item">
                            <div class="action-icon bg-success">
                                <i class="fas fa-cash-register"></i>
                            </div>
                            <div class="action-content">
                                <h5>Transaksi</h5>
                                <p>Kelola penjualan & pembelian</p>
                            </div>
                        </a>

                        <a href="{{ route('admin.financial.index') }}" class="quick-action-item">
                            <div class="action-icon bg-danger">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="action-content">
                                <h5>Finansial</h5>
                                <p>Laporan keuangan</p>
                            </div>
                        </a>

                        <a href="{{ route('admin.reports.index') }}" class="quick-action-item">
                            <div class="action-icon bg-warning">
                                <i class="fas fa-file-chart-line"></i>
                            </div>
                            <div class="action-content">
                                <h5>Laporan</h5>
                                <p>Analisis & reporting</p>
                            </div>
                        </a>

                        <a href="{{ route('admin.fund-allocation.index') }}" class="quick-action-item">
                            <div class="action-icon bg-gold">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="action-content">
                                <h5>Alokasi Dana</h5>
                                <p>Manajemen dana usaha</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Smart Fund Allocation --}}
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-chart-pie"></i>
                        Smart Fund Allocation
                    </h4>
                    <p class="card-subtitle">Alokasi dana dari keuntungan bersih: <strong>Rp {{ number_format($netProfitToday ?? 0, 0, ',', '.') }}</strong></p>
                </div>
                <div class="card-body">
                    <div class="fund-allocation-container">
                        <div class="chart-container">
                            <canvas id="fundAllocationChart"></canvas>
                            <div class="chart-center-text">
                                <div class="center-amount">Rp {{ number_format($netProfitToday ?? 0, 0, ',', '.') }}</div>
                                <div class="center-label">Total Dana</div>
                            </div>
                        </div>
                        <div class="allocation-details">
                            <div class="allocation-item">
                                <div class="allocation-indicator bg-success"></div>
                                <div class="allocation-info">
                                    <h6>Gaji Owner</h6>
                                    <div class="amount">Rp {{ number_format(($netProfitToday ?? 0) * 0.4, 0, ',', '.') }}</div>
                                    <div class="percentage">40%</div>
                                </div>
                            </div>
                            <div class="allocation-item">
                                <div class="allocation-indicator bg-primary"></div>
                                <div class="allocation-info">
                                    <h6>Reinvestasi</h6>
                                    <div class="amount">Rp {{ number_format(($netProfitToday ?? 0) * 0.3, 0, ',', '.') }}</div>
                                    <div class="percentage">30%</div>
                                </div>
                            </div>
                            <div class="allocation-item">
                                <div class="allocation-indicator bg-warning"></div>
                                <div class="allocation-info">
                                    <h6>Dana Darurat</h6>
                                    <div class="amount">Rp {{ number_format(($netProfitToday ?? 0) * 0.2, 0, ',', '.') }}</div>
                                    <div class="percentage">20%</div>
                                </div>
                            </div>
                            <div class="allocation-item">
                                <div class="allocation-indicator bg-info"></div>
                                <div class="allocation-info">
                                    <h6>Ekspansi</h6>
                                    <div class="amount">Rp {{ number_format(($netProfitToday ?? 0) * 0.1, 0, ',', '.') }}</div>
                                    <div class="percentage">10%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('admin.fund-allocation.settings') }}" class="btn btn-gold">
                            <i class="fas fa-cog"></i> Atur Alokasi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Analytics & Activities Row --}}
    <div class="row g-4 mt-4">
        {{-- Comprehensive Analytics --}}
<div class="col-lg-8">
    <div class="dashboard-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title">
                        <i class="fas fa-chart-line"></i>
                        Analitik Performa
                    </h4>
                    <p class="card-subtitle">Tren penjualan dan keuntungan 6 bulan terakhir</p>
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
            <div class="chart-wrapper">
                <canvas id="comprehensiveChart"></canvas>
            </div>
            <div class="chart-stats">
                <div class="stat-item">
                    <div class="stat-label">Rata-rata Penjualan</div>
                    <div class="stat-value text-primary">
                        Rp {{ number_format($avgSales, 2, ',', '.') }} Jt
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Rata-rata Keuntungan</div>
                    <div class="stat-value text-success">
                        Rp {{ number_format($avgProfit, 2, ',', '.') }} Jt
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Margin Keuntungan</div>
                    <div class="stat-value text-info">
                        {{ $profitMargin }}%
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
                        @forelse($recentActivities as $log)
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
                                <div class="empty-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
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

@endsection

@push('styles')
<style>
    /* Dashboard Header */
    .dashboard-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
    }
    
    .dashboard-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .dashboard-subtitle {
        opacity: 0.9;
        margin-bottom: 0;
    }
    
    .dashboard-date {
        background: rgba(255, 255, 255, 0.1);
        padding: 0.75rem 1rem;
        border-radius: 10px;
        backdrop-filter: blur(10px);
    }

    /* Metric Cards */
    .metric-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .metric-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    
    .metric-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }
    
    .metric-card-sales::before { background: linear-gradient(90deg, #4facfe, #00f2fe); }
    .metric-card-profit::before { background: linear-gradient(90deg, #43e97b, #38f9d7); }
    .metric-card-inventory::before { background: linear-gradient(90deg, #fa709a, #fee140); }
    .metric-card-users::before { background: linear-gradient(90deg, #a8edea, #fed6e3); }
    
    .metric-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .metric-card-sales .metric-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .metric-card-profit .metric-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    .metric-card-inventory .metric-icon { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    .metric-card-users .metric-icon { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
    
    .metric-title {
        font-size: 0.875rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    
    .metric-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }
    
    .metric-unit {
        font-size: 1rem;
        font-weight: 500;
        color: #6c757d;
    }
    
    .metric-change {
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .metric-change.positive {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    
    .metric-action {
        margin-top: 0.75rem;
    }
    
    .metric-info {
        margin-top: 0.5rem;
    }
    
    .metric-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: rgba(0, 0, 0, 0.05);
    }
    
    .metric-progress .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        transition: width 0.3s ease;
    }
    
    .metric-progress.warning .progress-bar {
        background: linear-gradient(90deg, #fa709a, #fee140);
    }

    /* Dashboard Cards */
    .dashboard-card {
        background: white;
        border-radius: 15px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .dashboard-card:hover {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    
    .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 1.5rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .card-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .card-subtitle {
        color: #6c757d;
        margin-bottom: 0;
        font-size: 0.875rem;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .card-footer {
        background: #f8f9fa;
        padding: 1rem 1.5rem;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
    }

    /* Quick Actions */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .quick-action-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        transition: all 0.3s ease;
    }
    
    .quick-action-item:hover {
        background: #e9ecef;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        color: inherit;
        text-decoration: none;
    }
    
    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.25rem;
    }
    
    .action-content h5 {
        margin-bottom: 0.25rem;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .action-content p {
        margin-bottom: 0;
        color: #6c757d;
        font-size: 0.875rem;
    }

    /* Fund Allocation */
    .fund-allocation-container {
        display: flex;
        gap: 2rem;
        align-items: center;
    }
    
    .chart-container {
        position: relative;
        width: 200px;
        height: 200px;
    }
    
    .chart-center-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }
    
    .center-amount {
        font-size: 1rem;
        font-weight: 700;
        color: #2c3e50;
    }
    
    .center-label {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    
    .allocation-details {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .allocation-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .allocation-item:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }
    
    .allocation-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    
    .allocation-info {
        flex: 1;
    }
    
    .allocation-info h6 {
        margin-bottom: 0.25rem;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .allocation-info .amount {
        font-size: 0.875rem;
        color: #6c757d;
    }
    
    .allocation-info .percentage {
        font-size: 0.75rem;
        color: #495057;
        font-weight: 600;
    }

    /* Chart Styles */
    .chart-wrapper {
        position: relative;
        height: 300px;
        margin-bottom: 1.5rem;
    }
    
    .chart-controls {
        display: flex;
        gap: 0.5rem;
    }
    
    .chart-stats {
        display: flex;
        justify-content: space-around;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        margin-top: 1rem;
    }
    
    .stat-item {
        text-align: center;
    }
    
    .stat-label {
        font-size: 0.75rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    
    .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
    }

    /* Activity Timeline */
    .activity-timeline {
        max-height: 400px;
        overflow-y: auto;
        padding-right: 0.5rem;
    }
    
    .activity-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 0.75rem;
        background: #f8f9fa;
        transition: all 0.3s ease;
    }
    
    .activity-item:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }
    
    .activity-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
    }
    
    .activity-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .activity-content {
        flex: 1;
    }
    
    .activity-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.25rem;
    }
    
    .activity-user {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0;
    }
    
    .activity-time {
        font-size: 0.75rem;
        color: #6c757d;
    }
    
    .activity-description {
        margin-bottom: 0.5rem;
    }
    
    .activity-action {
        color: #495057;
        font-weight: 500;
    }
    
    .activity-module {
        color: #6c757d;
    }
    
    .activity-details {
        font-size: 0.75rem;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 2rem;
    }
    
    .empty-icon {
        font-size: 3rem;
        color: #dee2e6;
        margin-bottom: 1rem;
    }
    
    .empty-text h6 {
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    
    .empty-text p {
        color: #adb5bd;
        margin-bottom: 0;
        font-size: 0.875rem;
    }

    /* Utility Classes */
    .bg-primary { background: #007bff !important; }
    .bg-success { background: #28a745 !important; }
    .bg-info { background: #17a2b8 !important; }
    .bg-warning { background: #ffc107 !important; }
    .bg-danger { background: #dc3545 !important; }
    .bg-gold { background: linear-gradient(135deg, #f9d423 0%, #ff4e50 100%) !important; }
    
    .text-primary { color: #007bff !important; }
    .text-success { color: #28a745 !important; }
    .text-info { color: #17a2b8 !important; }
    .text-warning { color: #ffc107 !important; }
    .text-danger { color: #dc3545 !important; }

    /* Buttons */
    .btn-gold {
        background: linear-gradient(135deg, #f9d423 0%, #ff4e50 100%);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-gold:hover {
        background: linear-gradient(135deg, #e6c21f 0%, #e04648 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(249, 212, 35, 0.3);
    }

    /* Badges */
    .badge-info {
        background: rgba(23, 162, 184, 0.1);
        color: #17a2b8;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1.5rem;
        }
        
        .dashboard-title {
            font-size: 1.5rem;
        }
        
        .metric-card {
            padding: 1rem;
        }
        
        .fund-allocation-container {
            flex-direction: column;
            gap: 1rem;
        }
        
        .chart-container {
            width: 150px;
            height: 150px;
        }
        
        .quick-actions-grid {
            grid-template-columns: 1fr;
        }
        
        .chart-stats {
            flex-direction: column;
            gap: 1rem;
        }
    }

    /* Scrollbar Styling */
    .activity-timeline::-webkit-scrollbar {
        width: 6px;
    }
    
    .activity-timeline::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .activity-timeline::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }
    
    .activity-timeline::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .metric-card,
    .dashboard-card {
        animation: fadeInUp 0.6s ease forwards;
    }
    
    .metric-card:nth-child(1) { animation-delay: 0.1s; }
    .metric-card:nth-child(2) { animation-delay: 0.2s; }
    .metric-card:nth-child(3) { animation-delay: 0.3s; }
    .metric-card:nth-child(4) { animation-delay: 0.4s; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Fund Allocation Chart
    const fundCtx = document.getElementById('fundAllocationChart').getContext('2d');
    const fundChart = new Chart(fundCtx, {
        type: 'doughnut',
        data: {
            labels: ['Gaji Owner', 'Reinvestasi', 'Dana Darurat', 'Ekspansi'],
            datasets: [{
                data: [40, 30, 20, 10],
                backgroundColor: [
                    '#28a745',
                    '#007bff', 
                    '#ffc107',
                    '#17a2b8'
                ],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${context.label}: ${percentage}%`;
                        }
                    }
                }
            },
            interaction: {
                intersect: false
            },
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
            }
        }
    });

    // Comprehensive Analytics Chart
    const comprehensiveCtx = document.getElementById('comprehensiveChart').getContext('2d');
    const comprehensiveChart = new Chart(comprehensiveCtx, {
        type: 'line',
        data: {
            labels: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'],
            datasets: [
                {
                    label: 'Penjualan',
                    data: [12, 15, 18, 13, 17, 21],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#007bff',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                },
                {
                    label: 'Keuntungan',
                    data: [4, 5.5, 6, 4.5, 6.5, 8],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#28a745',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false,
                    },
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value + ' Jt';
                        },
                        color: '#6c757d',
                        font: {
                            size: 12
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#6c757d',
                        font: {
                            size: 12
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 13,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    cornerRadius: 8,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        title: function(context) {
                            return 'Bulan ' + context[0].label;
                        },
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + context.parsed.y + ' Jt';
                        }
                    }
                }
            },
            elements: {
                line: {
                    borderWidth: 3
                }
            }
        }
    });

    // Chart Period Toggle
    const chartPeriodInputs = document.querySelectorAll('input[name="chartPeriod"]');
    chartPeriodInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.id === 'weekly') {
                comprehensiveChart.data.labels = ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'];
                comprehensiveChart.data.datasets[0].data = [18, 22, 19, 25];
                comprehensiveChart.data.datasets[1].data = [6, 8, 7, 9];
            } else {
                comprehensiveChart.data.labels = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'];
                comprehensiveChart.data.datasets[0].data = [12, 15, 18, 13, 17, 21];
                comprehensiveChart.data.datasets[1].data = [4, 5.5, 6, 4.5, 6.5, 8];
            }
            comprehensiveChart.update();
        });
    });

    // Animate metric cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe all metric cards
    document.querySelectorAll('.metric-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });

    // Add smooth scrolling for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Auto-refresh data every 5 minutes
    setInterval(() => {
        // Here you would typically make an AJAX call to refresh the data
        console.log('Auto-refreshing dashboard data...');
    }, 300000); // 5 minutes

    // Add loading states for quick actions
    document.querySelectorAll('.quick-action-item').forEach(item => {
        item.addEventListener('click', function(e) {
            const icon = this.querySelector('.action-icon i');
            const originalClass = icon.className;
            
            // Show loading state
            icon.className = 'fas fa-spinner fa-spin';
            
            // Reset after a short delay (simulate loading)
            setTimeout(() => {
                icon.className = originalClass;
            }, 500);
        });
    });
});
</script>
@endpush