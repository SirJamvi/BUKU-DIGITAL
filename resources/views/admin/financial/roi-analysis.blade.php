{{-- resources/views/admin/financial/roi-analysis.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Analisis ROI')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Analisis ROI</li>
@endsection

@section('content')
    <div class="container-fluid">
        {{-- Alert untuk warning atau sukses --}}
        @include('components.alert')
        
        {{-- Warning jika data tidak lengkap --}}
        @if(isset($debugInfo['warning_message']))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Peringatan:</strong> {{ $debugInfo['warning_message'] }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Modal untuk input modal awal jika data kosong --}}
        @if(!($roiData['has_capital_data'] ?? true))
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Inisialisasi Data Modal Awal
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">
                                Untuk mendapatkan analisis ROI yang akurat, silakan input modal awal bisnis Anda.
                            </p>
                            <form action="{{ route('admin.financial.initialize-data') }}" method="POST" class="row g-3">
                                @csrf
                                <div class="col-md-6">
                                    <label for="initial_capital" class="form-label">Modal Awal Bisnis</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" class="form-control @error('initial_capital') is-invalid @enderror" 
                                               id="initial_capital" name="initial_capital" 
                                               value="{{ old('initial_capital', '10000000') }}" 
                                               placeholder="Contoh: 10000000"
                                               pattern="[0-9,]*"
                                               inputmode="numeric"
                                               required>
                                        @error('initial_capital')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <small class="form-text text-muted">Minimal Rp 1.000.000 (input angka saja tanpa koma)</small>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save me-2"></i>Simpan Modal Awal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Card untuk Sinkronisasi Data --}}
        <div class="card border-primary mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title text-primary mb-1">Sinkronisasi Profit Bulanan</h5>
                        <p class="card-text text-muted mb-0">
                            Data ROI tidak sinkron? Klik tombol ini untuk menghitung ulang profit bulan ini berdasarkan data transaksi terbaru.
                        </p>
                    </div>
                    <form action="{{ route('admin.financial.process-monthly-closing') }}" method="POST">
                        @csrf
                        {{-- Mengirim periode bulan dan tahun saat ini (misal: 2025-07) --}}
                        <input type="hidden" name="period" value="{{ now()->format('Y-m') }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-2"></i>
                            Sinkronkan Profit Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Kartu ROI --}}
            <div class="col-md-12">
                <div class="card {{ ($roiData['roi'] ?? 0) >= 0 ? 'bg-success' : 'bg-danger' }} text-white text-center">
                    <div class="card-body">
                        <h5 class="card-title">RETURN ON INVESTMENT (ROI)</h5>
                        <h1 class="display-4 fw-bold">{{ $roiData['roi'] ?? 0 }}%</h1>
                        <p class="lead">
                            @if(($roiData['roi'] ?? 0) > 0)
                                Investasi Anda menghasilkan keuntungan yang baik!
                            @elseif(($roiData['roi'] ?? 0) < 0)
                                Investasi Anda mengalami kerugian. Perlu evaluasi strategi bisnis.
                            @else
                                Investasi Anda dalam kondisi impas (break-even point).
                            @endif
                        </p>
                        @if(isset($debugInfo['data_source']))
                            <small class="opacity-75">
                                <i class="fas fa-info-circle me-1"></i>
                                Sumber data: {{ $debugInfo['data_source'] }}
                            </small>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Kartu Modal Awal --}}
            <div class="col-md-6">
                <x-card title="Total Modal Investasi">
                    <div class="text-center">
                        <i class="fas fa-money-bill-wave fa-3x text-info mb-3"></i>
                        <h3>Rp {{ number_format($roiData['initial_capital'] ?? 0, 0, ',', '.') }}</h3>
                        <p class="text-muted">
                            @if(($roiData['has_capital_data'] ?? true))
                                Jumlah total modal awal dan tambahan modal yang telah diinvestasikan.
                            @else
                                Estimasi modal awal berdasarkan data yang tersedia.
                            @endif
                        </p>
                    </div>
                </x-card>
            </div>

            {{-- Kartu Total Keuntungan --}}
            <div class="col-md-6">
                <x-card title="Total Keuntungan Bersih">
                    <div class="text-center">
                        <i class="fas fa-chart-line fa-3x {{ ($roiData['total_profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }} mb-3"></i>
                        <h3 class="{{ ($roiData['total_profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                            Rp {{ number_format($roiData['total_profit'] ?? 0, 0, ',', '.') }}
                        </h3>
                        <p class="text-muted">
                            @if(($roiData['total_profit'] ?? 0) > 0)
                                Bisnis Anda menghasilkan keuntungan yang positif.
                            @elseif(($roiData['total_profit'] ?? 0) < 0)
                                Bisnis mengalami kerugian. Evaluasi diperlukan.
                            @else
                                Bisnis dalam kondisi impas (break-even).
                            @endif
                        </p>
                    </div>
                </x-card>
            </div>

            {{-- Kartu Penjelasan Rumus --}}
            <div class="col-md-12">
                <x-card title="Bagaimana ROI Dihitung?">
                    <div class="row">
                        <div class="col-md-8">
                            <p>ROI (Return on Investment) dihitung untuk mengukur efisiensi sebuah investasi. Rumus yang digunakan adalah:</p>
                            <div class="bg-light p-3 rounded text-center mb-3">
                                <code class="fs-5">ROI = (Total Keuntungan Bersih / Total Modal Investasi) × 100%</code>
                            </div>
                            <p>
                                <strong>Contoh Perhitungan:</strong><br>
                                Modal Awal: Rp {{ number_format($roiData['initial_capital'] ?? 0, 0, ',', '.') }}<br>
                                Total Keuntungan: Rp {{ number_format($roiData['total_profit'] ?? 0, 0, ',', '.') }}<br>
                                ROI = ({{ number_format($roiData['total_profit'] ?? 0, 0, ',', '.') }} / {{ number_format($roiData['initial_capital'] ?? 1, 0, ',', '.') }}) × 100% = {{ $roiData['roi'] ?? 0 }}%
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6>Interpretasi ROI:</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <span class="badge bg-success me-2">Positif</span>
                                    Investasi menguntungkan
                                </li>
                                <li class="mb-2">
                                    <span class="badge bg-warning me-2">0%</span>
                                    Break-even point
                                </li>
                                <li class="mb-2">
                                    <span class="badge bg-danger me-2">Negatif</span>
                                    Investasi merugi
                                </li>
                            </ul>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const initialCapitalInput = document.getElementById('initial_capital');
        
        if (initialCapitalInput) {
            // Function to format number with thousand separators
            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
            
            // Function to remove formatting and get raw number
            function getRawNumber(str) {
                return str.replace(/[^\d]/g, ''); // Remove all non-digits
            }
            
            // Handle input formatting
            initialCapitalInput.addEventListener('input', function(e) {
                let value = getRawNumber(e.target.value);
                
                if (value) {
                    // Format with thousand separators
                    const formattedValue = formatNumber(value);
                    e.target.value = formattedValue;
                } else {
                    e.target.value = '';
                }
            });
            
            // Handle paste event
            initialCapitalInput.addEventListener('paste', function(e) {
                setTimeout(() => {
                    let value = getRawNumber(e.target.value);
                    if (value) {
                        const formattedValue = formatNumber(value);
                        e.target.value = formattedValue;
                    }
                }, 0);
            });
            
            // Before form submission, convert formatted value back to raw number
            const form = initialCapitalInput.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Convert formatted value back to raw number before sending
                    const rawValue = getRawNumber(initialCapitalInput.value);
                    initialCapitalInput.value = rawValue;
                });
            }
        }
    });
</script>
@endpush