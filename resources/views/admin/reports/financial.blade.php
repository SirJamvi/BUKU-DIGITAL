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
                    <x-input type="date" name="start_date" label="Tanggal Mulai" :value="$reportData['filters']['start_date'] ?? now()->startOfMonth()->toDateString()" />
                </div>
                <div class="col-md-5">
                    <x-input type="date" name="end_date" label="Tanggal Akhir" :value="$reportData['filters']['end_date'] ?? now()->toDateString()" />
                </div>
                <div class="col-md-2">
                    <x-button type="submit" variant="primary" class="w-100">Terapkan</x-button>
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
    {{-- request()->query() akan meneruskan parameter filter (start_date, end_date) ke URL ekspor --}}
    <a href="{{ route('admin.reports.financial.export.pdf', request()->query()) }}" class="btn btn-secondary">
        <i class="fas fa-print me-2"></i>Ekspor PDF
    </a>
@endslot

        {{-- Ringkasan Data --}}
        <div class="row text-center mb-4 g-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total Pemasukan</p>
                        <h4 class="text-success">Rp {{ number_format($reportData['total_income'] ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Keuntungan Kotor</p>
                        <h4 class="text-info">Rp {{ number_format($reportData['total_gross_profit'] ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total Pengeluaran</p>
                        <h4 class="text-danger">Rp {{ number_format($reportData['total_expense'] ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Keuntungan Bersih</p>
                        <h4 class="text-primary">Rp {{ number_format($reportData['net_profit'] ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        {{-- Tabel Rincian Arus Kas --}}
        <h5 class="mt-4">Rincian Arus Kas (Pemasukan & Pengeluaran)</h5>
        <x-table>
            @slot('thead')
                <tr>
                    <th>Tanggal</th>
                    <th>Deskripsi</th>
                    <th>Kategori</th>
                    <th class="text-end">Pemasukan</th>
                    <th class="text-end">Pengeluaran</th>
                </tr>
            @endslot
            @forelse ($reportData['cash_flows'] as $flow)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($flow->date)->isoFormat('D MMM YYYY') }}</td>
                    <td>{{ $flow->description }}</td>
                    <td><span class="badge bg-secondary">{{ $flow->category->name ?? 'N/A' }}</span></td>
                    <td class="text-end text-success">
                        {{ $flow->type == 'income' ? 'Rp ' . number_format($flow->amount, 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-end text-danger">
                        {{ $flow->type == 'expense' ? 'Rp ' . number_format($flow->amount, 0, ',', '.') : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Tidak ada data arus kas untuk rentang tanggal yang dipilih.</td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
@endsection
