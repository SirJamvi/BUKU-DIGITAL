{{-- resources/views/components/table.blade.php --}}
@php
    $classes = 'table-responsive ' . ($class ?? '');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    <table class="table table-striped table-hover">
        @if(isset($thead))
            <thead class="table-dark">
                {{ $thead }}
            </thead>
        @endif
        
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>