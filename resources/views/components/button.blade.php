{{-- resources/views/components/button.blade.php --}}
@props([
    'type' => 'button',
    'href' => null,
    'variant' => 'primary'
])

@php
    $baseClasses = 'btn fw-semibold';
    $variantClasses = 'btn-' . $variant;

    // Kustomisasi untuk tema admin dan auth
    if ($variant === 'primary') {
        $variantClasses = 'btn-custom-primary';
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $baseClasses . ' ' . $variantClasses]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $baseClasses . ' ' . $variantClasses]) }}>
        {{ $slot }}
    </button>
@endif