@props([
    'type' => 'button',
    'href' => null,
    'variant' => 'primary' // primary, secondary, success, danger, warning, info, light, dark
])

@php
    $classes = 'btn btn-' . $variant;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif

{{-- Cara pakai:
<x-button href="#" variant="success">Tombol Link</x-button>
<x-button type="submit" variant="danger">Tombol Submit</x-button>
--}}