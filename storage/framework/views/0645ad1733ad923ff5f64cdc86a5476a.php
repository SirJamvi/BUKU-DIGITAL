
<?php
    $classes = 'table-responsive ' . ($class ?? '');
?>

<div <?php echo e($attributes->merge(['class' => $classes])); ?> style="width: 100%;">
    <table class="table table-striped table-hover mb-0">
        <?php if(isset($thead)): ?>
            <thead class="table-dark">
                <?php echo e($thead); ?>

            </thead>
        <?php endif; ?>

        <tbody>
            <?php echo e($slot); ?>

        </tbody>
    </table>
</div><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/components/table.blade.php ENDPATH**/ ?>