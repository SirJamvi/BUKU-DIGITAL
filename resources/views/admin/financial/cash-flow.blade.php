{{-- resources/views/admin/financial/cash-flow.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Laporan Arus Kas (Cash Flow)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Arus Kas</li>
@endsection

@section('content')
    <x-card title="Data Arus Kas">
        @slot('headerActions')
            {{-- Tombol untuk filter atau ekspor bisa ditambahkan di sini --}}
            <x-button variant="secondary"><i class="fas fa-print me-2"></i>Ekspor PDF</x-button>
        @endslot

        @include('components.alert')

        <x-table>
            @slot('thead')
                <tr>
                    <th>Tanggal</th>
                    <th>Tipe</th>
                    <th>Kategori</th>
                    <th>Deskripsi</th>
                    <th>Jumlah</th>
                    <th>Dicatat oleh</th>
                </tr>
            @endslot

            @forelse ($cashFlows as $flow)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($flow->date)->isoFormat('D MMMM YYYY') }}</td>
                    <td>
                        @if ($flow->type == 'income')
                            <span class="badge bg-success">Pemasukan</span>
                        @else
                            <span class="badge bg-danger">Pengeluaran</span>
                        @endif
                    </td>
                    <td>{{ $flow->category->name ?? 'N/A' }}</td>
                    <td>{{ $flow->description }}</td>
                    <td class="text-end">Rp {{ number_format($flow->amount, 0, ',', '.') }}</td>
                    <td>{{ $flow->createdBy->name ?? 'Sistem' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada data arus kas yang ditemukan.</td>
                </tr>
            @endforelse
        </x-table>

        {{-- Pagination --}}
        @if ($cashFlows->hasPages())
            <div class="mt-3">
                {{ $cashFlows->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection