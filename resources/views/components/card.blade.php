{{-- resources/views/components/card.blade.php --}}
@php
    $classes = 'card ' . ($class ?? '');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    @if(isset($title) || isset($headerActions))
        <div class="card-header d-flex justify-content-between align-items-center">
            @if(isset($title))
                <h5 class="card-title mb-0">{{ $title }}</h5>
            @endif
            
            @if(isset($headerActions))
                <div class="card-actions">
                    {{ $headerActions }}
                </div>
            @endif
        </div>
    @endif
    
    <div class="card-body">
        {{ $slot }}
    </div>
</div>  