 



<?php $__env->startSection('title', 'Pengaturan'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active" aria-current="page">Pengaturan</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Pusat Pengaturan']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Pusat Pengaturan']); ?>
        <p>Kelola pengaturan profil Anda dan konfigurasi umum sistem melalui menu di bawah ini.</p>
        
        <?php echo $__env->make('components.alert', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="list-group">
            <a href="<?php echo e(route('admin.settings.profile')); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-user-edit fa-fw me-2"></i>
                    <strong>Profil Saya</strong>
                    <br>
                    <small class="text-muted">Perbarui informasi pribadi Anda seperti nama, email, dan kata sandi.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
            <a href="<?php echo e(route('admin.settings.system')); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                 <div>
                    <i class="fas fa-cogs fa-fw me-2"></i>
                    <strong>Pengaturan Sistem</strong>
                    <br>
                    <small class="text-muted">Konfigurasi umum aplikasi seperti nama aplikasi dan mode pemeliharaan.</small>
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
<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/admin/settings/index.blade.php ENDPATH**/ ?>