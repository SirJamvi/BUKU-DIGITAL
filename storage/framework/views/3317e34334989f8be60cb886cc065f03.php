 



<?php $__env->startSection('title', 'Pusat Laporan'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active" aria-current="page">Laporan</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Pilih Jenis Laporan']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Pilih Jenis Laporan']); ?>
        <p>Silakan pilih jenis laporan yang ingin Anda lihat. Setiap laporan dapat difilter berdasarkan rentang tanggal dan diekspor ke format PDF.</p>
        
        <?php echo $__env->make('components.alert', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="list-group">
            <a href="<?php echo e(route('admin.reports.sales')); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-chart-line fa-fw me-2"></i>
                    <strong>Laporan Penjualan</strong>
                    <br>
                    <small class="text-muted">Analisis detail semua transaksi penjualan, produk terlaris, dan kinerja kasir.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
            <a href="<?php echo e(route('admin.reports.financial')); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                 <div>
                    <i class="fas fa-file-invoice-dollar fa-fw me-2"></i>
                    <strong>Laporan Keuangan</strong>
                    <br>
                    <small class="text-muted">Rincian pemasukan, pengeluaran, dan keuntungan bersih bisnis Anda.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
            <a href="<?php echo e(route('admin.reports.inventory')); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                 <div>
                    <i class="fas fa-warehouse fa-fw me-2"></i>
                    <strong>Laporan Inventaris</strong>
                    <br>
                    <small class="text-muted">Laporan status stok produk, valuasi inventaris, dan pergerakan barang.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
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
<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/admin/reports/index.blade.php ENDPATH**/ ?>