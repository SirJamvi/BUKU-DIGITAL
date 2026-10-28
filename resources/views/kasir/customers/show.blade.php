{{-- resources/views/kasir/customers/show.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Detail Pelanggan')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('kasir.customers.index') }}">Pelanggan</a></li>
<li class="breadcrumb-item active" aria-current="page">Detail</li>
@endsection

@section('content')

{{-- Menampilkan pesan sukses atau error jika halaman reload --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="row">
    <div class="col-md-4">
        <x-card title="Informasi Pelanggan">
            <div class="text-center mb-3">
                <i class="fas fa-user-circle fa-5x" style="color: var(--kasir-accent);"></i>
                <h4 class="mt-3">{{ $customer->name }}</h4>
                <span class="text-muted">Bergabung sejak {{ \Carbon\Carbon::parse($customer->join_date)->isoFormat('D MMM YYYY') }}</span>
            </div>
            <hr>
            <ul class="list-unstyled">
                <li class="mb-2"><i class="fas fa-phone-alt fa-fw me-2"></i> {{ $customer->phone ?? '-' }}</li>
                <li class="mb-2"><i class="fas fa-envelope fa-fw me-2"></i> {{ $customer->email ?? '-' }}</li>
                <li class="mb-2"><i class="fas fa-map-marker-alt fa-fw me-2"></i> {{ $customer->address ?? '-' }}</li>
            </ul>
        </x-card>
    </div>

    <div class="col-md-8">
        <x-card title="Riwayat Transaksi Pelanggan">
            <div class="table-responsive">
                <x-table>
                    @slot('thead')
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th class="text-end">Total Belanja</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                    @endslot

                    @forelse ($transactions as $transaction)
                    <tr>
                        <td>#{{ $transaction->id }}</td>
                        <td>{{ $transaction->transaction_date->isoFormat('D MMM YYYY, HH:mm') }}</td>
                        <td class="text-end">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if($transaction->payment_status === 'pending')
                            <span class="badge bg-danger mb-2 d-block">Belum Lunas</span>
                            {{-- Tombol Trigger Modal (Modalnya dipindah ke bawah) --}}
                            <button type="button" class="btn btn-sm btn-success w-100" data-bs-toggle="modal" data-bs-target="#payModal{{ $transaction->id }}">
                                <i class="fas fa-check-circle"></i> Approve Lunas
                            </button>
                            @else
                            <span class="badge bg-success mb-2 d-block">Lunas</span>
                            <x-button href="{{ route('kasir.transactions.show', $transaction->id) }}" variant="info" class="btn-sm w-100">
                                <i class="fas fa-receipt"></i> Struk
                            </x-button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center">Pelanggan ini belum memiliki riwayat transaksi.</td>
                    </tr>
                    @endforelse
                </x-table>
            </div>

            @if ($transactions->hasPages())
            <div class="mt-3">
                {{ $transactions->links('components.pagination') }}
            </div>
            @endif
        </x-card>
    </div>
</div>

{{-- ============================================================= --}}
{{-- MODAL AREA (Wajib diletakkan di luar struktur Table / Card)   --}}
{{-- ============================================================= --}}
@foreach ($transactions as $transaction)
@if($transaction->payment_status === 'pending')
<div class="modal fade" id="payModal{{ $transaction->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('kasir.transactions.markAsPaid', $transaction->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-content text-start">
                <div class="modal-header">
                    <h5 class="modal-title">Pelunasan Transaksi #{{ $transaction->id }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Total Tagihan: <strong>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Metode Pembayaran</label>
                        <select name="payment_method" id="pm_cust_{{ $transaction->id }}" class="form-select" onchange="checkSettlementModeCust_{{ $transaction->id }}()" required>
                            <option value="cash">💵 Cash / Tunai</option>
                            <option value="dana">📱 Dana</option>
                            <option value="transfer-bank">🏦 Transfer Bank</option>
                        </select>
                    </div>

                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label fw-bold text-dark small mb-2">Alokasi Tanggal Pembukuan</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_today_{{ $transaction->id }}" value="today" checked onchange="checkSettlementModeCust_{{ $transaction->id }}()">
                            <label class="form-check-label" for="settle_today_{{ $transaction->id }}">
                                <strong>Uang Baru Diterima Hari Ini (Masuk Laci / Kas Hari Ini)</strong>
                                <br><small class="text-muted">Gunakan jika pelanggan/driver baru menyerahkan uangnya hari ini (Cash, DANA, Transfer).</small>
                            </label>
                        </div>
                        <div class="form-check" id="container_cust_nota_{{ $transaction->id }}">
                            <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_nota_{{ $transaction->id }}" value="nota_date" onchange="checkSettlementModeCust_{{ $transaction->id }}()">
                            <label class="form-check-label" for="settle_nota_{{ $transaction->id }}">
                                <strong>Koreksi Lupa Update (Uang Sudah Diterima Saat Tanggal Nota: {{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y') : '' }})</strong>
                                <br><small class="text-muted">Hanya gunakan jika sebenarnya sudah bayar saat nota dibuat namun kasir lupa mengubah status.</small>
                            </label>
                        </div>
                        <div id="warning_nota_cust_{{ $transaction->id }}" class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none" style="font-size: 0.78rem;">
                            <i class="fas fa-exclamation-triangle me-1"></i> <strong>Perhatian:</strong> Memilih opsi ini tidak akan menambah catatan Saldo Laci hari ini karena uang dianggap sudah masuk pada tanggal nota dibuat.
                        </div>
                    </div>

                    @if(in_array(auth()->user()->role ?? '', ['admin', 'owner']))
                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label fw-bold text-dark small mb-2"><i class="fas fa-user-shield text-primary me-1"></i> Alokasi Fisik Kas Tunai (Role Admin):</label>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="cash_destination" id="dest_cust_drawer_{{ $transaction->id }}" value="drawer" checked>
                            <label class="form-check-label small" for="dest_cust_drawer_{{ $transaction->id }}">
                                <strong>Masuk ke Laci Kasir Pembuat Nota</strong> ({{ optional($transaction->createdBy)->name ?? 'Kasir' }})
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="cash_destination" id="dest_cust_owner_{{ $transaction->id }}" value="owner">
                            <label class="form-check-label small" for="dest_cust_owner_{{ $transaction->id }}">
                                <strong>Dipegang Langsung oleh Admin / Owner</strong> (Tidak masuk laci fisik kasir)
                            </label>
                        </div>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark d-flex justify-content-between align-items-center">
                            <span><i class="fab fa-whatsapp text-success me-1"></i> Bukti Pembayaran / Transfer Non-Tunai (WhatsApp):</span>
                            <span id="proof_badge_cust_{{ $transaction->id }}"><span class="badge bg-light text-muted border">Opsional (Cash)</span></span>
                        </label>
                        <input type="file" name="payment_proof" id="proof_cust_{{ $transaction->id }}" class="form-control form-control-sm" accept="image/*">
                        <div id="proof_help_cust_{{ $transaction->id }}" class="text-muted" style="font-size: 0.72rem;">*Opsional untuk Cash. Ambil dari galeri tablet jika bayar transfer/DANA.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Catatan Pelunasan (Opsional)</label>
                        <input type="text" name="settlement_notes" class="form-control form-control-sm" placeholder="Misal: Bukti transfer terlampir">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-check me-1"></i> Validasi Pelunasan</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
function checkSettlementModeCust_{{ $transaction->id }}() {
    const method = document.getElementById('pm_cust_{{ $transaction->id }}')?.value;
    const isCash = (method === 'cash');
    const optToday = document.getElementById('settle_today_{{ $transaction->id }}');
    const optNota = document.getElementById('settle_nota_{{ $transaction->id }}');
    const containerNota = document.getElementById('container_cust_nota_{{ $transaction->id }}');
    const warn = document.getElementById('warning_nota_cust_{{ $transaction->id }}');
    const proofInput = document.getElementById('proof_cust_{{ $transaction->id }}');
    const proofBadge = document.getElementById('proof_badge_cust_{{ $transaction->id }}');
    const proofHelp = document.getElementById('proof_help_cust_{{ $transaction->id }}');

    if (isCash) {
        if (optNota) {
            optNota.disabled = true;
            optNota.checked = false;
        }
        if (optToday) {
            optToday.checked = true;
        }
        if (containerNota) {
            containerNota.classList.add('d-none');
        }
        if (warn) warn.classList.add('d-none');

        if (proofInput) {
            proofInput.removeAttribute('required');
            if (proofBadge) proofBadge.innerHTML = '<span class="badge bg-light text-muted border">Opsional (Cash)</span>';
            if (proofHelp) proofHelp.innerText = '*Opsional jika pelunasan secara Cash / Tunai.';
        }
    } else {
        if (optNota) {
            optNota.disabled = false;
        }
        if (containerNota) {
            containerNota.classList.remove('d-none');
        }
        if (warn) {
            if (optNota && optNota.checked) {
                warn.classList.remove('d-none');
            } else {
                warn.classList.add('d-none');
            }
        }

        if (proofInput) {
            proofInput.setAttribute('required', 'required');
            if (proofBadge) proofBadge.innerHTML = '<span class="badge bg-danger text-white"><i class="fas fa-asterisk me-1"></i>Wajib Upload Bukti WA</span>';
            if (proofHelp) proofHelp.innerHTML = '<strong class="text-danger">*Wajib diunggah!</strong> Lampirkan screenshot bukti transfer WhatsApp pelanggan.';
        }
    }
}
document.addEventListener('DOMContentLoaded', function() {
    checkSettlementModeCust_{{ $transaction->id }}();
});
</script>
@endif
@endforeach
{{-- ============================================================= --}}

@endsection