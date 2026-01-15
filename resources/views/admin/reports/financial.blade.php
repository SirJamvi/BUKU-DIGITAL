{{-- resources/views/admin/reports/financial.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Laporan Keuangan')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.reports.index') }}">Laporan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Keuangan</li>
@endsection

@section('content')
    {{-- Form Filter --}}
    <x-card>
        @slot('title')
            Filter Laporan Keuangan
        @endslot
        <form method="GET" action="{{ route('admin.reports.financial') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <x-input 
                        type="date" 
                        name="start_date" 
                        label="Tanggal Mulai" 
                        :value="$reportData['filters']['start_date'] ?? now()->startOfMonth()->toDateString()" 
                    />
                </div>
                <div class="col-md-5">
                    <x-input 
                        type="date" 
                        name="end_date" 
                        label="Tanggal Akhir" 
                        :value="$reportData['filters']['end_date'] ?? now()->toDateString()" 
                    />
                </div>
                <div class="col-md-2">
                    <x-button type="submit" variant="primary" class="w-100">
                        <i class="fas fa-filter me-1"></i>Terapkan
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    <div class="mt-4"></div>

    {{-- Hasil Laporan --}}
    <x-card>
        @slot('title')
            Hasil Laporan Keuangan
        @endslot
        
        @slot('headerActions')
            <a href="{{ route('admin.reports.financial.export.pdf', request()->query()) }}" 
               class="btn btn-secondary btn-sm">
                <i class="fas fa-print me-2"></i>Ekspor PDF
            </a>
        @endslot

        {{-- Ringkasan Data --}}
        <div class="row text-center mb-4 g-3">
            {{-- Total Pemasukan --}}
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-muted mb-2 small">Total Pemasukan</p>
                        <h4 class="text-success mb-0 fw-bold">
                            Rp {{ number_format($reportData['total_income'] ?? 0, 0, ',', '.') }}
                        </h4>
                    </div>
                </div>
            </div>

            {{-- Keuntungan Kotor --}}
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-muted mb-2 small">Keuntungan Kotor</p>
                        <h4 class="text-info mb-0 fw-bold">
                            Rp {{ number_format($reportData['total_gross_profit'] ?? 0, 0, ',', '.') }}
                        </h4>
                    </div>
                </div>
            </div>

            {{-- Total HPP (COGS) --}}
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-muted mb-2 small">Total HPP (COGS)</p>
                        <h4 class="text-warning mb-0 fw-bold">
                            Rp {{ number_format($reportData['total_cogs'] ?? 0, 0, ',', '.') }}
                        </h4>
                        <small class="text-muted">Harga Pokok Penjualan</small>
                    </div>
                </div>
            </div>

            {{-- Total Pengeluaran Operasional --}}
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-muted mb-2 small">Pengeluaran Operasional</p>
                        <h4 class="text-danger mb-0 fw-bold">
                            Rp {{ number_format($reportData['total_expense'] ?? 0, 0, ',', '.') }}
                        </h4>
                        <small class="text-muted">Beban Usaha</small>
                    </div>
                </div>
            </div>

            {{-- Keuntungan Bersih --}}
            <div class="col-12 mt-3">
                <div class="card border-0 shadow-sm bg-primary text-white">
                    <div class="card-body text-center">
                        <p class="mb-2 opacity-75">Keuntungan Bersih</p>
                        <h2 class="mb-0 fw-bold">
                            Rp {{ number_format($reportData['net_profit'] ?? 0, 0, ',', '.') }}
                        </h2>
                        <small class="opacity-75">Laba Kotor - Beban Operasional</small>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        {{-- Info Pemisahan COGS dan Opex --}}
        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Catatan:</strong> Sistem memisahkan <strong>HPP (COGS)</strong> dari <strong>Beban Operasional</strong>. 
            HPP dihitung otomatis dari selisih harga jual dan harga beli produk, sedangkan Beban Operasional adalah pengeluaran seperti gaji, listrik, dan biaya usaha lainnya.
        </div>

        {{-- Tabel Rincian Arus Kas --}}
        <h5 class="mt-4 mb-3">
            <i class="fas fa-stream me-2"></i>Rincian Arus Kas (Pemasukan & Pengeluaran)
        </h5>
        
        <div class="table-responsive">
            <x-table class="table-hover">
                @slot('thead')
                    <tr>
                        <th style="width: 10%;">Tanggal</th>
                        <th style="width: 35%;">Deskripsi</th>
                        <th style="width: 15%;" class="text-center">Kategori</th>
                        <th style="width: 5%;" class="text-center">Tipe</th>
                        <th style="width: 17.5%;" class="text-end">Pemasukan</th>
                        <th style="width: 17.5%;" class="text-end">Pengeluaran</th>
                    </tr>
                @endslot
                
                @forelse ($reportData['cash_flows'] as $flow)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($flow->date)->format('d/m/Y') }}</td>
                        <td>
                            <small class="text-muted d-block">#{{ $flow->id }}</small>
                            {{ Str::limit($flow->description, 50) }}
                        </td>
                        <td class="text-center">
                            @if($flow->category)
                                <span class="badge {{ $flow->category->is_cogs ? 'bg-warning text-dark' : 'bg-secondary' }}">
                                    {{ $flow->category->name }}
                                </span>
                            @else
                                <span class="badge bg-light text-dark">N/A</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($flow->type == 'income')
                                <i class="fas fa-arrow-down text-success" title="Pemasukan"></i>
                            @else
                                @if($flow->category && $flow->category->is_cogs)
                                    <i class="fas fa-box text-warning" title="COGS"></i>
                                @else
                                    <i class="fas fa-arrow-up text-danger" title="Pengeluaran"></i>
                                @endif
                            @endif
                        </td>
                        <td class="text-end">
                            @if($flow->type == 'income')
                                <span class="text-success fw-bold">
                                    Rp {{ number_format($flow->amount, 0, ',', '.') }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($flow->type == 'expense')
                                <span class="text-danger fw-bold">
                                    Rp {{ number_format($flow->amount, 0, ',', '.') }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-0">Tidak ada data arus kas untuk rentang tanggal yang dipilih.</p>
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </div>

        @if($reportData['cash_flows']->count() >= 100)
            <div class="alert alert-warning mt-3" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Menampilkan 100 transaksi terakhir. Gunakan filter tanggal untuk melihat data lebih spesifik.
            </div>
        @endif

        {{-- Summary Footer --}}
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="mb-3">Formula Perhitungan:</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-2">
                                <strong>Laba Kotor</strong> = Total Pemasukan - HPP (dari harga jual - harga beli produk)
                            </li>
                            <li class="mb-2">
                                <strong>Laba Bersih</strong> = Laba Kotor - Beban Operasional
                            </li>
                            <li>
                                <strong>HPP (COGS)</strong> = Pengeluaran yang ditandai sebagai Harga Pokok Penjualan
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="mb-3">Ringkasan Periode:</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-2">
                                <strong>Tanggal:</strong> 
                                {{ $reportData['filters']['start_date'] ?? '-' }} 
                                s/d 
                                {{ $reportData['filters']['end_date'] ?? '-' }}
                            </li>
                            <li class="mb-2">
                                <strong>Total Transaksi:</strong> {{ $reportData['cash_flows']->count() }} item
                            </li>
                            <li>
                                <strong>Total Penjualan:</strong> {{ $reportData['transactions']->count() }} transaksi
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </x-card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form on date change (optional)
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Uncomment line below to auto-submit on date change
            // this.form.submit();
        });
    });
});
</script>
@endpush