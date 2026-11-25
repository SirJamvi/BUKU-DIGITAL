@extends('admin.layouts.app')

@section('title', 'Tutup Buku Bulanan per Cabang')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tutup Buku Cabang</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    @include('components.alert')

    {{-- KONTROL UTAMA: FORM PROSES TUTUP BUKU --}}
    @if(app(\App\Support\BranchContext::class)->isConsolidated() || session('active_branch_id') === 'all')
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; border-top: 4px solid #10b981 !important;">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-calendar-check text-success me-2"></i> Proses Tutup Buku Konsolidasi (Pusat)
                </h5>
                <small class="text-muted">Proses ini akan menghitung HPP, omzet, dan beban seluruh cabang aktif, serta memperbarui alokasi laba bersih holding.</small>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.financial.process-monthly-closing') }}" method="POST" class="row g-3 align-items-end"
                      onsubmit="return confirm('Apakah Anda yakin ingin menjalankan proses tutup buku untuk periode ini? Seluruh cabang akan direkonsiliasi.');">
                    @csrf
                    <div class="col-md-4">
                        <label for="period" class="form-label fw-semibold">Pilih Periode Bulan:</label>
                        <select name="period" id="period" class="form-select" required>
                            @foreach($availablePeriods as $ap)
                                <option value="{{ $ap['period'] }}" {{ $loop->first ? 'selected' : '' }}>
                                    {{ $ap['label'] }} {{ $ap['is_closed'] ? '(Sudah Ditutup - Re-kalkulasi)' : '(Belum Ditutup)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-play-circle me-1"></i> Jalankan Tutup Buku
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="alert alert-warning d-flex align-items-center mb-4 py-3 px-3 shadow-sm" style="border-radius: 10px;">
            <i class="fas fa-lock fs-4 me-3 text-warning"></i>
            <div>
                <strong>Tutup buku dijalankan dari mode Pusat.</strong>
                <div class="small mt-1">Anda saat ini berada dalam mode Monitoring Cabang: <em>{{ session('active_branch_name') }}</em>. Untuk menjalankan atau merekonsiliasi tutup buku bulanan seluruh cabang, beralihlah ke mode Pusat (Konsolidasi) di bagian header.</div>
            </div>
        </div>
    @endif

    {{-- FILTER RIWAYAT PENUTUPAN BUKU --}}
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <div class="card-body py-3">
            <form action="{{ route('admin.financial.branch-closings') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <span class="fw-semibold text-muted small"><i class="fas fa-filter me-1"></i> Filter:</span>
                </div>
                @if(app(\App\Support\BranchContext::class)->isConsolidated() || session('active_branch_id') === 'all')
                    <div class="col-md-3">
                        <select name="branch_id" class="form-select form-select-sm">
                            <option value="">-- Semua Cabang --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>
                                    {{ $b->name }} ({{ $b->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-2">
                    <select name="month" class="form-select form-select-sm">
                        <option value="">-- Bulan --</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create(2026, $m, 1)->isoFormat('MMMM') }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="year" class="form-select form-select-sm">
                        <option value="">-- Tahun --</option>
                        @for($y = now()->year; $y >= 2024; $y--)
                            <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    @if(request()->hasAny(['branch_id', 'month', 'year']))
                        <a href="{{ route('admin.financial.branch-closings') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- TABEL RIWAYAT CLOSING PER CABANG --}}
    <x-card title="Riwayat Tutup Buku per Cabang">
        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th>Periode</th>
                        <th>Cabang</th>
                        <th class="text-center">Status Periode</th>
                        <th class="text-end">Omzet Penjualan</th>
                        <th class="text-end">Total HPP</th>
                        <th class="text-end">Laba Kotor</th>
                        <th class="text-end">Beban Ops Cabang</th>
                        <th class="text-end">Laba Bersih Cabang</th>
                        <th>Ditutup Oleh</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                @endslot

                <tbody>
                    @forelse($closings as $closing)
                        <tr>
                            <td class="fw-bold font-monospace">
                                {{ \Carbon\Carbon::create($closing->period_year, $closing->period_month, 1)->format('F Y') }}
                            </td>
                            <td>
                                <span class="fw-semibold">{{ $closing->branch->name ?? 'Cabang #' . $closing->branch_id }}</span>
                                <br>
                                <span class="badge bg-dark font-monospace" style="font-size: 0.7rem;">{{ $closing->branch->code ?? '-' }}</span>
                                @if(!empty($closing->branch->is_main))
                                    <span class="badge bg-primary ms-1" style="font-size: 0.65rem;">Pusat</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($closing->status === 'completed')
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1 px-2" style="font-size: 0.75rem;">
                                        <i class="fas fa-lock me-1"></i>🔒 Terkunci (Completed)
                                    </span>
                                @else
                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 py-1 px-2" style="font-size: 0.75rem;">
                                        <i class="fas fa-unlock me-1"></i>🔓 Terbuka (Draft)
                                    </span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold text-primary">
                                Rp {{ number_format($closing->monthly_income, 0, ',', '.') }}
                            </td>
                            <td class="text-end text-muted">
                                Rp {{ number_format($closing->monthly_cogs, 0, ',', '.') }}
                            </td>
                            <td class="text-end fw-semibold">
                                Rp {{ number_format($closing->gross_profit, 0, ',', '.') }}
                            </td>
                            <td class="text-end text-danger">
                                Rp {{ number_format($closing->monthly_expense, 0, ',', '.') }}
                            </td>
                            <td class="text-end fw-bold {{ $closing->net_profit >= 0 ? 'text-success' : 'text-danger' }}">
                                Rp {{ number_format($closing->net_profit, 0, ',', '.') }}
                            </td>
                            <td>
                                <div>{{ $closing->closedBy->name ?? 'Sistem (Auto)' }}</div>
                                <small class="text-muted">{{ $closing->closed_at ? $closing->closed_at->format('d/m/Y H:i') : '-' }}</small>
                            </td>
                            <td class="text-center">
                                @if($closing->status === 'completed')
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#modalReopenClosing_{{ $closing->id }}" title="Buka Kembali Tutup Buku Periode Ini">
                                        <i class="fas fa-unlock-alt me-1"></i>Buka Kembali
                                    </button>
                                @else
                                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">Sedang Terbuka</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="fas fa-file-invoice-dollar fa-2x mb-2 d-block"></i>
                                Belum ada riwayat tutup buku per cabang untuk filter yang dipilih.
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

    {{-- MODAL KONFIRMASI BUKA KEMBALI TUTUP BUKU --}}
    @foreach($closings as $closing)
    @if($closing->status === 'completed')
    <div class="modal fade" id="modalReopenClosing_{{ $closing->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning text-dark py-3">
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fas fa-unlock-alt me-2"></i> Buka Kembali Tutup Buku
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.financial.branch-closings.reopen', $closing->id) }}" method="POST">
                    @csrf
                    <div class="modal-body p-4 text-start">
                        <div class="alert alert-warning py-2 px-3 mb-3 small" style="font-size: 0.8rem;">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Anda akan membuka kembali pembukuan Cabang <strong>{{ $closing->branch->name ?? '#' . $closing->branch_id }}</strong> untuk periode <strong>{{ \Carbon\Carbon::create($closing->period_year, $closing->period_month, 1)->format('F Y') }}</strong>. 
                            Status periode akan diubah menjadi <strong>Draft</strong> sehingga penyesuaian data dapat dilakukan kembali.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">Alasan Pembukaan Kembali <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Masukkan alasan pembukaan kembali (minimal 5 karakter), contoh: Penyesuaian nota susulan..." required minlength="5"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning btn-sm fw-bold">
                            <i class="fas fa-unlock me-1"></i> Buka Kembali Pembukuan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
    @endforeach
</div>
@endsection

