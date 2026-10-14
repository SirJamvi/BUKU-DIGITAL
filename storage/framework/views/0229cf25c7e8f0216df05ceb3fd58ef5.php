<?php $__env->startSection('title', 'Laporan Keuangan'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="breadcrumb-item"><a href="<?php echo e(route('admin.reports.index')); ?>">Laporan</a></li>
<li class="breadcrumb-item active" aria-current="page">Keuangan</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['class' => 'mb-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mb-2']); ?>
    <?php $__env->slot('title'); ?>
    Filter Laporan Keuangan
    <?php $__env->endSlot(); ?>
    <form method="GET" action="<?php echo e(route('admin.reports.financial')); ?>">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['type' => 'date','name' => 'start_date','label' => 'Tanggal Mulai','value' => $reportData['filters']['start_date'] ?? now()->startOfMonth()->toDateString()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'date','name' => 'start_date','label' => 'Tanggal Mulai','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportData['filters']['start_date'] ?? now()->startOfMonth()->toDateString())]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $attributes = $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $component = $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
            </div>
            <div class="col-md-5">
                <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['type' => 'date','name' => 'end_date','label' => 'Tanggal Akhir','value' => $reportData['filters']['end_date'] ?? now()->toDateString()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'date','name' => 'end_date','label' => 'Tanggal Akhir','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportData['filters']['end_date'] ?? now()->toDateString())]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $attributes = $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $component = $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
            </div>
            <div class="col-md-2">
                <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['type' => 'submit','variant' => 'primary','class' => 'w-100']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','variant' => 'primary','class' => 'w-100']); ?>
                    <i class="fas fa-filter me-1"></i>Terapkan
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
            </div>
        </div>
    </form>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $attributes = $__attributesOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__attributesOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $component = $__componentOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__componentOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>


<?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <?php $__env->slot('title'); ?>
    Hasil Laporan Keuangan
    <?php $__env->endSlot(); ?>

    <?php $__env->slot('headerActions'); ?>
    <a href="<?php echo e(route('admin.reports.financial.export.pdf', request()->query())); ?>"
        class="btn btn-secondary btn-sm">
        <i class="fas fa-print me-2"></i>Ekspor PDF
    </a>
    <?php $__env->endSlot(); ?>

    
    
    
    <div class="row text-center mb-4 g-3">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2 small">Total Pemasukan</p>
                    <h4 class="text-success mb-0 fw-bold">
                        Rp <?php echo e(number_format($reportData['total_income'] ?? 0, 0, ',', '.')); ?>

                    </h4>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2 small">Keuntungan Kotor</p>
                    <h4 class="text-info mb-0 fw-bold">
                        Rp <?php echo e(number_format($reportData['total_gross_profit'] ?? 0, 0, ',', '.')); ?>

                    </h4>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2 small">Total HPP (COGS)</p>
                    <h4 class="text-warning mb-0 fw-bold">
                        Rp <?php echo e(number_format($reportData['total_cogs'] ?? 0, 0, ',', '.')); ?>

                    </h4>
                    <small class="text-muted">Harga Pokok Penjualan</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2 small">Pengeluaran Operasional</p>
                    <h4 class="text-danger mb-0 fw-bold">
                        Rp <?php echo e(number_format($reportData['total_expense'] ?? 0, 0, ',', '.')); ?>

                    </h4>
                    <small class="text-muted">Beban Usaha</small>
                </div>
            </div>
        </div>

        <div class="col-12 mt-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body text-center">
                    <p class="mb-2 opacity-75">Keuntungan Bersih (Periode Ini)</p>
                    <h2 class="mb-0 fw-bold">
                        Rp <?php echo e(number_format($reportData['net_profit'] ?? 0, 0, ',', '.')); ?>

                    </h2>
                    <small class="opacity-75">Laba Kotor - Beban Operasional</small>
                </div>
            </div>
        </div>
    </div>

    
    
    
    <div class="mt-5 mb-4">
        <h5 class="mb-3 border-bottom pb-2">
            <i class="fas fa-wallet me-2"></i>Saldo Kas & Rekening (Realtime Akumulasi)
        </h5>
        <div class="row g-3">
            <?php if(isset($reportData['balances']) && count($reportData['balances']) > 0): ?>
            <?php $__currentLoopData = $reportData['balances']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $balance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="col-md-4">
                <div class="card h-100 border-start border-4 
                                <?php echo e($balance['slug'] == 'cash' ? 'border-success' : ($balance['slug'] == 'dana' ? 'border-primary' : 'border-info')); ?> shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1"><?php echo e($balance['name']); ?></h6>
                            <h4 class="fw-bold mb-0">Rp <?php echo e(number_format($balance['balance'], 0, ',', '.')); ?></h4>
                        </div>
                        <div class="fs-1 opacity-25">
                            <?php if($balance['slug'] == 'cash'): ?>
                            <i class="fas fa-money-bill-wave text-success"></i>
                            <?php elseif($balance['slug'] == 'dana'): ?>
                            <i class="fas fa-wallet text-primary"></i>
                            <?php else: ?>
                            <i class="fas fa-university text-info"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php else: ?>
            <div class="col-12">
                <div class="alert alert-light border">Belum ada data metode pembayaran.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <hr class="my-4">

    
    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Catatan:</strong> Sistem memisahkan <strong>HPP (COGS)</strong> dari <strong>Beban Operasional</strong>.
        HPP dihitung otomatis dari selisih harga jual dan harga beli produk, sedangkan Beban Operasional adalah pengeluaran seperti gaji, listrik, dan biaya usaha lainnya.
    </div>

    
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h5 class="mb-0">
            <i class="fas fa-stream me-2"></i>Rincian Arus Kas (Periode Ini)
        </h5>
        <?php if($reportData['cash_flows']->total() > 0): ?>
        <small class="text-muted">
            Menampilkan <?php echo e($reportData['cash_flows']->firstItem()); ?>–<?php echo e($reportData['cash_flows']->lastItem()); ?>

            dari <?php echo e($reportData['cash_flows']->total()); ?> transaksi
        </small>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <?php if (isset($component)) { $__componentOriginal163c8ba6efb795223894d5ffef5034f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal163c8ba6efb795223894d5ffef5034f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.table','data' => ['class' => 'table-hover']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'table-hover']); ?>
            <?php $__env->slot('thead'); ?>
            <tr>
                <th style="width: 10%;">Tanggal</th>
                <th style="width: 25%;">Deskripsi</th>
                <th style="width: 15%;" class="text-center">Metode</th>
                <th style="width: 15%;" class="text-center">Kategori</th>
                <th style="width: 5%;" class="text-center">Tipe</th>
                <th style="width: 15%;" class="text-end">Jumlah</th>
            </tr>
            <?php $__env->endSlot(); ?>

            <?php $__empty_1 = true; $__currentLoopData = $reportData['cash_flows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $flow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <td><?php echo e(\Carbon\Carbon::parse($flow->date)->format('d/m/Y')); ?></td>
                <td>
                    <small class="text-muted d-block">#<?php echo e($flow->id); ?></small>
                    <?php echo e(Str::limit($flow->description, 50)); ?>

                </td>
                <td class="text-center">
                    <?php
                    $badgeClass = match($flow->payment_method) {
                    'cash' => 'bg-success',
                    'dana' => 'bg-primary',
                    'transfer-bank' => 'bg-info text-dark',
                    default => 'bg-secondary'
                    };
                    ?>
                    <span class="badge <?php echo e($badgeClass); ?>">
                        <?php echo e(ucfirst(str_replace('-', ' ', $flow->payment_method ?? 'Cash'))); ?>

                    </span>
                </td>
                <td class="text-center">
                    <?php if($flow->category): ?>
                    <span class="badge <?php echo e($flow->category->is_cogs ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                        <?php echo e($flow->category->name); ?>

                    </span>
                    <?php else: ?>
                    <span class="badge bg-light text-dark">N/A</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if($flow->type == 'income'): ?>
                    <i class="fas fa-arrow-down text-success" title="Pemasukan"></i>
                    <?php else: ?>
                    <?php if($flow->category && $flow->category->is_cogs): ?>
                    <i class="fas fa-box text-warning" title="COGS"></i>
                    <?php else: ?>
                    <i class="fas fa-arrow-up text-danger" title="Pengeluaran"></i>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <span class="<?php echo e($flow->type == 'income' ? 'text-success' : 'text-danger'); ?> fw-bold">
                        <?php echo e($flow->type == 'expense' ? '-' : '+'); ?> Rp <?php echo e(number_format($flow->amount, 0, ',', '.')); ?>

                    </span>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
                <td colspan="6" class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-0">Tidak ada data arus kas untuk rentang tanggal yang dipilih.</p>
                </td>
            </tr>
            <?php endif; ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $attributes = $__attributesOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $component = $__componentOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__componentOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
    </div>

    
    
    
    <?php if($reportData['cash_flows']->hasPages()): ?>
    <div class="d-flex justify-content-center mt-2">
        <?php echo e($reportData['cash_flows']->onEachSide(1)->links('pagination::bootstrap-5')); ?>

    </div>
    <?php endif; ?>

    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-3">Formula Perhitungan:</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <strong>Laba Kotor</strong> = Total Pemasukan - HPP (dari harga jual - harga beli produk)
                        </li>
                        <li class="mb-2">
                            <strong>Laba Bersih</strong> = Laba Kotor - Beban Operasional
                        </li>
                        <li>
                            <strong>HPP (COGS)</strong> = Pengeluaran yang ditandai sebagai Harga Pokok Penjualan
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-3">Ringkasan Periode:</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <strong>Tanggal:</strong>
                            <?php echo e($reportData['filters']['start_date'] ?? '-'); ?>

                            s/d
                            <?php echo e($reportData['filters']['end_date'] ?? '-'); ?>

                        </li>
                        <li class="mb-2">
                            <strong>Total Transaksi:</strong> <?php echo e($reportData['cash_flows']->total()); ?> item
                        </li>
                        <li>
                            <strong>Total Penjualan:</strong> <?php echo e($reportData['transactions']->count()); ?> transaksi
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $attributes = $__attributesOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__attributesOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $component = $__componentOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__componentOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<style>
    /* Perkecil padding card body */
    .card-body {
        padding: 1rem !important;
    }

    /* Kurangi margin bawah breadcrumb */
    .breadcrumb {
        margin-bottom: 0.5rem;
    }

    /* Kurangi jarak antar card */
    .card {
        margin-bottom: 0.75rem;
    }

    /* Perkecil pagination dan hilangkan margin bawah */
    .pagination {
        margin-bottom: 0 !important;
        font-size: 0.875rem;
    }
    .pagination .page-link {
        padding: 0.25rem 0.5rem;
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            input.addEventListener('change', function() {
                // this.form.submit();
            });
        });
    });
</script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/admin/reports/financial.blade.php ENDPATH**/ ?>