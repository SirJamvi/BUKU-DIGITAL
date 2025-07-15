@extends('admin.layouts.app')

@section('title', 'Dashboard Alokasi Dana')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Alokasi Dana</li>
@endsection

@section('content')
    {{-- [FIX] Menyimpan data di atribut data-* untuk diteruskan ke JavaScript --}}
    <div id="allocation-data-container" data-allocation='@json($allocationData)'></div>

    <div class="container-fluid">
        @include('components.alert')

        <div class="row g-4">
            {{-- Kartu Net Profit --}}
            <div class="col-lg-4">
                <div class="card {{ $allocationData['view_type'] == 'pending' ? 'bg-info' : 'bg-secondary' }} text-white h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            @if ($allocationData['view_type'] == 'pending')
                                Keuntungan Bersih Siap Alokasi
                            @else
                                Keuntungan Terakhir Dialokasikan
                            @endif
                        </h5>
                        <h1 class="display-5 fw-bold">Rp {{ number_format($allocationData['net_profit'] ?? 0, 0, ',', '.') }}</h1>
                        <p class="mb-0">
                            @if ($allocationData['view_type'] == 'pending')
                                Total keuntungan bersih yang siap untuk dialokasikan.
                            @else
                                Ringkasan dari alokasi dana terakhir yang diproses.
                            @endif
                        </p>
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
                    
                    @if (($allocationData['net_profit'] ?? 0) > 0 && ($allocationData['settings']->isNotEmpty() || $allocationData['history']->isNotEmpty()))
                        <canvas id="allocationPieChart"></canvas>
                    @else
                        <div class="text-center p-5">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Data keuntungan atau pengaturan alokasi belum tersedia untuk ditampilkan.</p>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- Kartu Aksi untuk Proses Alokasi (hanya muncul jika ada profit pending) --}}
            @if ($allocationData['view_type'] == 'pending' && $allocationData['pending_profits']->isNotEmpty())
                <div class="col-lg-12">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Tindakan Alokasi</h5>
                        </div>
                        <div class="card-body">
                            <p>Terdapat keuntungan yang siap dialokasikan. Klik tombol di bawah untuk memprosesnya.</p>
                            <form action="{{ route('admin.fund-allocation.process') }}" method="POST">
                                @csrf
                                <input type="hidden" name="owner_profit_id" value="{{ $allocationData['pending_profits']->first()->id }}">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle me-2"></i>Proses Alokasi Dana Sekarang
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Tabel Rincian Alokasi (sekarang bisa menampilkan data histori) --}}
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
                        
                        @if ($allocationData['view_type'] == 'pending')
                            @forelse ($allocationData['settings'] as $setting)
                                <tr>
                                    <td>{{ $setting->allocation_name }}</td>
                                    <td>{{ $setting->percentage }}%</td>
                                    <td class="fw-bold">Rp {{ number_format(($allocationData['net_profit'] * $setting->percentage) / 100, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center">Pengaturan alokasi belum dibuat.</td></tr>
                            @endforelse
                        @else
                             @forelse ($allocationData['history'] as $historyRecord)
                                <tr>
                                    <td>{{ $historyRecord->allocation_name }}</td>
                                    <td>{{ $historyRecord->allocation_percentage }}%</td>
                                    <td class="fw-bold">Rp {{ number_format($historyRecord->allocated_amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center">Tidak ada riwayat alokasi untuk ditampilkan.</td></tr>
                            @endforelse
                        @endif
                        
                         @if ($allocationData['history']->isNotEmpty() || ($allocationData['view_type'] == 'pending' && $allocationData['settings']->isNotEmpty()))
                            @slot('tfoot')
                                <tr class="table-primary">
                                    <td colspan="2" class="text-end fw-bold">Total Alokasi</td>
                                    <td class="fw-bold">
                                        @if ($allocationData['view_type'] == 'pending')
                                            Rp {{ number_format(($allocationData['net_profit'] * $allocationData['settings']->sum('percentage')) / 100, 0, ',', '.') }}
                                        @else
                                            Rp {{ number_format($allocationData['history']->sum('allocated_amount'), 0, ',', '.') }}
                                        @endif
                                    </td>
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
        // [FIX FINAL] Mengambil data dari atribut data-* pada elemen HTML
        const dataContainer = document.getElementById('allocation-data-container');
        
        if (chartCanvas && dataContainer) {
            const allocationData = JSON.parse(dataContainer.dataset.allocation); 
            let labels, data;

            if (allocationData.view_type === 'pending' && allocationData.settings.length > 0) {
                labels = allocationData.settings.map(s => s.allocation_name);
                data = allocationData.settings.map(s => (allocationData.net_profit * s.percentage) / 100);
            } else if (allocationData.view_type === 'summary' && allocationData.history.length > 0) {
                labels = allocationData.history.map(h => h.allocation_name);
                data = allocationData.history.map(h => parseFloat(h.allocated_amount));
            }

            if (labels && data) {
                const ctx = chartCanvas.getContext('2d');
                const backgroundColors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d', '#0dcaf0'];
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
                            legend: { position: 'top' },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) { label += ': '; }
                                        const value = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(context.parsed);
                                        label += value;
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