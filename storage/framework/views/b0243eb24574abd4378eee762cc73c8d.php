
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'type' => 'button',
    'href' => null,
    'variant' => 'primary'
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'type' => 'button',
    'href' => null,
    'variant' => 'primary'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $baseClasses = 'btn fw-semibold';
    $variantClasses = 'btn-' . $variant;

    // Kustomisasi untuk tema admin dan auth
    if ($variant === 'primary') {
        $variantClasses = 'btn-custom-primary';
    }
?>

<?php if($href): ?>
    <a href="<?php echo e($href); ?>" <?php echo e($attributes->merge(['class' => $baseClasses . ' ' . $variantClasses])); ?>>
        <?php echo e($slot); ?>

    </a>
<?php else: ?>
    <button type="<?php echo e($type); ?>" <?php echo e($attributes->merge(['class' => $baseClasses . ' ' . $variantClasses])); ?>>
        <?php echo e($slot); ?>

    </button>
<?php endif; ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/components/button.blade.php ENDPATH**/ ?>