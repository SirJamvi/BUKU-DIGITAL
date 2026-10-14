<?php $__env->startSection('title', 'Detail Transaksi'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('kasir.transactions.index')); ?>">Riwayat Transaksi</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail #<?php echo e($transaction->id); ?></li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
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
            Detail Transaksi #<?php echo e($transaction->id); ?>

        <?php $__env->endSlot(); ?>
        <?php $__env->slot('headerActions'); ?>
            <a href="#" class="btn btn-secondary" onclick="window.print(); return false;">
                <i class="fas fa-print me-2"></i>Cetak Struk
            </a>
        <?php $__env->endSlot(); ?>

        <div class="receipt-preview p-3">
            <div class="header text-center mb-4">
                <h4 class="fw-bold"><?php echo e(config('app.name', 'Toko Anda')); ?></h4>
                <p class="text-muted mb-0">Alamat Toko Anda di Sini<br>Telp: 08123456789</p>
            </div>
            
            <hr style="border-style: dashed;">

            <div class="info row mb-3">
                <div class="col-6">
                    <div><strong>No. Struk:</strong> #<?php echo e($transaction->id); ?></div>
                    <div><strong>Tanggal:</strong> <?php echo e($transaction->transaction_date->format('d/m/Y H:i')); ?></div>
                </div>
                <div class="col-6 text-end">
                    <div><strong>Kasir:</strong> <?php echo e($transaction->createdBy->name ?? 'N/A'); ?></div>
                    <div><strong>Pelanggan:</strong> <?php echo e($transaction->customer->name ?? 'Umum'); ?></div>
                </div>
            </div>
            
            <hr style="border-style: dashed;">

            <div class="items-table">
                <?php $__currentLoopData = $transaction->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="row g-2 mb-2">
                        <div class="col-12"><strong><?php echo e($item->product->name); ?></strong></div>
                        <div class="col-6"><?php echo e($item->quantity); ?> x Rp <?php echo e(number_format($item->unit_price, 0, ',', '.')); ?></div>
                        <div class="col-6 text-end fw-bold">Rp <?php echo e(number_format($item->total_price, 0, ',', '.')); ?></div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <hr style="border-style: dashed;">
            
            <div class="totals mt-3">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Subtotal</span>
                    <span>Rp <?php echo e(number_format($transaction->total_amount, 0, ',', '.')); ?></span>
                </div>
                <div class="d-flex justify-content-between fs-5 fw-bold mt-2 pt-2 border-top">
                    <span >Total</span>
                    <span style="color: var(--kasir-accent);">Rp <?php echo e(number_format($transaction->total_amount, 0, ',', '.')); ?></span>
                </div>
                 <div class="d-flex justify-content-between mt-1">
                    <span class="text-muted">Metode Pembayaran</span>
                    <span><?php echo e($transaction->payment_method); ?></span>
                </div>
            </div>

            <div class="footer text-center mt-4">
                <p class="text-muted">--- Terima kasih telah berbelanja ---</p>
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

    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-preview, .receipt-preview * {
                visibility: visible;
            }
            .receipt-preview {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('kasir.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/kasir/transactions/show.blade.php ENDPATH**/ ?>