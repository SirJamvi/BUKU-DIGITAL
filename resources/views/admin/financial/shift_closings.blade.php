@extends('admin.layouts.app')

@section('title', 'Rekap Tutup Shift Kasir')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Rekap Tutup Shift</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    @include('components.alert')

    {{-- 4 KPI SUMMARY CARDS --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: Total Shift Ditutup --}}
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #3b82f6 !important; border-radius: 10px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Total Shift Ditutup</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-circle"><i class="fas fa-clock-rotate-left"></i></span>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">{{ number_format($kpi['total_closings'] ?? 0) }}</h3>
                    <p class="card-text text-muted small mb-0">Total sesi shift kasir tersimpan.</p>
                </div>
            </div>
        </div>

        {{-- Card 2: Total Setoran Handover --}}
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #10b981 !important; border-radius: 10px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Total Setoran ke Owner</span>
                        <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-circle"><i class="fas fa-hand-holding-dollar"></i></span>
                    </div>
                    <h3 class="fw-bold text-success mb-1">Rp {{ number_format($kpi['total_handover'] ?? 0, 0, ',', '.') }}</h3>
                    <p class="card-text text-muted small mb-0">Uang fisik diserahkan ke Owner/Bank.</p>
                </div>
            </div>
        </div>

        {{-- Card 3: Total Selisih Kurang (Short) --}}
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #ef4444 !important; border-radius: 10px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Total Selisih Kurang (Short)</span>
                        <span class="badge bg-danger bg-opacity-10 text-danger p-2 rounded-circle"><i class="fas fa-arrow-trend-down"></i></span>
                    </div>
                    <h3 class="fw-bold text-danger mb-1">- Rp {{ number_format($kpi['total_short'] ?? 0, 0, ',', '.') }}</h3>
                    <p class="card-text text-muted small mb-0">Akumulasi uang fisik laci tekor.</p>
                </div>
            </div>
        </div>

        {{-- Card 4: Total Selisih Lebih (Over) --}}
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #06b6d4 !important; border-radius: 10px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Total Selisih Lebih (Over)</span>
                        <span class="badge bg-info bg-opacity-10 text-info p-2 rounded-circle"><i class="fas fa-arrow-trend-up"></i></span>
                    </div>
                    <h3 class="fw-bold text-info mb-1">+ Rp {{ number_format($kpi['total_over'] ?? 0, 0, ',', '.') }}</h3>
                    <p class="card-text text-muted small mb-0">Akumulasi uang fisik laci lebih.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTER FORM --}}
    <x-card title="Filter Rekap Tutup Shift" class="mb-4">
        <form method="GET" action="{{ route('admin.financial.shift-closings.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <x-input type="date" name="start_date" label="Dari Tanggal" :value="request('start_date')" />
                </div>
                <div class="col-md-2 col-sm-6">
                    <x-input type="date" name="end_date" label="Sampai Tanggal" :value="request('end_date')" />
                </div>
                @if(app(\App\Support\BranchContext::class)->isConsolidated() || session('active_branch_id') === 'all')
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold">Cabang</label>
                    <select name="branch_id" class="form-select form-select-sm">
                        <option value="">-- Semua Cabang --</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold">Kasir</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">-- Semua Kasir --</option>
                        @foreach($cashiers as $usr)
                            <option value="{{ $usr->id }}" {{ request('user_id') == $usr->id ? 'selected' : '' }}>
                                {{ $usr->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold">Status Selisih</label>
                    <select name="variance_status" class="form-select form-select-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="balanced" {{ request('variance_status') === 'balanced' ? 'selected' : '' }}>⚖️ Pas (Nol Selisih)</option>
                        <option value="over" {{ request('variance_status') === 'over' ? 'selected' : '' }}>📈 Lebih (Over)</option>
                        <option value="short" {{ request('variance_status') === 'short' ? 'selected' : '' }}>📉 Kurang (Short)</option>
                    </select>
                </div>
                <div class="col-md-2 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.financial.shift-closings.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </div>
        </form>
    </x-card>

    {{-- TABEL DATA TUTUP SHIFT --}}
    <x-card title="Riwayat Tutup Shift Kasir">
        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th>Tgl & Shift</th>
                        <th>Cabang & Kasir</th>
                        <th class="text-end">Modal Awal</th>
                        <th class="text-end">Masuk Laci</th>
                        <th class="text-end">Keluar Laci</th>
                        <th class="text-end">Setor Owner</th>
                        <th class="text-end">Ekspektasi</th>
                        <th class="text-end">Fisik Hitung</th>
                        <th class="text-center">Variance (Selisih)</th>
                        <th class="text-end">Modal Besok</th>
                        <th class="text-center">Sumber</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                @endslot

                <tbody>
                    @forelse($closings as $sc)
                        @php
                            $totalMasuk = (float)$sc->cash_sales + (float)$sc->cash_debt_collection;
                            $totalKeluar = (float)$sc->cash_expenses + (float)$sc->cash_refunds;
                            $var = (float)$sc->variance;
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $sc->date ? $sc->date->format('d/m/Y') : '-' }}</span>
                                <br><span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;">{{ $sc->shift_code ?: 'Shift' }}</span>
                            </td>
                            <td>
                                <strong class="text-dark">{{ $sc->user->name ?? 'Kasir #' . $sc->user_id }}</strong>
                                <br><span class="badge bg-light text-muted border" style="font-size: 0.7rem;">
                                    <i class="fas fa-store me-1"></i>{{ $sc->branch->name ?? 'Cabang #' . $sc->branch_id }}
                                </span>
                            </td>
                            <td class="text-end text-muted">
                                Rp {{ number_format($sc->opening_cash, 0, ',', '.') }}
                            </td>
                            <td class="text-end text-success fw-semibold">
                                + Rp {{ number_format($totalMasuk, 0, ',', '.') }}
                            </td>
                            <td class="text-end text-danger fw-semibold">
                                - Rp {{ number_format($totalKeluar, 0, ',', '.') }}
                            </td>
                            <td class="text-end fw-semibold text-primary">
                                Rp {{ number_format($sc->cash_handover_amount, 0, ',', '.') }}
                                @if($sc->handover_receiver)
                                <br><small class="text-muted" style="font-size: 0.68rem;">Penerima: {{ $sc->handover_receiver }}</small>
                                @endif
                            </td>
                            <td class="text-end fw-bold">
                                Rp {{ number_format($sc->expected_drawer_cash, 0, ',', '.') }}
                            </td>
                            <td class="text-end fw-bold text-dark">
                                Rp {{ number_format($sc->actual_cash_counted, 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                @if($var == 0)
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 0.75rem;">
                                        <i class="fas fa-check-circle me-1"></i>PAS (Rp 0)
                                    </span>
                                @elseif($var > 0)
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1" style="font-size: 0.75rem;">
                                        <i class="fas fa-arrow-up me-1"></i>LEBIH (+Rp {{ number_format($var, 0, ',', '.') }})
                                    </span>
                                @else
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1" style="font-size: 0.75rem;">
                                        <i class="fas fa-arrow-down me-1"></i>KURANG (-Rp {{ number_format(abs($var), 0, ',', '.') }})
                                    </span>
                                @endif
                            </td>
                            <td class="text-end text-muted">
                                Rp {{ number_format($sc->ending_cash_for_next_shift, 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                @if(!empty($sc->external_reference))
                                    <span class="badge bg-purple bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size: 0.7rem;" title="ID Referensi: {{ $sc->external_reference }}">
                                        <i class="fas fa-sync-alt me-1"></i>Sync ACS
                                    </span>
                                @else
                                    <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                        Manual POS
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#modalShiftDetail_{{ $sc->id }}" title="Lihat Rincian Shift">
                                    <i class="fas fa-eye"></i> Detail
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="fas fa-clock-rotate-left fa-3x mb-3 text-secondary opacity-50"></i>
                                <h6 class="fw-bold text-secondary">Belum ada riwayat tutup shift kasir</h6>
                                <p class="small mb-0">Tidak ada data tutup shift yang cocok dengan kriteria filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-table>
        </div>

        @if($closings->hasPages())
            <div class="mt-3">
                {{ $closings->links('components.pagination') }}
            </div>
        @endif
    </x-card>

    {{-- MODAL DETAIL PER SHIFT CLOSING --}}
    @foreach($closings as $sc)
    <div class="modal fade" id="modalShiftDetail_{{ $sc->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fas fa-receipt text-warning me-2"></i> Rincian Tutup Shift #{{ $sc->id }} ({{ $sc->shift_code ?: 'Shift' }})
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Kasir / Petugas:</span>
                            <strong class="text-dark">{{ $sc->user->name ?? '-' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Cabang:</span>
                            <strong>{{ $sc->branch->name ?? '-' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Tanggal:</span>
                            <span>{{ $sc->date ? $sc->date->format('d/m/Y') : '-' }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Waktu Tutup Shift:</span>
                            <span>{{ $sc->closed_at ? $sc->closed_at->format('d/m/Y H:i') : '-' }} WIB</span>
                        </div>
                        @if($sc->closedBy)
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Ditutup Oleh:</span>
                            <span>{{ $sc->closedBy->name }}</span>
                        </div>
                        @endif
                    </div>

                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Rekonsiliasi Kas Laci (SSOT):</h6>
                    <div class="d-flex justify-content-between py-1 border-bottom small">
                        <span>Modal Awal Kasir:</span>
                        <span class="fw-semibold">Rp {{ number_format($sc->opening_cash, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom small text-success">
                        <span>+ Penjualan Tunai Meja POS:</span>
                        <span class="fw-semibold">+ Rp {{ number_format($sc->cash_sales, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom small text-success">
                        <span>+ Pelunasan Kasbon & Setoran Masuk:</span>
                        <span class="fw-semibold">+ Rp {{ number_format($sc->cash_debt_collection, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom small text-danger">
                        <span>- Pengeluaran dari Laci Kasir:</span>
                        <span class="fw-semibold">- Rp {{ number_format($sc->cash_expenses, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom small text-danger">
                        <span>- Refund Kas / Void Nota:</span>
                        <span class="fw-semibold">- Rp {{ number_format($sc->cash_refunds, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom fw-bold bg-light px-2 rounded mt-1">
                        <span>= Saldo Ekspektasi Kas Laci:</span>
                        <span class="text-dark">Rp {{ number_format($sc->expected_drawer_cash, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom fw-bold mt-1 px-2">
                        <span>Fisik Kas Dihitung Nyata:</span>
                        <span class="text-primary">Rp {{ number_format($sc->actual_cash_counted, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom fw-bold px-2">
                        <span>Variance (Selisih Hitung):</span>
                        <span class="{{ $sc->variance >= 0 ? ($sc->variance == 0 ? 'text-success' : 'text-info') : 'text-danger' }}">
                            {{ $sc->variance >= 0 ? ($sc->variance == 0 ? 'PAS' : '+') : '' }}Rp {{ number_format($sc->variance, 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom small mt-2">
                        <span>Setoran Fisik ke Owner:</span>
                        <span class="fw-semibold text-warning">Rp {{ number_format($sc->cash_handover_amount, 0, ',', '.') }}</span>
                    </div>
                    @if($sc->handover_receiver)
                    <div class="d-flex justify-content-between py-1 border-bottom small text-muted">
                        <span>Penerima Setoran:</span>
                        <span>{{ $sc->handover_receiver }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between py-1 border-bottom small">
                        <span>Sisa Modal untuk Shift Berikutnya:</span>
                        <span class="fw-semibold text-dark">Rp {{ number_format($sc->ending_cash_for_next_shift, 0, ',', '.') }}</span>
                    </div>

                    @if(!empty($sc->notes))
                    <div class="mt-3 p-3 bg-light rounded border">
                        <span class="fw-bold small text-muted d-block mb-1">Catatan Shift:</span>
                        <div class="small text-dark">{{ $sc->notes }}</div>
                    </div>
                    @endif
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
