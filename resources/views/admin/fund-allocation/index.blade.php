{{-- resources/views/admin/fund-allocation/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Dashboard Alokasi Dana')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Alokasi Dana</li>
@endsection

@section('content')
    <div class="container-fluid">
        @include('components.alert')

        <div class="row g-4">
            {{-- Kartu Net Profit --}}
            <div class="col-lg-4">
                <div class="card bg-info text-white h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="fas fa-money-bill-wave me-2"></i>Keuntungan Bersih Saat Ini</h5>
                        <h1 class="display-5 fw-bold">Rp {{ number_format($allocationData['net_profit'] ?? 0, 0, ',', '.') }}</h1>
                        <p class="mb-0">Total keuntungan bersih yang siap untuk dialokasikan.</p>
                    </div>
                </div>
            </div>

            {{-- Kartu Visualisasi Alokasi --}}
            <div class="col-lg-8">
                <x-card>
                    @slot('title')
                        <i class="fas fa-chart-pie me-2"></i>Visualisasi Alokasi Dana
                    @endslot
                    @slot('headerActions')
                        <x-button href="{{ route('admin.fund-allocation.settings') }}" variant="primary">
                            <i class="fas fa-cog me-2"></i>Ubah Pengaturan
                        </x-button>
                    @endslot
                    
                    @if (($allocationData['net_profit'] ?? 0) > 0 && $allocationData['settings']->isNotEmpty())
                        {{-- Data JSON dipindahkan ke atribut data-* untuk menghindari error linter --}}
                        <canvas id="allocationPieChart" 
                                data-net-profit="{{ $allocationData['net_profit'] }}"
                                data-settings="{{ json_encode($allocationData['settings']) }}">
                        </canvas>
                    @else
                        <div class="text-center p-5">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Data keuntungan atau pengaturan alokasi belum tersedia untuk ditampilkan.</p>
                        </div>
                    @endif
                </x-card>
            </div>
        </div>

        {{-- Tabel Rincian Alokasi --}}
        <div class="row mt-4">
            <div class="col-12">
                <x-card title="Rincian Alokasi Dana">
                     <x-table>
                        @slot('thead')
                            <tr>
                                <th>Kategori Alokasi</th>
                                <th>Persentase</th>
                                <th>Jumlah Alokasi</th>
                            </tr>
                        @endslot
                        @forelse ($allocationData['settings'] as $setting)
                            <tr>
                                <td>{{ $setting->allocation_name }}</td>
                                <td>{{ $setting->percentage }}%</td>
                                <td class="fw-bold">Rp {{ number_format(($allocationData['net_profit'] * $setting->percentage) / 100, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center">Pengaturan alokasi belum dibuat.</td>
                            </tr>
                        @endforelse
                         @if ($allocationData['settings']->isNotEmpty())
                            @slot('tfoot')
                                <tr class="table-primary">
                                    <td colspan="2" class="text-end fw-bold">Total Alokasi</td>
                                    <td class="fw-bold">Rp {{ number_format(($allocationData['net_profit'] * $allocationData['settings']->sum('percentage')) / 100, 0, ',', '.') }}</td>
                                </tr>
                            @endslot
                         @endif
                    </x-table>
                </x-card>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const chartCanvas = document.getElementById('allocationPieChart');
        
        // Cek jika elemen canvas ada
        if (chartCanvas) {
            // Ambil data dari atribut data-*
            const netProfit = parseFloat(chartCanvas.dataset.netProfit);
            const settings = JSON.parse(chartCanvas.dataset.settings);

            if (netProfit > 0 && settings.length > 0) {
                const ctx = chartCanvas.getContext('2d');
                const labels = settings.map(s => s.allocation_name);
                const data = settings.map(s => (netProfit * s.percentage) / 100);
                
                const backgroundColors = [
                    '#0d6efd', '#6c757d', '#198754', '#dc3545', 
                    '#ffc107', '#0dcaf0', '#212529', '#f8f9fa'
                ];

                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Alokasi Dana',
                            data: data,
                            backgroundColor: backgroundColors.slice(0, data.length),
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed !== null) {
                                            const value = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(context.parsed);
                                            label += value;
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    });
</script>
@endpush