{{-- resources/views/kasir/dashboard/shift_summary.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Rekap Shift & Rincian Transaksi')

@push('styles')
<style>
    .info-tip { cursor: pointer; font-size: 0.78em; opacity: 0.78; transition: opacity 120ms ease; }
    .info-tip:hover, .info-tip:focus { opacity: 1; outline: none; }
</style>
@endpush

@section('content')
@php
    $ts = $transactionSummary ?? [];
    $tsRows = $ts['rows'] ?? [];
    $tsSalesKasbon = $ts['sales_kasbon'] ?? [];
    $rowGross = $tsRows['inflow_gross'] ?? ['cash' => 0, 'dana' => 0, 'transfer' => 0, 'total_money' => 0];
    $rowNet = $tsRows['inflow_net'] ?? ['total_money' => 0];
@endphp
<div class="container-fluid px-0">
    {{-- Header + Filter Tanggal + Cetak --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1">
                <i class="fas fa-table-list me-2" style="color: var(--kasir-primary);"></i>Rekap Shift &amp; Rincian Transaksi
            </h3>
            <p class="text-muted mb-0">
                Kasir: <strong class="text-dark">{{ Auth::user()->name }}</strong> &middot;
                Tanggal: <strong class="text-dark">{{ $formattedDate }}</strong>
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 d-print-none">
            <form method="GET" action="{{ route('kasir.shift_summary') }}" class="d-flex align-items-center gap-2">
                <input type="date" name="date" value="{{ $selectedDate }}" class="form-control form-control-sm" style="width: auto;">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-filter me-1"></i>Tampilkan
                </button>
                @if(!$isToday)
                <a href="{{ route('kasir.shift_summary') }}" class="btn btn-sm btn-outline-secondary">Hari Ini</a>
                @endif
            </form>
            <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Cetak Rekap
            </button>
            <a href="{{ route('kasir.dashboard') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Dashboard
            </a>
        </div>
    </div>

    {{-- 4 KPI Ringkas Atas --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kasir-card p-3 h-100">
                <div class="small text-muted fw-semibold">
                    Uang Tunai di Laci
                    <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Jumlah uang tunai (kertas &amp; koin) yang wajib ada di dalam laci sekarang."></i>
                </div>
                <div class="fs-4 fw-bold kasir-num mt-1" style="color: var(--kasir-success);">
                    Rp {{ number_format($myNetCashToday ?? 0, 0, ',', '.') }}
                </div>
                <div class="small text-muted mt-1" style="font-size: 0.76rem;">
                    Modal Rp {{ number_format($myOpeningCash ?? 0, 0, ',', '.') }} &middot; Masuk +Rp {{ number_format($totalCashInToday ?? 0, 0, ',', '.') }} &middot; Keluar -Rp {{ number_format($myExpensesToday ?? 0, 0, ',', '.') }}
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kasir-card p-3 h-100">
                <div class="small text-muted fw-semibold">
                    Uang Masuk Bersih
                    <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Semua uang nyata yang benar-benar diterima (Tunai + DANA + Transfer) setelah dikurangi nota batal &amp; koreksi deposit."></i>
                </div>
                <div class="fs-4 fw-bold kasir-num mt-1 text-dark">
                    Rp {{ number_format($rowNet['total_money'] ?? 0, 0, ',', '.') }}
                </div>
                <div class="small text-muted mt-1" style="font-size: 0.76rem;">
                    Sebelum potongan Rp {{ number_format($rowGross['total_money'] ?? 0, 0, ',', '.') }} (Tunai {{ number_format($rowGross['cash'] ?? 0, 0, ',', '.') }} | DANA {{ number_format($rowGross['dana'] ?? 0, 0, ',', '.') }} | TF {{ number_format($rowGross['transfer'] ?? 0, 0, ',', '.') }})
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kasir-card p-3 h-100">
                <div class="small text-muted fw-semibold">
                    Total Penjualan
                    <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Total nilai barang terjual pada tanggal ini (yang sudah lunas + kasbon baru)."></i>
                </div>
                <div class="fs-4 fw-bold kasir-num mt-1" style="color: var(--kasir-primary);">
                    Rp {{ number_format($tsSalesKasbon['omset_today'] ?? 0, 0, ',', '.') }}
                </div>
                <div class="small text-muted mt-1" style="font-size: 0.76rem;">
                    Lunas Rp {{ number_format($tsSalesKasbon['omset_paid_today'] ?? 0, 0, ',', '.') }} &middot; Kasbon Baru Rp {{ number_format($tsSalesKasbon['kasbon_new_today'] ?? 0, 0, ',', '.') }}
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kasir-card p-3 h-100">
                <div class="small text-muted fw-semibold">
                    Uang Non-Tunai Bersih (DANA / TF)
                    <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Uang bersih yang masuk ke rekening Bos via DANA &amp; Transfer Bank (tidak masuk laci fisik)."></i>
                </div>
                <div class="fs-4 fw-bold kasir-num mt-1" style="color: #0f766e;">
                    Rp {{ number_format($ts['non_cash_net_total'] ?? 0, 0, ',', '.') }}
                </div>
                <div class="small text-muted mt-1" style="font-size: 0.76rem;">
                    Deposit Bersih: Rp {{ number_format(($ts['deposit']['in_total'] ?? 0) - ($ts['deposit']['refund_total'] ?? 0), 0, ',', '.') }} &middot; Dipakai Belanja: Rp {{ number_format($ts['deposit']['used_total'] ?? 0, 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Konten Matriks Full + Panel 2 Kolom --}}
    @include('kasir.dashboard._transaction_summary_content')
</div>

{{-- Modal AJAX Drilldown Rincian Item --}}
<div class="modal fade" id="modalKasirDrilldown" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header text-white" style="background: var(--kasir-gradient);">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="kasirDrilldownTitle">Rincian Transaksi</h5>
                    <small class="opacity-90" id="kasirDrilldownSub">Memuat data...</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="kasirDrilldownLoading" class="text-center py-4">
                    <div class="spinner-border text-primary spinner-border-sm me-2"></div>
                    <span class="small text-muted">Mengambil daftar rincian transaksi...</span>
                </div>
                <div id="kasirDrilldownContent" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.82rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Jam</th>
                                    <th>Kode / Jenis</th>
                                    <th>Pelanggan</th>
                                    <th>Metode</th>
                                    <th>Keterangan</th>
                                    <th class="text-end pe-3">Nominal</th>
                                </tr>
                            </thead>
                            <tbody id="kasirDrilldownTbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div class="small fw-bold" id="kasirDrilldownFooterTotal"></div>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.bootstrap && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            bootstrap.Tooltip.getOrCreateInstance(el, { trigger: 'hover focus click', container: 'body' });
        });
        document.addEventListener('click', function(ev) {
            if (!ev.target.closest('[data-bs-toggle="tooltip"]')) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                    const inst = bootstrap.Tooltip.getInstance(el);
                    if (inst) inst.hide();
                });
            }
        });
    }
});

