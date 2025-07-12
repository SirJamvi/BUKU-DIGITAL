 
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
                    <x-input type="date" name="start_date" label="Tanggal Mulai" :value="request()->get('start_date')" />
                </div>
                <div class="col-md-5">
                    <x-input type="date" name="end_date" label="Tanggal Akhir" :value="request()->get('end_date')" />
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
            <x-button variant="secondary"><i class="fas fa-print me-2"></i>Ekspor PDF</x-button>
        @endslot

        {{-- Ringkasan Data --}}
        <div class="row text-center mb-4 g-3">
            <div class="col-md-4">
                <div class="card"><div class="card-body"><p class="text-muted mb-1">Total Pemasukan</p><h4 class="text-success">Rp {{ number_format($reportData['total_income'] ?? 0, 0, ',', '.') }}</h4></div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body"><p class="text-muted mb-1">Total Pengeluaran</p><h4 class="text-danger">Rp {{ number_format($reportData['total_expense'] ?? 0, 0, ',', '.') }}</h4></div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body"><p class="text-muted mb-1">Keuntungan Bersih</p><h4 class="text-primary">Rp {{ number_format($reportData['net_profit'] ?? 0, 0, ',', '.') }}</h4></div></div>
            </div>
        </div>

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
                    <td>{{ $flow->category->name ?? 'N/A' }}</td>
                    <td class="text-end text-success">
                        {{ $flow->type == 'income' ? 'Rp ' . number_format($flow->amount, 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-end text-danger">
                        {{ $flow->type == 'expense' ? 'Rp ' . number_format($flow->amount, 0, ',', '.') : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Tidak ada data keuangan untuk rentang tanggal yang dipilih.</td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
@endsection