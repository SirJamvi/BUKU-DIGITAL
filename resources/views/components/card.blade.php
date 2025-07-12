@props([
    'title' => '',
    'headerActions' => null
])

<div class="card">
    @if ($title)
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ $title }}</h5>
                @if ($headerActions)
                    <div>
                        {{ $headerActions }}
                    </div>
                @endif
            </div>
        </div>
    @endif
    
    <div class="card-body">
        {{ $slot }}
    </div>
</div>

{{-- Cara pakai:
<x-card title="Judul Kartu">
    ...Konten di sini...
</x-card>
--}}