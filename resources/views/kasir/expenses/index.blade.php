@extends('kasir.layouts.app') {{-- Sesuaikan jika file layout kasir berbeda --}}

@section('title', 'Daftar Pengeluaran')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Pengeluaran</li>
@endsection

@section('content')
    @include('components.alert') {{-- Memanggil komponen alert default sistem --}}

    {{-- Filter Data Menggunakan Komponen Anda --}}
    <x-card title="Filter Laporan">
        <form method="GET" action="{{ route('kasir.expenses.index') }}">
            <div class="row g-3">
                <div class="col-md-4 col-sm-6">
                    <x-input type="date" name="start_date" label="Dari Tanggal" :value="request('start_date')" />
                </div>
                <div class="col-md-4 col-sm-6">
                    <x-input type="date" name="end_date" label="Sampai Tanggal" :value="request('end_date')" />
                </div>
                <div class="col-md-4 col-12 d-flex align-items-end">
                    <x-button type="submit" variant="primary" class="me-2">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </x-button>
                    <a href="{{ route('kasir.expenses.index') }}" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </x-card>

    <div class="mt-3 mt-md-4"></div>

    {{-- Tabel Data --}}
    <x-card title="Rincian Pengeluaran Operasional">
        @slot('headerActions')
            <x-button href="{{ route('kasir.expenses.create') }}" variant="primary">
                <i class="fas fa-plus me-2"></i> Catat Pengeluaran
            </x-button>
        @endslot

        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th class="text-end">Jumlah</th>
                        <th>Dicatat Oleh</th>
                    </tr>
                @endslot
                @forelse ($expenses as $expense)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($expense->date)->isoFormat('D MMM YYYY') }}</td>
                        <td><span class="badge bg-secondary">{{ $expense->category->name ?? 'N/A' }}</span></td>
                        <td>{{ Str::limit($expense->description, 50) }}</td>
                        <td class="text-end text-danger fw-bold">Rp {{ number_format($expense->amount, 0, ',', '.') }}</td>
                        <td>{{ $expense->createdBy->name ?? 'Sistem' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-0">Tidak ada data pengeluaran yang ditemukan.</p>
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </div>

        {{-- PERBAIKAN PAGINASI: Menggunakan withQueryString & memanggil komponen khusus Anda --}}
        @if ($expenses->hasPages())
            <div class="mt-3">
                {{ $expenses->withQueryString()->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection