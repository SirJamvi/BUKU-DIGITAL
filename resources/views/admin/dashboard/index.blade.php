@extends('admin.layouts.app')

@section('title', 'Dashboard Admin')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
    {{-- Baris 1: Widget Ringkasan Utama --}}
    <div class="row">
        {{-- Widget Penjualan Hari Ini --}}
        <div class="col-md-6 col-lg-3">
            <x-card>
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-dollar-sign fa-3x text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Penjualan Hari Ini</h6>
                        <h4 class="mb-0">Rp {{ number_format($salesToday ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Widget Laba Bersih Hari Ini --}}
        <div class="col-md-6 col-lg-3">
            <x-card>
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-chart-line fa-3x text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Laba Bersih (Bulan Ini)</h6>
                        <h4 class="mb-0">Rp {{ number_format($netProfitToday ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Widget Peringatan Stok Rendah --}}
        <div class="col-md-6 col-lg-3">
            <x-card>
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Stok Rendah</h6>
                        <h4 class="mb-0">{{ $lowStockItems ?? 0 }} Item</h4>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Widget Pengguna Aktif --}}
        <div class="col-md-6 col-lg-3">
            <x-card>
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users fa-3x text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Pengguna Aktif</h6>
                        <h4 class="mb-0">{{ $activeUsers ?? 0 }} Online</h4>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Baris 2: Alokasi Dana & Analitik --}}
    <div class="row">
        {{-- Smart Fund Allocation --}}
        <div class="col-lg-6">
            <x-card title="Smart Fund Allocation">
                <p class="text-muted">Visualisasi alokasi dana dari keuntungan bersih bulan ini.</p>
                {{-- Placeholder untuk Pie Chart --}}
                <div class="text-center">
                    <canvas id="fundAllocationChart" height="250"></canvas>
                    {{-- JavaScript untuk chart ini akan ditambahkan nanti --}}
                </div>
                <div class="mt-3 text-center">
                    <a href="{{ route('admin.fund-allocation.settings') }}" class="btn btn-outline-primary">
                        <i class="fas fa-cog"></i> Atur Alokasi
                    </a>
                </div>
            </x-card>
        </div>

        {{-- Comprehensive Analytics --}}
        <div class="col-lg-6">
            <x-card title="Analitik Komprehensif">
                <p class="text-muted">Grafik tren penjualan, keuntungan, ROI, dan BEP.</p>
                {{-- Placeholder untuk Line Chart --}}
                <div class="text-center">
                     <canvas id="comprehensiveChart" height="250"></canvas>
                     {{-- JavaScript untuk chart ini akan ditambahkan nanti --}}
                </div>
                 <div class="mt-3 text-center">
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary">
                       <i class="fas fa-chart-pie"></i> Lihat Laporan Lengkap
                    </a>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Baris 3: Aktivitas Terbaru --}}
    <div class="row">
        <div class="col-12">
            <x-card title="Aktivitas Terbaru">
                <ul class="list-group list-group-flush">
                    @forelse($recentActivities as $log)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-user-clock text-muted me-2"></i>
                                <strong>{{ $log->user->name ?? 'Sistem' }}</strong> 
                                melakukan aksi '{{ $log->action }}' pada modul '{{ $log->module }}'.
                            </div>
                            <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-center">Tidak ada aktivitas terbaru.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>

@endsection

@push('scripts')
{{-- Library Chart.js akan kita tambahkan di layout utama --}}
<script>
    // Data dummy untuk chart, nanti akan diganti dengan data asli via API
    document.addEventListener("DOMContentLoaded", function() {
        // Fund Allocation Chart
        var fundCtx = document.getElementById('fundAllocationChart').getContext('2d');
        new Chart(fundCtx, {
            type: 'doughnut',
            data: {
                labels: ['Gaji Owner (40%)', 'Reinvestasi (30%)', 'Darurat (20%)', 'Ekspansi (10%)'],
                datasets: [{
                    data: [40, 30, 20, 10],
                    backgroundColor: ['#28a745', '#007bff', '#ffc107', '#17a2b8'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
            }
        });

        // Comprehensive Analytics Chart
        var comprehensiveCtx = document.getElementById('comprehensiveChart').getContext('2d');
        new Chart(comprehensiveCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                datasets: [{
                    label: 'Penjualan',
                    data: [120, 150, 180, 130, 170, 210],
                    borderColor: '#007bff',
                    tension: 0.1
                }, {
                    label: 'Keuntungan',
                    data: [40, 55, 60, 45, 65, 80],
                    borderColor: '#28a745',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
            }
        });
    });
</script>
@endpush