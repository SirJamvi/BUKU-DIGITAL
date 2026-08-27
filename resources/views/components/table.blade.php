{{-- resources/views/components/table.blade.php --}}
@php
    $classes = 'table-responsive ' . ($class ?? '');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }} style="width: 100%;">
    <table class="table table-striped table-hover mb-0">
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