<?php $__env->startSection('title', 'Detail Produk'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('kasir.products.index')); ?>">Lihat Produk</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Detail Produk: '.e($product->name).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Detail Produk: '.e($product->name).'']); ?>
        <?php $__env->slot('headerActions'); ?>
            <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['href' => ''.e(route('kasir.products.index')).'','variant' => 'secondary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('kasir.products.index')).'','variant' => 'secondary']); ?>
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar
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
        <?php $__env->endSlot(); ?>

        <div class="row">
            <div class="col-md-8">
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 30%;">Nama Produk</th>
                        <td><?php echo e($product->name); ?></td>
                    </tr>
                    <tr>
                        <th>SKU</th>
                        <td><?php echo e($product->sku); ?></td>
                    </tr>
                    <tr>
                        <th>Kategori</th>
                        <td><?php echo e($product->category->name ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Deskripsi</th>
                        <td><?php echo e($product->description ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Harga Jual</th>
                        <td class="fw-bold" style="color: var(--kasir-accent);">Rp <?php echo e(number_format($product->base_price, 0, ',', '.')); ?></td>
                    </tr>
                     <tr>
                        <th>Satuan</th>
                        <td><?php echo e($product->unit); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-4">
                 <div class="card text-center" style="background-color: var(--kasir-bg-secondary);">
                     <div class="card-body">
                         <p class="text-muted mb-1">Stok Tersedia</p>
                         <h1 class="display-4 fw-bold" style="color: var(--kasir-accent);"><?php echo e($product->inventory->current_stock ?? 0); ?></h1>
                         <p class="text-muted mb-0"><?php echo e($product->unit); ?></p>
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
<?php echo $__env->make('kasir.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/kasir/products/show.blade.php ENDPATH**/ ?>