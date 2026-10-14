
<?php
    $classes = 'card ' . ($class ?? '');
?>

<div <?php echo e($attributes->merge(['class' => $classes])); ?>>
    <?php if(isset($title) || isset($headerActions)): ?>
        <div class="card-header d-flex justify-content-between align-items-center">
            <?php if(isset($title)): ?>
                <h5 class="card-title mb-0"><?php echo e($title); ?></h5>
            <?php endif; ?>
            
            <?php if(isset($headerActions)): ?>
                <div class="card-actions">
                    <?php echo e($headerActions); ?>

                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="card-body">
        <?php echo e($slot); ?>

    </div>
</div>  <?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/components/card.blade.php ENDPATH**/ ?>