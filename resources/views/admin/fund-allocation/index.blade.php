@extends('admin.layouts.app')

@section('title', 'Dashboard Alokasi Dana')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Alokasi Dana</li>
@endsection

@section('content')
    <div id="allocation-data-container" data-allocation='@json($allocationData)'></div>

    <div class="container-fluid">
        @include('components.alert')

        {{-- Tampilan jika ada profit yang belum dialokasikan --}}
        @if($allocationData['view_type'] === 'pending')
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="fas fa-exclamation-triangle me-2"></i>Keuntungan Bersih Siap Alokasi</h5>
                            <h1 class="display-5 fw-bold">Rp {{ number_format($allocationData['net_profit_to_allocate'] ?? $allocationData['net_profit'] ?? 0, 0, ',', '.') }}</h1>
                            <p class="mb-2">Total keuntungan bersih yang siap untuk dialokasikan dari periode yang belum dialokasikan.</p>
                            <form action="{{ route('admin.fund-allocation.process') }}" method="POST">
                                @csrf
                                @foreach($allocationData['pending_profits'] as $profit)
                                    <input type="hidden" name="owner_profit_ids[]" value="{{ $profit->id }}">
                                @endforeach
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle me-2"></i> Proses Alokasi Dana Sekarang
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <x-card title="Visualisasi Estimasi Alokasi Dana">
                        @if($allocationData['settings']->isNotEmpty())
                            <canvas id="allocationPieChart"></canvas>
                        @else
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Pengaturan alokasi belum dibuat. Silakan buat pengaturan alokasi terlebih dahulu.
                            </div>
                        @endif
                    </x-card>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <x-card title="Estimasi Rincian Alokasi Dana yang Akan Diproses">
                        {{-- [FIX] Menggunakan 'net_profit_to_allocate' untuk kalkulasi --}}
                        @php $profitForDisplay = $allocationData['net_profit_to_allocate'] ?? $allocationData['net_profit'] ?? 0; @endphp
                        <x-table>
                            @slot('thead')
                                <tr>
                                    <th>Kategori Alokasi</th>
                                    <th>Persentase</th>
                                    <th>Jumlah Alokasi</th>
                                </tr>
                            @endslot
                            
                            @forelse($allocationData['settings'] as $setting)
                                <tr>
                                    <td>{{ $setting->allocation_name }}</td>
                                    <td>{{ $setting->percentage }}%</td>
                                    <td class="fw-bold">Rp {{ number_format(($profitForDisplay * $setting->percentage) / 100, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center">Pengaturan alokasi belum dibuat.</td>
                                </tr>
                            @endforelse
                            
                            @if($allocationData['settings']->isNotEmpty())
                                @slot('tfoot')
                                    <tr class="table-primary">
                                        <td colspan="2" class="text-end fw-bold">Total Alokasi</td>
                                        <td class="fw-bold">Rp {{ number_format($profitForDisplay, 0, ',', '.') }}</td>
                                    </tr>
                                @endslot
                            @endif
                        </x-table>
                    </x-card>
                </div>
            </div>

        {{-- Tampilan ringkasan alokasi terakhir --}}
        @elseif($allocationData['view_type'] === 'summary')
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="fas fa-check-circle me-2"></i>Keuntungan Terakhir Dialokasikan</h5>
                            <h1 class="display-5 fw-bold">Rp {{ number_format($allocationData['last_allocated_profit'] ?? $allocationData['net_profit'] ?? 0, 0, ',', '.') }}</h1>
                            <p class="mb-0">Ringkasan dari alokasi dana terakhir yang diproses.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <x-card title="Visualisasi Alokasi Dana Terakhir">
                        @if($allocationData['history']->isNotEmpty())
                            <canvas id="allocationPieChart"></canvas>
                        @else
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Belum ada data historis alokasi dana.
                            </div>
                        @endif
                    </x-card>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <x-card title="Rincian Alokasi Dana Terakhir">
                        <x-table>
                            @slot('thead')
                                <tr>
                                    <th>Kategori Alokasi</th>
                                    <th>Persentase</th>
                                    <th>Jumlah Alokasi</th>
                                    <th>Tanggal Alokasi</th>
                                </tr>
                            @endslot
                            
                            @forelse($allocationData['history'] as $historyItem)
                                <tr>
                                    <td>{{ $historyItem->allocation_name }}</td>
                                    <td>{{ $historyItem->allocation_percentage }}%</td>
                                    <td class="fw-bold">Rp {{ number_format($historyItem->allocated_amount, 0, ',', '.') }}</td>
                                    <td>{{ $historyItem->allocated_at->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">Belum ada data historis alokasi.</td>
                                </tr>
                            @endforelse
                            
                            @if($allocationData['history']->isNotEmpty())
                                @slot('tfoot')
                                    <tr class="table-primary">
                                        <td colspan="2" class="text-end fw-bold">Total Alokasi</td>
                                        <td colspan="2" class="fw-bold">Rp {{ number_format($allocationData['history']->sum('allocated_amount'), 0, ',', '.') }}</td>
                                    </tr>
                                @endslot
                            @endif
                        </x-table>
                    </x-card>
                </div>
            </div>

        {{-- Tampilan jika kosong --}}
        @else
            <div class="text-center p-5 bg-light rounded">
                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">Belum Ada Data Keuntungan</h4>
                <p>Silakan lakukan proses "Tutup Buku" di menu Finansial untuk menghitung keuntungan bersih bulanan Anda.</p>
                <a href="{{ route('admin.financial.index') }}" class="btn btn-primary mt-3">
                    <i class="fas fa-calculator me-2"></i>Ke Halaman Finansial
                </a>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const chartCanvas = document.getElementById('allocationPieChart');
        const dataContainer = document.getElementById('allocation-data-container');
        
        if (chartCanvas && dataContainer) {
            const allocationData = JSON.parse(dataContainer.dataset.allocation);
            let labels, data;

            if (allocationData.view_type === 'pending' && allocationData.settings) {
                // Untuk tampilan pending, gunakan net_profit_to_allocate atau fallback ke net_profit
                const profitAmount = allocationData.net_profit_to_allocate || allocationData.net_profit || 0;
                labels = allocationData.settings.map(s => s.allocation_name);
                data = allocationData.settings.map(s => (profitAmount * s.percentage) / 100);
            } else if (allocationData.view_type === 'summary' && allocationData.history) {
                labels = allocationData.history.map(h => h.allocation_name);
                data = allocationData.history.map(h => parseFloat(h.allocated_amount));
            }

            if (labels && data && labels.length > 0 && data.length > 0) {
                const ctx = chartCanvas.getContext('2d');
                const backgroundColors = [
                    '#0d6efd', '#198754', '#ffc107', '#dc3545', 
                    '#6c757d', '#0dcaf0', '#6f42c1', '#fd7e14'
                ];
                
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: backgroundColors.slice(0, data.length),
                            borderColor: '#fff',
                            borderWidth: 2,
                            hoverBorderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'top',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const percentage = ((context.raw / data.reduce((a, b) => a + b, 0)) * 100).toFixed(1);
                                        return `${context.label}: Rp ${context.raw.toLocaleString('id-ID')} (${percentage}%)`;
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