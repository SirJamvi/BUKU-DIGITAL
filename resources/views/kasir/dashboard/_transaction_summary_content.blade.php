{{-- resources/views/kasir/dashboard/_transaction_summary_content.blade.php --}}
@php
    $ts = $transactionSummary ?? [];
    $tsRows = $ts['rows'] ?? [];
    $tsDrawer = $ts['drawer'] ?? [];
    $tsNonDrawer = $ts['non_drawer'] ?? [];
    $tsDeposit = $ts['deposit'] ?? [];
    $tsSalesKasbon = $ts['sales_kasbon'] ?? [];
    $tsHasOther = !empty($ts['has_other_channel']);
    $summaryDateStr = $selectedDate ?? \Carbon\Carbon::today('Asia/Jakarta')->toDateString();

    $rowSale = $tsRows['sale_direct'] ?? ['cash' => 0, 'dana' => 0, 'transfer' => 0, 'other' => 0, 'deposit_used' => 0, 'total_money' => 0, 'total' => 0, 'count' => 0];
    $rowSettle = $tsRows['debt_settlement'] ?? ['cash' => 0, 'dana' => 0, 'transfer' => 0, 'other' => 0, 'deposit_used' => 0, 'total_money' => 0, 'total' => 0, 'count' => 0];
    $rowDeposit = $tsRows['customer_deposit'] ?? ['cash' => 0, 'dana' => 0, 'transfer' => 0, 'other' => 0, 'deposit_used' => 0, 'total_money' => 0, 'total' => 0, 'count' => 0];
    $rowGross = $tsRows['inflow_gross'] ?? ['cash' => 0, 'dana' => 0, 'transfer' => 0, 'other' => 0, 'deposit_used' => 0, 'total_money' => 0, 'total' => 0, 'count' => 0];
    $rowRefunds = $tsRows['refunds'] ?? ['cash' => 0, 'dana' => 0, 'transfer' => 0, 'other' => 0, 'deposit_used' => 0, 'total_money' => 0, 'total' => 0, 'count' => 0];
    $rowRefundVoid = $tsRows['refund_void'] ?? ['cash' => 0, 'dana' => 0, 'transfer' => 0, 'other' => 0, 'total_money' => 0, 'count' => 0];
    $rowRefundDeposit = $tsRows['refund_deposit'] ?? ['cash' => 0, 'dana' => 0, 'transfer' => 0, 'other' => 0, 'total_money' => 0, 'count' => 0];
    $rowNet = $tsRows['inflow_net'] ?? ['cash' => 0, 'dana' => 0, 'transfer' => 0, 'other' => 0, 'total_money' => 0, 'total' => 0];
    $tripsCountVal = $activeTripsCount ?? (isset($activeTrips) ? $activeTrips->count() : 0);
@endphp

{{-- TABEL RINCIAN TRANSAKSI LENGKAP --}}
<div class="kasir-card mb-4">
    <div class="kasir-section-header kasir-section-header--brand">
        <div>
            <h5 class="kasir-section-title mb-0">
                <i class="fas fa-table-list" style="color: var(--kasir-primary);"></i>
                <span>Rincian Uang Masuk &amp; Transaksi Lengkap</span>
                <i class="fas fa-circle-info text-muted info-tip" tabindex="0" role="button" data-bs-toggle="tooltip" title="Menampilkan semua uang yang diterima hari ini, dipisah antara belanja langsung, bayar kasbon lama, titipan deposit, bayar pakai saldo deposit, dan potongan nota batal/koreksi."></i>
            </h5>
            <p class="kasir-section-subtitle">
                Klik tombol kaca pembesar (<i class="fas fa-search"></i>) pada tiap baris untuk melihat daftar nota/catatannya. Arahkan/klik tanda <i class="fas fa-circle-info"></i> untuk melihat penjelasan.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="kasir-badge-success kasir-num" tabindex="0" data-bs-toggle="tooltip" title="Total uang nyata (Tunai + DANA + Transfer) yang benar-benar diterima setelah dikurangi nota batal &amp; koreksi deposit.">
                <i class="fas fa-check-circle"></i>
                <span>Uang Masuk Bersih: Rp {{ number_format($rowNet['total_money'] ?? 0, 0, ',', '.') }}</span>
            </span>
            <span class="kasir-badge-info kasir-num" tabindex="0" data-bs-toggle="tooltip" title="Total uang bersih dari DANA &amp; Transfer Bank yang langsung masuk ke rekening Bos (tidak masuk laci fisik kasir).">
                <i class="fas fa-building-columns"></i>
                <span>Non-Tunai Bersih: Rp {{ number_format($ts['non_cash_net_total'] ?? 0, 0, ',', '.') }}</span>
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table kasir-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Jenis Transaksi</th>
                        <th class="text-end text-nowrap">
                            <i class="fas fa-money-bill-wave text-success me-1"></i>Tunai (Laci)
                            <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Uang tunai (kertas/koin) yang langsung masuk ke laci kasir."></i>
                        </th>
                        <th class="text-end text-nowrap">
                            <i class="fas fa-mobile-alt text-info me-1"></i>DANA (Bos)
                            <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Pembayaran lewat DANA yang langsung masuk ke akun Bos (tidak masuk laci kasir)."></i>
                        </th>
                        <th class="text-end text-nowrap">
                            <i class="fas fa-university text-primary me-1"></i>Transfer Bank
                            <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Pembayaran transfer bank yang langsung masuk ke rekening Bos (tidak masuk laci kasir)."></i>
                        </th>
                        @if($tsHasOther)
                        <th class="text-end text-nowrap">Lainnya</th>
                        @endif
                        <th class="text-end text-nowrap">
                            <i class="fas fa-wallet me-1"></i>Pakai Deposit
                            <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Pembeli membayar pakai saldo titipan (deposit) yang sudah pernah disetor sebelumnya. Tidak menambah uang baru di laci/rekening hari ini."></i>
                        </th>
                        <th class="text-end text-nowrap">Jumlah Total</th>
                        <th class="text-center pe-3 d-print-none" style="width: 82px;">Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 1. Penjualan Langsung --}}
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold text-dark">
                                1. Pembelian Langsung Lunas
                                <i class="fas fa-circle-info text-muted info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Transaksi belanja baru hari ini di meja kasir yang langsung dibayar lunas."></i>
                            </div>
                            <small class="text-muted">Belanja baru di meja kasir yang langsung dibayar hari ini</small>
                        </td>
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowSale['cash'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowSale['dana'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowSale['transfer'] ?? 0, 0, ',', '.') }}</td>
                        @if($tsHasOther)
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowSale['other'] ?? 0, 0, ',', '.') }}</td>
                        @endif
                        <td class="text-end kasir-num fw-semibold text-nowrap" style="color: #0f766e;">Rp {{ number_format($rowSale['deposit_used'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-bold text-dark text-nowrap">Rp {{ number_format($rowSale['total'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-center pe-3 d-print-none">
                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2"
                                onclick="openKasirDrilldown('sale_direct', 'all', '1. Pembelian Langsung Lunas', '{{ $summaryDateStr }}')"
                                {{ ($rowSale['total'] ?? 0) <= 0 ? 'disabled' : '' }}>
                                <i class="fas fa-search"></i>
                            </button>
                        </td>
                    </tr>

                    {{-- 2. Pelunasan Kasbon & Pengiriman --}}
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold text-dark">
                                2. Bayar Kasbon Lama &amp; Setoran Driver
                                <i class="fas fa-circle-info text-muted info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Uang pembayaran dari kasbon hari sebelumnya atau setoran hasil antar driver. Tidak dihitung sebagai penjualan baru lagi supaya tidak dobel."></i>
                            </div>
                            <small class="text-muted">Pelunasan hutang nota lama atau hasil antar driver (bukan penjualan baru)</small>
                        </td>
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowSettle['cash'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowSettle['dana'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowSettle['transfer'] ?? 0, 0, ',', '.') }}</td>
                        @if($tsHasOther)
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowSettle['other'] ?? 0, 0, ',', '.') }}</td>
                        @endif
                        <td class="text-end kasir-num fw-semibold text-nowrap" style="color: #0f766e;">Rp {{ number_format($rowSettle['deposit_used'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-bold text-dark text-nowrap">Rp {{ number_format($rowSettle['total'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-center pe-3 d-print-none">
                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2"
                                onclick="openKasirDrilldown('debt_settlement', 'all', '2. Bayar Kasbon Lama &amp; Setoran Driver', '{{ $summaryDateStr }}')"
                                {{ ($rowSettle['total'] ?? 0) <= 0 ? 'disabled' : '' }}>
                                <i class="fas fa-search"></i>
                            </button>
                        </td>
                    </tr>

                    {{-- 3. Terima Deposit Pelanggan --}}
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold text-dark">
                                3. Terima Titipan Deposit Pelanggan
                                <i class="fas fa-circle-info text-muted info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Uang titipan (top-up saldo atau kelebihan bayar) dari pelanggan untuk dipakai belanja nanti. Ini uang titipan, bukan hasil penjualan barang."></i>
                            </div>
                            <small class="text-muted">Uang titipan / kelebihan bayar pelanggan (uang titipan, bukan penjualan)</small>
                        </td>
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowDeposit['cash'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowDeposit['dana'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowDeposit['transfer'] ?? 0, 0, ',', '.') }}</td>
                        @if($tsHasOther)
                        <td class="text-end kasir-num fw-semibold text-nowrap">Rp {{ number_format($rowDeposit['other'] ?? 0, 0, ',', '.') }}</td>
                        @endif
                        <td class="text-end text-muted">—</td>
                        <td class="text-end kasir-num fw-bold text-dark text-nowrap">Rp {{ number_format($rowDeposit['total_money'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-center pe-3 d-print-none">
                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2"
                                onclick="openKasirDrilldown('customer_deposit', 'all', '3. Terima Titipan Deposit Pelanggan', '{{ $summaryDateStr }}')"
                                {{ ($rowDeposit['total_money'] ?? 0) <= 0 ? 'disabled' : '' }}>
                                <i class="fas fa-search"></i>
                            </button>
                        </td>
                    </tr>

                    {{-- Subtotal Kotor --}}
                    <tr style="background: var(--kasir-surface-subtle);">
                        <td class="ps-3 fw-bold text-dark">
                            TOTAL UANG MASUK KOTOR (Baris 1 + 2 + 3)
                            <i class="fas fa-circle-info text-muted info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Gabungan semua uang masuk hari ini (Tunai + DANA + Transfer) sebelum dikurangi nota batal atau koreksi/pengembalian deposit."></i>
                        </td>
                        <td class="text-end kasir-num fw-bold text-nowrap" style="color: var(--kasir-success);">Rp {{ number_format($rowGross['cash'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-bold text-nowrap" style="color: var(--kasir-info);">Rp {{ number_format($rowGross['dana'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-bold text-nowrap" style="color: var(--kasir-primary);">Rp {{ number_format($rowGross['transfer'] ?? 0, 0, ',', '.') }}</td>
                        @if($tsHasOther)
                        <td class="text-end kasir-num fw-bold text-nowrap">Rp {{ number_format($rowGross['other'] ?? 0, 0, ',', '.') }}</td>
                        @endif
                        <td class="text-end kasir-num fw-bold text-nowrap" style="color: #0f766e;">Rp {{ number_format($rowGross['deposit_used'] ?? 0, 0, ',', '.') }}*</td>
                        <td class="text-end kasir-num fw-bold text-nowrap" style="color: var(--kasir-success);">Rp {{ number_format($rowGross['total_money'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-center pe-3 d-print-none">
                            <button type="button" class="btn btn-xs btn-outline-success py-0 px-2"
                                onclick="openKasirDrilldown('inflow_gross', 'all', 'Semua Uang Masuk Kotor', '{{ $summaryDateStr }}')"
                                {{ ($rowGross['total_money'] ?? 0) <= 0 ? 'disabled' : '' }}>
                                <i class="fas fa-list"></i>
                            </button>
                        </td>
                    </tr>

                    {{-- 4. Pengurang Nota Batal & Koreksi Deposit --}}
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold" style="color: var(--kasir-danger);">
                                4. Pengurang: Nota Batal &amp; Koreksi / Kembali Deposit
                                <i class="fas fa-circle-info text-muted info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Uang yang dibatalkan atau dikembalikan karena nota di-void atau ada koreksi salah input deposit oleh Admin."></i>
                            </div>
                            <small class="text-muted">
                                Nota Batal: Rp {{ number_format($rowRefundVoid['total_money'] ?? 0, 0, ',', '.') }} &middot;
                                Koreksi/Kembali Deposit: Rp {{ number_format($rowRefundDeposit['total_money'] ?? 0, 0, ',', '.') }}
                            </small>
                        </td>
                        <td class="text-end kasir-num fw-semibold text-nowrap" style="color: var(--kasir-danger);">
                            {{ ($rowRefunds['cash'] ?? 0) > 0 ? '-Rp ' . number_format($rowRefunds['cash'], 0, ',', '.') : 'Rp 0' }}
                        </td>
                        <td class="text-end kasir-num fw-semibold text-nowrap" style="color: var(--kasir-danger);">
                            {{ ($rowRefunds['dana'] ?? 0) > 0 ? '-Rp ' . number_format($rowRefunds['dana'], 0, ',', '.') : 'Rp 0' }}
                        </td>
                        <td class="text-end kasir-num fw-semibold text-nowrap" style="color: var(--kasir-danger);">
                            {{ ($rowRefunds['transfer'] ?? 0) > 0 ? '-Rp ' . number_format($rowRefunds['transfer'], 0, ',', '.') : 'Rp 0' }}
                        </td>
                        @if($tsHasOther)
                        <td class="text-end kasir-num fw-semibold text-nowrap" style="color: var(--kasir-danger);">
                            {{ ($rowRefunds['other'] ?? 0) > 0 ? '-Rp ' . number_format($rowRefunds['other'], 0, ',', '.') : 'Rp 0' }}
                        </td>
                        @endif
                        <td class="text-end text-muted">—</td>
                        <td class="text-end kasir-num fw-bold text-nowrap" style="color: var(--kasir-danger);">
                            {{ ($rowRefunds['total_money'] ?? 0) > 0 ? '-Rp ' . number_format($rowRefunds['total_money'], 0, ',', '.') : 'Rp 0' }}
                        </td>
                        <td class="text-center pe-3 d-print-none">
                            <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2"
                                onclick="openKasirDrilldown('refunds', 'all', '4. Nota Batal &amp; Koreksi / Kembali Deposit', '{{ $summaryDateStr }}')"
                                {{ ($rowRefunds['total_money'] ?? 0) <= 0 ? 'disabled' : '' }}>
                                <i class="fas fa-search"></i>
                            </button>
                        </td>
                    </tr>

                    {{-- Subtotal Bersih --}}
                    <tr style="background: var(--kasir-success-soft);">
                        <td class="ps-3 fw-bold text-dark">
                            TOTAL UANG MASUK BERSIH (Uang Nyata Diterima)
                            <i class="fas fa-circle-info text-muted info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Hasil akhir uang nyata yang benar-benar diterima hari ini (Uang Masuk Kotor dikurangi Nota Batal &amp; Koreksi Deposit)."></i>
                        </td>
                        <td class="text-end kasir-num fw-bold text-nowrap" style="color: var(--kasir-success);">Rp {{ number_format($rowNet['cash'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-bold text-dark text-nowrap">Rp {{ number_format($rowNet['dana'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end kasir-num fw-bold text-dark text-nowrap">Rp {{ number_format($rowNet['transfer'] ?? 0, 0, ',', '.') }}</td>
                        @if($tsHasOther)
                        <td class="text-end kasir-num fw-bold text-dark text-nowrap">Rp {{ number_format($rowNet['other'] ?? 0, 0, ',', '.') }}</td>
                        @endif
                        <td class="text-end text-muted">—</td>
                        <td class="text-end kasir-num fw-bold fs-6 text-nowrap" style="color: var(--kasir-success);">Rp {{ number_format($rowNet['total_money'] ?? 0, 0, ',', '.') }}</td>
                        <td class="text-center pe-3 d-print-none">—</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 border-top d-flex flex-wrap justify-content-between gap-2 small text-muted" style="background: var(--kasir-surface-subtle); font-size: 0.76rem;">
            <span>*<strong>Pakai Deposit (Rp {{ number_format($rowGross['deposit_used'] ?? 0, 0, ',', '.') }})</strong> adalah pembayaran memakai saldo titipan pelanggan sebelumnya (bukan uang tunai/bank baru yang masuk hari ini).</span>
            <span>Total DANA + Transfer (Sebelum Potongan): <strong class="text-dark kasir-num">Rp {{ number_format($ts['non_cash_gross_total'] ?? 0, 0, ',', '.') }}</strong></span>
        </div>
    </div>
</div>

{{-- PANEL 2 KOLOM: HITUNGAN UANG LACI + RINGKASAN KASBON, DEPOSIT & PENGIRIMAN --}}
<div class="row g-4">
    <div class="col-lg-6">
        <div class="kasir-card h-100 d-flex flex-column">
            <div class="kasir-section-header">
                <h5 class="kasir-section-title mb-0">
                    <i class="fas fa-cash-register" style="color: var(--kasir-danger);"></i>
                    <span>Uang Keluar &amp; Hitungan Isi Laci</span>
                    <i class="fas fa-circle-info text-muted info-tip" tabindex="0" role="button" data-bs-toggle="tooltip" title="Daftar semua uang yang keluar dari laci kasir hari ini supaya mudah mencocokkan uang tunai fisik di laci."></i>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="kasir-badge-danger kasir-num">Keluar Laci: -Rp {{ number_format($tsDrawer['out_total'] ?? 0, 0, ',', '.') }}</span>
                    @if(($tsDrawer['out_total'] ?? 0) > 0)
                    <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 d-print-none"
                        onclick="openKasirDrilldown('drawer_out', 'all', 'Rincian Uang Keluar dari Laci', '{{ $summaryDateStr }}')"
                        title="Lihat daftar catatan uang keluar dari laci">
                        <i class="fas fa-search"></i> Rincian
                    </button>
                    @endif
                </div>
            </div>
            <div class="card-body d-flex flex-column justify-content-between gap-3">
                <div class="small">
                    <div class="fw-bold text-uppercase text-muted mb-2" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                        A. Mengurangi Uang Tunai di Laci Kasir
                        <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Semua pengeluaran/koreksi ini langsung mengurangi jumlah uang tunai yang ada di dalam laci Anda."></i>
                    </div>
                    <div class="ps-3 border-start border-2 border-danger d-flex flex-column gap-2 mb-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Biaya Harian (BBM, Plastik, Makan, dll)</span>
                            <strong class="kasir-num text-nowrap" style="color: var(--kasir-danger);">-Rp {{ number_format($tsDrawer['out_operational'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Beli Stok / Es dari Luar (Tunai)</span>
                            <strong class="kasir-num text-nowrap" style="color: var(--kasir-danger);">-Rp {{ number_format($tsDrawer['out_stock_purchase'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">
                                Setor Uang Tunai ke Bos / Bank
                                <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Uang tunai dari laci yang sudah diserahkan ke Bos atau disetor ke bank."></i>
                            </span>
                            <strong class="kasir-num text-nowrap" style="color: var(--kasir-warning);">-Rp {{ number_format($tsDrawer['out_cash_handover'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Uang Kembali karena Nota Batal (Tunai)</span>
                            <strong class="kasir-num text-nowrap" style="color: var(--kasir-danger);">-Rp {{ number_format($tsDrawer['out_cash_refund'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Koreksi / Kembalikan Deposit (Tunai)</span>
                            <strong class="kasir-num text-nowrap" style="color: var(--kasir-danger);">-Rp {{ number_format($tsDrawer['out_deposit_refund'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-uppercase text-muted" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                            B. Bayar Pakai Rekening Bos (Tidak Potong Laci)
                            <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Dibayar langsung dari rekening/kantong Bos, jadi tidak mengurangi uang tunai di laci kasir."></i>
                        </span>
                        @if(($tsNonDrawer['total'] ?? 0) > 0)
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 d-print-none"
                            onclick="openKasirDrilldown('non_drawer_out', 'all', 'Rincian Pengeluaran Rekening Bos', '{{ $summaryDateStr }}')">
                            <i class="fas fa-search"></i>
                        </button>
                        @endif
                    </div>
                    <div class="ps-3 border-start border-2 d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Biaya Harian / Gaji dari Rekening Bos</span>
                            <span class="kasir-num text-muted text-nowrap">Rp {{ number_format($tsNonDrawer['operational_and_other'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Nota Batal Non-Tunai (DANA / Transfer)</span>
                            <span class="kasir-num text-muted text-nowrap">Rp {{ number_format($tsNonDrawer['cash_refund'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Koreksi / Kembali Deposit (DANA / Transfer)</span>
                            <span class="kasir-num text-muted text-nowrap">Rp {{ number_format($tsNonDrawer['deposit_refund'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: var(--kasir-success-soft); border: 1px solid var(--kasir-success-border);">
                    <div>
                        <div class="fw-bold text-dark small">
                            Sisa Uang Tunai Wajib di Laci Sekarang
                            <i class="fas fa-circle-info text-muted info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Hitung fisik uang kertas &amp; koin di laci Anda. Harusnya pas dengan angka hijau di kanan ini."></i>
                        </div>
                        <div class="text-muted" style="font-size: 0.74rem;">
                            Modal (Rp {{ number_format($myOpeningCash ?? 0, 0, ',', '.') }}) + Tunai Masuk (Rp {{ number_format($totalCashInToday ?? 0, 0, ',', '.') }}) &minus; Keluar Laci (Rp {{ number_format($tsDrawer['out_total'] ?? 0, 0, ',', '.') }})
                        </div>
                    </div>
                    <div class="fs-5 fw-bold kasir-num text-nowrap" style="color: var(--kasir-success);">
                        Rp {{ number_format($myNetCashToday ?? 0, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="kasir-card h-100 d-flex flex-column">
            <div class="kasir-section-header">
                <h5 class="kasir-section-title mb-0">
                    <i class="fas fa-piggy-bank" style="color: #0f766e;"></i>
                    <span>Ringkasan Kasbon, Deposit &amp; Antar Driver</span>
                </h5>
                <span class="kasir-badge-muted">Info Shift</span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between gap-3">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3 h-100" style="background: var(--kasir-surface-subtle); border: 1px solid var(--kasir-border);">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted">
                                    Kasbon Baru Hari Ini
                                    <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Belanja hari ini yang pembelinya belum bayar (hutang baru hari ini)."></i>
                                </span>
                                @if(($tsSalesKasbon['kasbon_new_today'] ?? 0) > 0)
                                <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1 d-print-none"
                                    onclick="openKasirDrilldown('kasbon_new', 'all', 'Daftar Kasbon Baru', '{{ $summaryDateStr }}')"
                                    title="Lihat daftar nota kasbon baru">
                                    <i class="fas fa-search"></i>
                                </button>
                                @endif
                            </div>
                            <div class="fs-6 fw-bold kasir-num mt-1" style="color: var(--kasir-danger);">
                                Rp {{ number_format($tsSalesKasbon['kasbon_new_today'] ?? 0, 0, ',', '.') }}
                            </div>
                            <div class="text-muted" style="font-size: 0.74rem;">
                                {{ number_format($tsSalesKasbon['kasbon_new_count'] ?? 0) }} nota belum dibayar
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3 h-100" style="background: var(--kasir-surface-subtle); border: 1px solid var(--kasir-border);">
                            <div class="small text-muted">
                                Kasbon Dibayar Hari Ini
                                <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Total hutang kasbon lama atau hasil antar driver yang dilunasi hari ini."></i>
                            </div>
                            <div class="fs-6 fw-bold kasir-num mt-1" style="color: var(--kasir-success);">
                                Rp {{ number_format($rowSettle['total'] ?? 0, 0, ',', '.') }}
                            </div>
                            <div class="text-muted" style="font-size: 0.74rem;">
                                Tunai Rp {{ number_format($rowSettle['cash'] ?? 0, 0, ',', '.') }} &middot; Non-Tunai Rp {{ number_format(($rowSettle['dana'] ?? 0) + ($rowSettle['transfer'] ?? 0) + ($rowSettle['other'] ?? 0), 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3 h-100" style="background: var(--kasir-surface-subtle); border: 1px solid var(--kasir-border);">
                            <div class="small text-muted">
                                Total Semua Kasbon Belum Dibayar
                                <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Akumulasi seluruh kasbon pelanggan dari hari-hari lalu sampai sekarang di semua cabang yang belum lunas."></i>
                            </div>
                            <div class="fs-6 fw-bold kasir-num text-dark mt-1">
                                Rp {{ number_format($totalKasbonAmount ?? 0, 0, ',', '.') }}
                            </div>
                            <div class="text-muted" style="font-size: 0.74rem;">
                                {{ number_format($totalKasbonCount ?? 0) }} struk lintas cabang
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3 h-100" style="background: var(--kasir-surface-subtle); border: 1px solid var(--kasir-border);">
                            <div class="small text-muted">
                                Pesanan Masih Dibawa Driver
                                <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Total nilai es yang sedang dalam perjalanan diantar driver dan belum diselesaikan pembayarannya."></i>
                            </div>
                            <div class="fs-6 fw-bold kasir-num mt-1" style="color: var(--kasir-primary);">
                                Rp {{ number_format($activeTripsTotalAmount ?? 0, 0, ',', '.') }}
                            </div>
                            <div class="text-muted" style="font-size: 0.74rem;">
                                {{ $tripsCountVal }} trip &middot; {{ number_format($activeTripsOrderCount ?? 0) }} pesanan di jalan
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded-3 small" style="background: #f0fdfa; border: 1px solid #99f6e4;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold" style="color: #0f766e;">
                            <i class="fas fa-wallet me-1"></i> Keluar-Masuk Titipan Deposit Pelanggan
                            <i class="fas fa-circle-info info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Ringkasan uang titipan (deposit) yang disetor, dikoreksi/dikembalikan, dan dipakai belanja hari ini."></i>
                        </span>
                        @if(($tsDeposit['used_total'] ?? 0) > 0)
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 d-print-none"
                            onclick="openKasirDrilldown('deposit_used', 'deposit', 'Rincian Belanja Pakai Saldo Deposit', '{{ $summaryDateStr }}')">
                            <i class="fas fa-search"></i> Pemakaian
                        </button>
                        @endif
                    </div>
                    <div class="d-flex flex-column gap-1" style="font-size: 0.8rem;">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-muted">Deposit Masuk (Tunai {{ number_format($tsDeposit['in_cash'] ?? 0, 0, ',', '.') }} &middot; DANA {{ number_format($tsDeposit['in_dana'] ?? 0, 0, ',', '.') }} &middot; TF {{ number_format($tsDeposit['in_transfer'] ?? 0, 0, ',', '.') }})</span>
                            <strong class="kasir-num text-nowrap" style="color: var(--kasir-success);">+Rp {{ number_format($tsDeposit['in_total'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-muted">Koreksi / Kembalikan Deposit</span>
                            <strong class="kasir-num text-nowrap" style="color: var(--kasir-danger);">-Rp {{ number_format($tsDeposit['refund_total'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-muted">Dipakai Pelanggan untuk Bayar Nota / Kasbon</span>
                            <strong class="kasir-num text-nowrap" style="color: #0f766e;">-Rp {{ number_format($tsDeposit['used_total'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-2 pt-2 mt-1 border-top fw-bold" style="border-color: #99f6e4 !important;">
                            <span class="text-dark">
                                Selisih Tambah/Kurang Deposit Hari Ini
                                <i class="fas fa-circle-info text-muted info-tip ms-1" tabindex="0" role="button" data-bs-toggle="tooltip" title="Jika plus (+), total titipan pelanggan bertambah hari ini. Jika minus (-), lebih banyak deposit yang dipakai belanja daripada yang disetor hari ini."></i>
                            </span>
                            @php $depNet = (float) ($tsDeposit['net_change'] ?? 0); @endphp
                            <span class="kasir-num text-nowrap {{ $depNet >= 0 ? 'text-primary' : 'text-danger' }}">
                                {{ $depNet >= 0 ? '+' : '-' }}Rp {{ number_format(abs($depNet), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
