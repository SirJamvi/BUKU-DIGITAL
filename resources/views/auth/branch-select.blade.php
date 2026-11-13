{{-- resources/views/auth/branch-select.blade.php --}}
@extends('auth.guest')

@section('title', 'Pilih Cabang Aktif')

@section('content')
    <div class="text-center mb-4">
        <h5 class="card-title mb-1">Pilih Cabang Bertugas</h5>
        <p class="text-muted small">Anda terdaftar di beberapa cabang. Silakan pilih cabang yang sedang aktif untuk sesi ini.</p>
    </div>

    @include('components.alert')

    <div class="list-group mb-3">
        @php
            $branchList = $branches ?? (auth()->check() ? auth()->user()->activeAccessibleBranches() : collect());
        @endphp
        @foreach($branchList as $branch)
            <a href="{{ route('branch.switch', ['branchId' => $branch->id]) }}" 
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 mb-2 rounded border">
                <div>
                    <h6 class="mb-1 text-primary fw-bold">{{ $branch->name }}</h6>
                    <small class="text-muted"><i class="fas fa-barcode me-1"></i>Kode: {{ $branch->code }}</small>
                    @if($branch->address)
                        <br><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>{{ $branch->address }}</small>
                    @endif
                </div>
                <span class="btn btn-sm btn-outline-primary">
                    Pilih <i class="fas fa-arrow-right ms-1"></i>
                </span>
            </a>
        @endforeach
    </div>

    <div class="text-center mt-3">
        <form action="{{ route('logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-link text-muted btn-sm text-decoration-none">
                <i class="fas fa-sign-out-alt me-1"></i>Keluar / Ganti Akun
            </button>
        </form>
    </div>
@endsection

