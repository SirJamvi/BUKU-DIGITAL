{{-- resources/views/admin/financial/expenses.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Laporan Pengeluaran')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pengeluaran</li>
@endsection

@section('content')
    <x-card title="Rincian Data Pengeluaran">
        @slot('headerActions')
            <x-button href="{{ route('admin.expenses.create') }}" variant="primary">
                <i class="fas fa-plus me-2"></i>Catat Pengeluaran Baru
            </x-button>
            <x-button variant="secondary"><i class="fas fa-print me-2"></i>Ekspor PDF</x-button>
        @endslot

        @include('components.alert')

        <x-table>
            @slot('thead')
                <tr>
                    <th>Tanggal</th>
                    <th>Kategori</th>
                    <th>Deskripsi</th>
                    <th>Jumlah</th>
                    <th>Dicatat oleh</th>
                </tr>
            @endslot

            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($expense->date)->isoFormat('D MMMM YYYY') }}</td>
                    <td>{{ $expense->category->name ?? 'N/A' }}</td>
                    <td>{{ $expense->description }}</td>
                    <td class="text-end text-danger">Rp {{ number_format($expense->amount, 0, ',', '.') }}</td>
                    <td>{{ $expense->createdBy->name ?? 'Sistem' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Tidak ada data pengeluaran yang ditemukan.</td>
                </tr>
            @endforelse
        </x-table>

        {{-- Pagination --}}
        @if ($expenses->hasPages())
            <div class="mt-3">
                {{ $expenses->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection