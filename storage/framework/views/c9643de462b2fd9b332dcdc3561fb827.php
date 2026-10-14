<?php $__env->startSection('title', 'Dashboard Admin'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="breadcrumb-item active">Dashboard</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php if(isset($error)): ?>
<div class="alert alert-danger"><?php echo e($error); ?></div>
<?php else: ?>

<div class="dashboard-header mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="dashboard-title">Selamat Datang, <?php echo e(Auth::user()->name ?? 'Admin'); ?></h1>
            <p class="dashboard-subtitle">Berikut adalah ringkasan performa bisnis Anda hari ini</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="dashboard-date">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo e(\Carbon\Carbon::now()->format('l, d F Y')); ?></span>
            </div>
        </div>
    </div>
</div>


<div class="row g-4 mb-4">
    
    <div class="col-xl-3 col-md-6">
        <div class="metric-card metric-card-sales">
            <div class="metric-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="metric-content">
                <h3 class="metric-title">Penjualan Hari Ini</h3>
                <div class="metric-value">Rp <?php echo e(number_format($salesToday ?? 0, 0, ',', '.')); ?></div>
                <?php if(isset($salesChangePercentage)): ?>
                <?php if($salesChangePercentage >= 0): ?>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo e($salesChangePercentage); ?>% dari kemarin</span>
                </div>
                <?php else: ?>
                <div class="metric-change negative">
                    <i class="fas fa-arrow-down"></i>
                    <span><?php echo e(abs($salesChangePercentage)); ?>% dari kemarin</span>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>+12% dari kemarin</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="metric-progress">
                <div class="progress-bar" style="width: 75%"></div>
            </div>
        </div>
    </div>

    
    <div class="col-xl-3 col-md-6">
        <div class="metric-card metric-card-profit">
            <div class="metric-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="metric-content">
                <h3 class="metric-title">Laba Bersih Bulan Ini</h3>
                <div class="metric-value">Rp <?php echo e(number_format($netProfitThisMonth ?? 0, 0, ',', '.')); ?></div>
                <?php if(isset($profitChangePercentage)): ?>
                <?php if($profitChangePercentage >= 0): ?>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo e($profitChangePercentage); ?>% dari bulan lalu</span>
                </div>
                <?php else: ?>
                <div class="metric-change negative">
                    <i class="fas fa-arrow-down"></i>
                    <span><?php echo e(abs($profitChangePercentage)); ?>% dari bulan lalu</span>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>+8% dari bulan lalu</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="metric-progress">
                <div class="progress-bar" style="width: 85%"></div>
            </div>
        </div>
    </div>

    
    <div class="col-xl-3 col-md-6">
        <div class="metric-card metric-card-inventory">
            <div class="metric-icon">
                <i class="fas fa-box-open"></i>
            </div>
            <div class="metric-content">
                <h3 class="metric-title">Stok Menipis</h3>
                <div class="metric-value"><?php echo e($lowStockItems ?? 0); ?> <span class="metric-unit">Item</span></div>
                <div class="metric-action">
                    <a href="<?php echo e(route('admin.inventory.index')); ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-eye"></i> Lihat Detail
                    </a>
                </div>
            </div>
            <div class="metric-progress warning">
                <div class="progress-bar" style="width: 30%"></div>
            </div>
        </div>
    </div>

    
    <div class="col-xl-3 col-md-6">
        <div class="metric-card metric-card-users">
            <div class="metric-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="metric-content">
                <h3 class="metric-title">Total Pengguna</h3>
                <div class="metric-value"><?php echo e($totalUsers ?? 0); ?> <span class="metric-unit">User</span></div>
                <div class="metric-info">
                    <span class="badge badge-info"><?php echo e($activeUsers ?? 0); ?> sedang online</span>
                </div>
            </div>
            <div class="metric-progress">
                <div class="progress-bar" style="width: 65%"></div>
            </div>
        </div>
    </div>
</div>


<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-2 px-3 d-flex align-items-center bg-light rounded">
                <form action="<?php echo e(route('admin.dashboard')); ?>" method="GET" class="d-flex align-items-center w-100">
                    <label for="filter_date" class="form-label mb-0 me-3 fw-bold text-dark">
                        <i class="fas fa-filter text-primary me-1"></i> Filter Aktivitas Stok:
                    </label>
                    <input type="date" name="filter_date" id="filter_date" class="form-control w-auto me-2 border-primary" value="<?php echo e($filterDate); ?>">
                    <button type="submit" class="btn btn-primary btn-sm px-3">Tampilkan</button>

                    <?php if(!$isToday): ?>
                    <a href="<?php echo e(route('admin.dashboard')); ?>" class="btn btn-outline-secondary btn-sm ms-2">
                        <i class="fas fa-undo"></i> Kembali ke Hari Ini
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="row mb-4">
    <div class="col-12">
        <div class="card border-<?php echo e((isset($missedOpnames) && $missedOpnames->count() > 0) ? 'danger' : 'success'); ?> shadow-sm">
            <div class="card-header bg-<?php echo e((isset($missedOpnames) && $missedOpnames->count() > 0) ? 'danger' : 'success'); ?> text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php if(isset($missedOpnames) && $missedOpnames->count() > 0): ?>
                    <i class="fas fa-exclamation-triangle me-2"></i> Peringatan: Ada Selisih Stok (<?php echo e($isToday ? 'Hari Ini' : $formattedDate); ?>)
                    <?php else: ?>
                    <i class="fas fa-check-circle me-2"></i> Opname Stok Aman (<?php echo e($isToday ? 'Hari Ini' : $formattedDate); ?>)
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if(isset($missedOpnames) && $missedOpnames->count() > 0): ?>
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
                            <?php
                                $groupedMissed = $missedOpnames->groupBy(function($item) {
                                    return $item->product->name ?? 'Produk Dihapus';
                                });
                            ?>

                            <?php $__currentLoopData = $groupedMissed; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $productName => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="table-secondary">
                                <td colspan="4" class="fw-bold text-dark">
                                    <i class="fas fa-box text-primary me-2"></i> <?php echo e($productName); ?>

                                    <span class="badge bg-white text-dark border ms-2"><?php echo e($items->count()); ?> Aktivitas</span>
                                </td>
                            </tr>
                            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $miss): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="ps-4"><span class="badge bg-secondary"><?php echo e($miss->created_at->format('H:i')); ?></span></td>
                                <td><strong><?php echo e($miss->createdBy->name ?? 'Kasir'); ?></strong></td>
                                <td>
                                    <span class="badge bg-danger fs-6"><?php echo e($miss->quantity); ?></span>
                                </td>
                                <td class="text-danger fw-bold">"<?php echo e($miss->notes); ?>"</td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">Belum ada laporan selisih stok (miss) dari kasir pada tanggal <?php echo e($formattedDate); ?>.</p>
                <?php endif; ?>

                <hr>

                <h6 class="fw-bold mt-3"><i class="fas fa-box me-1"></i> Stok Realtime Saat Ini (Tidak Terpengaruh Filter):</h6>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php $__currentLoopData = $realtimeStocks ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stock): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <span class="badge bg-info text-dark fs-6 p-2 border">
                        <?php echo e($stock->product->name ?? 'Item'); ?> : <?php echo e($stock->current_stock); ?>

                    </span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="row mb-4">
    <div class="col-12">
        <div class="card border-primary shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exchange-alt me-2"></i> Riwayat Pecah Ball / Repacking (<?php echo e($isToday ? 'Hari Ini' : $formattedDate); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if(isset($breakUnitHistory) && count($breakUnitHistory) > 0): ?>
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
                            <?php $__currentLoopData = $breakUnitHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $history): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?php echo e($history['time']->format('H:i')); ?></span></td>
                                <td><strong><?php echo e($history['kasir']); ?></strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-danger fs-6 me-2">- <?php echo e($history['source_qty']); ?></span>
                                        <span class="fw-bold text-dark"><?php echo e($history['source_product']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <ul class="list-unstyled mb-0">
                                        <?php $__currentLoopData = $history['targets']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $target): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <li class="mb-1">
                                            <i class="fas fa-arrow-right text-success me-2"></i>
                                            <span class="badge bg-success fs-6 me-1">+ <?php echo e($target->quantity); ?></span>
                                            <?php echo e($target->product->name ?? 'Produk Dihapus'); ?>

                                        </li>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </ul>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">Belum ada aktivitas pecah karung/ball oleh kasir pada tanggal <?php echo e($formattedDate); ?>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<div class="row g-4">
    
    <div class="col-lg-5">
        <div class="dashboard-card">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fas fa-chart-pie"></i>
                    Alokasi Dana (Berdasarkan Pengaturan)
                </h4>
                <p class="card-subtitle">Dari keuntungan bersih: <strong>Rp <?php echo e(number_format($netProfitThisMonth ?? 0, 0, ',', '.')); ?></strong></p>
            </div>
            <div class="card-body">
                <div class="fund-allocation-wrapper">
                    
                    <div class="chart-container-wrapper">
                        <div class="chart-container" style="height: 300px; position: relative;">
                            <canvas id="fundAllocationChart"></canvas>
                            <div class="chart-center-text">
                                <div class="center-amount">Rp <?php echo e(number_format($netProfitThisMonth ?? 0, 0, ',', '.')); ?></div>
                                <div class="center-label">Total Dana</div>
                            </div>
                        </div>
                    </div>

                    
                    <div id="fundAllocationLegend" class="fund-legend-container"></div>
                </div>
            </div>
            <div class="card-footer">
                <a href="<?php echo e(route('admin.fund-allocation.settings')); ?>" class="btn btn-gold">
                    <i class="fas fa-cog"></i> Atur Alokasi
                </a>
            </div>
        </div>
    </div>

    
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
                            Rp <?php echo e(number_format($avgSales ?? 0, 2, ',', '.')); ?> Jt
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Rata-rata Keuntungan</div>
                        <div class="stat-value text-success">
                            Rp <?php echo e(number_format($avgProfit ?? 0, 2, ',', '.')); ?> Jt
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Margin Keuntungan</div>
                        <div class="stat-value text-info">
                            <?php echo e($profitMargin ?? 0); ?>%
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="<?php echo e(route('admin.reports.index')); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-chart-pie"></i> Lihat Laporan Lengkap
                </a>
            </div>
        </div>
    </div>
</div>


<div class="row g-4 mt-4">
    
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
                    <a href="<?php echo e(route('admin.products.index')); ?>" class="quick-action-item">
                        <div class="action-icon bg-primary"><i class="fas fa-box"></i></div>
                        <div class="action-content">
                            <h5>Master Data</h5>
                            <p>Kelola produk & kategori</p>
                        </div>
                    </a>
                    <a href="<?php echo e(route('admin.inventory.index')); ?>" class="quick-action-item">
                        <div class="action-icon bg-info"><i class="fas fa-warehouse"></i></div>
                        <div class="action-content">
                            <h5>Inventaris</h5>
                            <p>Monitoring stok barang</p>
                        </div>
                    </a>
                    <a href="<?php echo e(route('admin.transactions.index')); ?>" class="quick-action-item">
                        <div class="action-icon bg-success"><i class="fas fa-cash-register"></i></div>
                        <div class="action-content">
                            <h5>Transaksi</h5>
                            <p>Kelola penjualan & pembelian</p>
                        </div>
                    </a>
                    <a href="<?php echo e(route('admin.financial.index')); ?>" class="quick-action-item">
                        <div class="action-icon bg-danger"><i class="fas fa-chart-pie"></i></div>
                        <div class="action-content">
                            <h5>Finansial</h5>
                            <p>Laporan keuangan</p>
                        </div>
                    </a>
                    <a href="<?php echo e(route('admin.reports.index')); ?>" class="quick-action-item">
                        <div class="action-icon bg-warning"><i class="fas fa-file-chart-line"></i></div>
                        <div class="action-content">
                            <h5>Laporan</h5>
                            <p>Analisis & reporting</p>
                        </div>
                    </a>
                    <a href="<?php echo e(route('admin.fund-allocation.index')); ?>" class="quick-action-item">
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
                    <?php $__empty_1 = true; $__currentLoopData = $recentActivities ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="activity-item">
                        <div class="activity-avatar">
                            <img src="<?php echo e($log->user->avatar ?? '/images/default-avatar.png'); ?>" alt="User Avatar">
                        </div>
                        <div class="activity-content">
                            <div class="activity-header">
                                <h6 class="activity-user"><?php echo e($log->user->name ?? 'Sistem'); ?></h6>
                                <span class="activity-time"><?php echo e($log->created_at->diffForHumans()); ?></span>
                            </div>
                            <div class="activity-description">
                                <span class="activity-action"><?php echo e($log->action); ?></span>
                                <span class="activity-module"><?php echo e($log->module); ?></span>
                            </div>
                            <?php if($log->details): ?>
                            <div class="activity-details">
                                <i class="fas fa-info-circle"></i>
                                <?php echo e(\Illuminate\Support\Str::limit($log->details, 60)); ?>

                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                        <div class="empty-text">
                            <h6>Tidak ada aktivitas terbaru</h6>
                            <p>Aktivitas sistem akan muncul di sini</p>
                        </div>
                    </div>
                    <?php endif; ?>
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
<?php endif; ?>
<?php $__env->stopSection(); ?>


<?php $__env->startPush('styles'); ?>
<?php echo app('Illuminate\Foundation\Vite')(['resources/css/admin/dashboard.css']); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>
    window.dashboardData = {
        fundAllocation: <?php echo json_encode($fundAllocationData ?? [], 15, 512) ?>,
        monthlyPerformance: <?php echo json_encode($monthlyPerformanceData ?? null, 15, 512) ?>,
        weeklyPerformance: <?php echo json_encode($weeklyPerformanceData ?? null, 15, 512) ?>
    };
</script>


<?php echo app('Illuminate\Foundation\Vite')(['resources/js/admin/dashboard.js']); ?>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/admin/dashboard/index.blade.php ENDPATH**/ ?>