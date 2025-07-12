{{-- resources/views/admin/fund-allocation/history.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Riwayat Alokasi Dana')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fund-allocation.index') }}">Alokasi Dana</a></li>
    <li class="breadcrumb-item active" aria-current="page">Riwayat</li>
@endsection

@section('content')
    <x-card title="Tabel Riwayat Alokasi Dana">
         @slot('headerActions')
            <x-button variant="secondary"><i class="fas fa-print me-2"></i>Ekspor PDF</x-button>
        @endslot

        @include('components.alert')

        <x-table>
            @slot('thead')
                <tr>
                    <th>Periode</th>
                    <th>Nama Alokasi</th>
                    <th>Kategori</th>
                    <th>%</th>
                    <th>Jumlah Dialokasikan</th>
                    <th>Status</th>
                    <th>Dicatat Oleh</th>
                    <th>Tanggal Alokasi</th>
                </tr>
            @endslot

            @forelse ($history as $record)
                <tr>
                    <td>{{ \Carbon\Carbon::create()->month($record->period_month)->year($record->period_year)->isoFormat('MMMM YYYY') }}</td>
                    <td>{{ $record->allocation_name }}</td>
                    <td><span class="badge bg-secondary">{{ $record->allocation_category }}</span></td>
                    <td>{{ $record->allocation_percentage }}%</td>
                    <td class="text-end">Rp {{ number_format($record->allocated_amount, 0, ',', '.') }}</td>
                    <td>
                        @if ($record->is_manual)
                            <span class="badge bg-warning">Manual</span>
                        @else
                            <span class="badge bg-info">Otomatis</span>
                        @endif
                    </td>
                    <td>{{ $record->createdBy->name ?? 'Sistem' }}</td>
                    <td>{{ $record->allocated_at ? \Carbon\Carbon::parse($record->allocated_at)->isoFormat('D MMM YYYY, HH:mm') : 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Tidak ada riwayat alokasi yang ditemukan.</td>
                </tr>
            @endforelse
        </x-table>

        {{-- Pagination --}}
        @if ($history->hasPages())
            <div class="mt-3">
                {{ $history->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection