{{-- Shared Modals & JS for Admin Customer Deposit Management --}}
{{-- 1. Modal Input Deposit Admin / Saldo Awal --}}
<div class="modal fade" id="modalAdminAddDeposit" tabindex="-1" aria-labelledby="modalAdminAddDepositLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.deposits.store') }}" method="POST" id="formAdminAddDeposit">
                @csrf
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);">
                    <h5 class="modal-title fw-bold" id="modalAdminAddDepositLabel">
                        <i class="fas fa-wallet me-2 text-warning"></i>Input Deposit / Saldo Awal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="admin_dep_customer_id" class="form-label fw-bold small">Pelanggan <span class="text-danger">*</span></label>
                        <select name="customer_id" id="admin_dep_customer_id" class="form-select" required>
                            <option value="" disabled {{ empty($defaultCustomerId) ? 'selected' : '' }}>-- Pilih Pelanggan --</option>
                            @foreach($allCustomers as $cust)
                                <option value="{{ $cust->id }}" {{ (!empty($defaultCustomerId) && (int) $defaultCustomerId === (int) $cust->id) ? 'selected' : '' }}>
                                    {{ $cust->name }} (Saldo: Rp {{ number_format((float) ($cust->deposit_balance ?? 0), 0, ',', '.') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-7">
                            <label for="admin_dep_mode" class="form-label fw-bold small">Mode Pembukuan <span class="text-danger">*</span></label>
                            <select name="deposit_mode" id="admin_dep_mode" class="form-select border-primary fw-semibold" required>
                                <option value="opening_balance" selected>Saldo Awal / Titipan Lama (Tanpa Laci Hari Ini)</option>
                                <option value="cash_owner">Cash Tunai (Pegang Owner / Non-Laci Kasir)</option>
                                <option value="cash_drawer">Cash Tunai (Masuk Laci Kasir Cabang)</option>
                                <option value="transfer-bank">Transfer Bank (Rekening Owner)</option>
                                <option value="dana">DANA (E-Wallet Owner)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="admin_dep_branch_id" class="form-label fw-bold small">Cabang</label>
                            <select name="branch_id" id="admin_dep_branch_id" class="form-select">
                                <option value="">-- Cabang Default --</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ (!empty($branchId) && (int) $branchId === (int) $b->id) ? 'selected' : '' }}>
                                        {{ $b->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-3" id="adminDepModeHint">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Saldo Awal / Titipan Lama:</strong> Menambah saldo deposit pelanggan tanpa membuat mutasi kas masuk hari ini (aman untuk mencatat titipan hari kemarin/catatan WA tanpa mengganggu kas laci shift kasir).
                    </div>

                    <div class="mb-3 d-none" id="adminDepCashierWrap">
                        <label for="admin_dep_cashier_id" class="form-label fw-bold small">Kasir Penanggung Jawab Laci <span class="text-danger">*</span></label>
                        <select name="cashier_id" id="admin_dep_cashier_id" class="form-select">
                            <option value="">-- Pilih Kasir --</option>
                            @foreach($cashiers as $kasirUser)
                                <option value="{{ $kasirUser->id }}">{{ $kasirUser->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-7">
                            <label for="admin_dep_amount" class="form-label fw-bold small">Nominal Deposit (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" name="amount" id="admin_dep_amount" class="form-control fw-bold" min="1" step="any" required placeholder="Contoh: 15000">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label for="admin_dep_date" class="form-label fw-bold small">Tanggal Buku</label>
                            <input type="date" name="date" id="admin_dep_date" class="form-control" value="{{ now('Asia/Jakarta')->toDateString() }}">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="admin_dep_notes" class="form-label fw-bold small">Catatan (Opsional)</label>
                        <textarea name="notes" id="admin_dep_notes" rows="2" class="form-control" maxlength="255" placeholder="Contoh: Migrasi catatan kasir WA kelebihan bayar minggu lalu..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fas fa-save me-1"></i>Simpan Deposit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 2. Modal Batalkan / Void Deposit (Contra-Entry FMCG) --}}
<div class="modal fade" id="modalReverseDeposit" tabindex="-1" aria-labelledby="modalReverseDepositLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="#" method="POST" id="formReverseDeposit">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold" id="modalReverseDepositLabel">
                        <i class="fas fa-ban me-2"></i>Batalkan / Void Setoran Deposit
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-danger py-2 px-3 small mb-3">
                        <i class="fas fa-shield-alt me-1"></i>
                        <strong>Pedoman Finance FMCG (Zero Hard-Delete):</strong> Data lama tidak dihapus permanen. Sistem akan membuat <strong>jurnal pembalik (contra-entry)</strong> agar jejak audit dan rekonsiliasi laci kasir tetap valid.
                    </div>

                    <div class="border rounded p-3 bg-light mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">ID Log &amp; Pelanggan:</span>
                            <strong id="revLogCustomer">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Nominal yang Dibatalkan:</span>
                            <strong class="text-danger" id="revAmount">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Saldo Deposit Pelanggan Saat Ini:</span>
                            <strong class="text-success" id="revCurrentBalance">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Dampak Kas / Laci:</span>
                            <span class="fw-semibold text-dark text-end" id="revCashflowInfo">-</span>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="rev_reason" class="form-label fw-bold small">Alasan Pembatalan <span class="text-danger">*</span></label>
                        <textarea name="reason" id="rev_reason" rows="2" class="form-control" required minlength="3" maxlength="255" placeholder="Contoh: Kasir salah input 2x atau salah pilih pelanggan..."></textarea>
                        <small class="text-muted">Pembatalan ditolak otomatis jika saldo deposit saat ini lebih kecil dari nominal setoran (sudah terpakai belanja/kasbon).</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-danger fw-bold">
                        <i class="fas fa-ban me-1"></i>Konfirmasi Void Deposit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 3. Modal Koreksi Nominal Langsung (1 Langkah Atomik) --}}
<div class="modal fade" id="modalCorrectDeposit" tabindex="-1" aria-labelledby="modalCorrectDepositLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="#" method="POST" id="formCorrectDeposit">
                @csrf
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="modalCorrectDepositLabel">
                        <i class="fas fa-pen-to-square me-2"></i>Koreksi Nominal Deposit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 px-3 small mb-3 text-dark">
                        <i class="fas fa-bolt me-1"></i>
                        Sistem akan otomatis mem-<strong>Void setoran salah</strong> lalu membuat <strong>setoran pengganti baru</strong> dalam 1 transaksi atomik, dengan tetap menjaga cabang, tanggal buku, dan laci kasir asalnya.
                    </div>

                    <div class="border rounded p-3 bg-light mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">ID Log &amp; Pelanggan:</span>
                            <strong id="corLogCustomer">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Nominal Lama (Salah):</span>
                            <strong class="text-danger" id="corOldAmount">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Atribusi Kas Asal:</span>
                            <span class="fw-semibold text-dark text-end" id="corCashflowInfo">-</span>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="cor_new_amount" class="form-label fw-bold small">Nominal Baru (Benar) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" name="new_amount" id="cor_new_amount" class="form-control fw-bold" min="1" step="any" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="cor_new_method" class="form-label fw-bold small">Metode Pembayaran <span class="text-danger">*</span></label>
                            <select name="new_payment_method" id="cor_new_method" class="form-select" required>
                                <option value="cash">Cash / Tunai</option>
                                <option value="transfer-bank">Transfer Bank</option>
                                <option value="dana">DANA</option>
                                <option value="opening_balance">Saldo Awal (Tanpa Laci)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="cor_reason" class="form-label fw-bold small">Alasan Koreksi <span class="text-danger">*</span></label>
                        <textarea name="reason" id="cor_reason" rows="2" class="form-control" required minlength="3" maxlength="255" placeholder="Contoh: Salah ketik angka 0 (harus Rp 15.000 bukan Rp 150.000)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold">
                        <i class="fas fa-check me-1"></i>Simpan Koreksi Deposit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modeSelect = document.getElementById('admin_dep_mode');
    const cashierWrap = document.getElementById('adminDepCashierWrap');
    const cashierSelect = document.getElementById('admin_dep_cashier_id');
    const modeHint = document.getElementById('adminDepModeHint');
    const custSelect = document.getElementById('admin_dep_customer_id');

    const modeHints = {
        opening_balance: '<strong>Saldo Awal / Titipan Lama:</strong> Menambah saldo deposit pelanggan tanpa membuat mutasi kas masuk hari ini (aman untuk mencatat titipan hari kemarin/catatan WA tanpa mengganggu kas laci shift kasir).',
        cash_owner: '<strong>Cash Tunai (Owner / Non-Laci):</strong> Mencatat uang tunai masuk ke kas bisnis (<code>affects_drawer = 0</code>) tanpa menambah saldo fisik laci kasir.',
        cash_drawer: '<strong>Cash Tunai (Laci Kasir):</strong> Menambah saldo deposit dan mencatat uang fisik masuk ke laci kasir terpilih (<code>affects_drawer = 1</code>).',
        'transfer-bank': '<strong>Transfer Bank:</strong> Menambah saldo deposit pelanggan dan mencatat arus kas masuk ke Rekening Owner (<code>affects_drawer = 0</code>).',
        dana: '<strong>DANA:</strong> Menambah saldo deposit pelanggan dan mencatat arus kas masuk ke E-Wallet Owner (<code>affects_drawer = 0</code>).'
    };

    function syncAdminDepositMode() {
        if (!modeSelect) return;
        const val = modeSelect.value;
        const isDrawer = (val === 'cash_drawer');
        if (cashierWrap) cashierWrap.classList.toggle('d-none', !isDrawer);
        if (cashierSelect) {
            cashierSelect.required = isDrawer;
            if (!isDrawer) cashierSelect.value = '';
        }
        if (modeHint && modeHints[val]) {
            modeHint.innerHTML = '<i class="fas fa-info-circle me-1"></i> ' + modeHints[val];
        }
    }

    if (modeSelect) {
        modeSelect.addEventListener('change', syncAdminDepositMode);
        syncAdminDepositMode();
    }

    document.addEventListener('click', function(e) {
        const quickAddBtn = e.target.closest('[data-prefill-customer-id]');
        if (quickAddBtn && custSelect) {
            custSelect.value = quickAddBtn.getAttribute('data-prefill-customer-id');
        }

        const revBtn = e.target.closest('.btn-reverse-deposit');
        if (revBtn) {
            const form = document.getElementById('formReverseDeposit');
            if (form) form.action = revBtn.getAttribute('data-reverse-url') || '#';
            const elCust = document.getElementById('revLogCustomer');
            const elAmt = document.getElementById('revAmount');
            const elBal = document.getElementById('revCurrentBalance');
            const elCf = document.getElementById('revCashflowInfo');
            const elReason = document.getElementById('rev_reason');
            if (elCust) elCust.textContent = '#' + (revBtn.getAttribute('data-log-id') || '') + ' · ' + (revBtn.getAttribute('data-customer-name') || '');
            if (elAmt) elAmt.textContent = revBtn.getAttribute('data-amount-formatted') || '-';
            if (elBal) elBal.textContent = 'Rp ' + (revBtn.getAttribute('data-current-balance') || '0');
            if (elCf) elCf.textContent = revBtn.getAttribute('data-cashflow-info') || '-';
            if (elReason) elReason.value = '';
        }

        const corBtn = e.target.closest('.btn-correct-deposit');
        if (corBtn) {
            const form = document.getElementById('formCorrectDeposit');
            if (form) form.action = corBtn.getAttribute('data-correct-url') || '#';
            const elCust = document.getElementById('corLogCustomer');
            const elOldAmt = document.getElementById('corOldAmount');
            const elCf = document.getElementById('corCashflowInfo');
            const elNewAmt = document.getElementById('cor_new_amount');
            const elMethod = document.getElementById('cor_new_method');
            const elReason = document.getElementById('cor_reason');
            if (elCust) elCust.textContent = '#' + (corBtn.getAttribute('data-log-id') || '') + ' · ' + (corBtn.getAttribute('data-customer-name') || '');
            if (elOldAmt) elOldAmt.textContent = corBtn.getAttribute('data-amount-formatted') || '-';
            if (elCf) elCf.textContent = corBtn.getAttribute('data-cashflow-info') || '-';
            if (elNewAmt) elNewAmt.value = corBtn.getAttribute('data-amount') || '';
            if (elMethod) {
                const rawMethod = (corBtn.getAttribute('data-method') || 'cash').toLowerCase();
                const hasOpt = Array.from(elMethod.options).some(opt => opt.value === rawMethod);
                elMethod.value = hasOpt ? rawMethod : 'cash';
            }
            if (elReason) elReason.value = '';
        }
    });
});
</script>
@endpush