window.openKasirDrilldown = async function(category, channel, title, dateStr) {
    const modalEl = document.getElementById('modalKasirDrilldown');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.getElementById('kasirDrilldownTitle').textContent = title;
    document.getElementById('kasirDrilldownSub').textContent = 'Tanggal: ' + (dateStr || '{{ $selectedDate }}');
    document.getElementById('kasirDrilldownLoading').classList.remove('d-none');
    document.getElementById('kasirDrilldownContent').classList.add('d-none');
    document.getElementById('kasirDrilldownFooterTotal').textContent = '';
    modal.show();

    try {
        const params = new URLSearchParams({ category: category, channel: channel || 'all', date: dateStr || '{{ $selectedDate }}' });
        const resp = await fetch('{{ route('kasir.dashboard.drilldown') }}?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const res = await resp.json();
        const tbody = document.getElementById('kasirDrilldownTbody');
        if (!res.items || res.items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada rincian item pada kategori ini.</td></tr>';
            document.getElementById('kasirDrilldownFooterTotal').textContent = '0 item';
        } else {
            const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
            tbody.innerHTML = res.items.map(it => `
                <tr>
                    <td class="ps-3 text-nowrap text-muted">${esc(it.time || '-')}</td>
                    <td>
                        <div class="fw-bold text-dark">${esc(it.code || '-')}</div>
                        <small class="text-muted">${esc(it.type_label || '-')}</small>
                    </td>
                    <td>${esc(it.customer_name || '-')}</td>
                    <td><span class="badge bg-light text-dark border">${esc(it.payment_method || '-')}</span></td>
                    <td class="small text-muted">${esc(it.description || '-')}</td>
                    <td class="text-end pe-3 fw-bold text-nowrap ${it.sign === '-' ? 'text-danger' : 'text-success'}">${esc(it.sign || '')}Rp ${fmt(it.amount)}</td>
                </tr>
            `).join('');
            document.getElementById('kasirDrilldownFooterTotal').textContent = res.count + ' item • Total: Rp ' + fmt(res.total);
        }
    } catch (e) {
        document.getElementById('kasirDrilldownTbody').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Gagal memuat rincian transaksi.</td></tr>';
    } finally {
        document.getElementById('kasirDrilldownLoading').classList.add('d-none');
        document.getElementById('kasirDrilldownContent').classList.remove('d-none');
    }
};
</script>
@endpush
