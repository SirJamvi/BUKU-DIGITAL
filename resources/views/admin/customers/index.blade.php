@extends('admin.layouts.app')

@section('title', 'Manajemen Pelanggan')
@section('breadcrumb')
    <li class="breadcrumb-item active">Pelanggan</li>
@endsection

@section('content')
    {{-- Statistik Pelanggan Bulanan --}}
    <div class="row mb-4">
        <div class="col-12">
            <x-card title="Statistik Pelanggan Baru per Bulan">
                <div class="row">
                    {{-- Grafik --}}
                    <div class="col-md-9">
                        <canvas id="monthlyCustomersChart" style="max-height: 300px;"></canvas>
                    </div>
                    
                    {{-- Summary Cards --}}
                    <div class="col-md-3">
                        <div class="card bg-primary text-white mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Bulan Ini</h6>
                                <h2 class="mb-0">{{ $monthlyStats[count($monthlyStats)-1]['total'] ?? 0 }}</h2>
                                <small>pelanggan baru</small>
                            </div>
                        </div>
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total 12 Bulan</h6>
                                <h2 class="mb-0">{{ collect($monthlyStats)->sum('total') }}</h2>
                                <small>pelanggan baru</small>
                            </div>
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Tabel Daftar Pelanggan --}}
    <x-card title="Daftar Semua Pelanggan">
        @include('components.alert')
        <x-table>
            @slot('thead')
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Kontak</th>
                    <th>Tanggal Bergabung</th>
                    <th class="text-center">Aksi</th>
                </tr>
            @endslot
            @forelse ($customers as $customer)
                <tr>
                    <td>{{ $customer->id }}</td>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->phone ?? $customer->email }}</td>
                    <td>{{ $customer->join_date->isoFormat('D MMMM YYYY') }}</td>
                    <td class="text-center">
                        <x-button href="{{ route('admin.customers.show', $customer->id) }}" variant="info" class="btn-sm">
                            <i class="fas fa-eye"></i> Detail
                        </x-button>

                        <x-button href="{{ route('admin.customers.edit', $customer->id) }}" variant="warning" class="btn-sm">
                            <i class="fas fa-edit"></i>
                        </x-button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center">Tidak ada data pelanggan.</td></tr>
            @endforelse
        </x-table>
        @if ($customers->hasPages())
            <div class="mt-3">{{ $customers->links('components.pagination') }}</div>
        @endif
    </x-card>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyCustomersChart');
    
    const monthlyData = @json($monthlyStats);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: monthlyData.map(item => item.month_short + ' ' + item.year),
            datasets: [{
                label: 'Pelanggan Baru',
                data: monthlyData.map(item => item.total),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + ' pelanggan';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>
@endpush