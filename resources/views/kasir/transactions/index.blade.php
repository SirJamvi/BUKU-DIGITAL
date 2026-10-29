{{-- resources/views/kasir/transactions/index.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Riwayat Transaksi')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Riwayat Transaksi</li>
@endsection

@section('today-sales', 'Rp ' . number_format($todaySales ?? 0, 0, ',', '.'))
@section('today-transactions', (string) ($todayTransactionsCount ?? 0))

@section('content')
    <x-card>
        @slot('title')
            Daftar Transaksi Saya
        @endslot

        @include('components.alert')

        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Metode Bayar</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                @endslot

                @forelse ($transactions as $transaction)
                    <tr>
                        <td>#{{ $transaction->id }}</td>
                        <td>{{ $transaction->transaction_date->isoFormat('D MMMM YYYY, HH:mm') }}</td>
                        <td>{{ $transaction->customer->name ?? 'Umum' }}</td>
                        <td class="text-end fw-bold">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @php
                                $pm = strtolower($transaction->payment_method);
                                $badgeClass = match($pm) {
                                    'cash' => 'bg-success text-white',
                                    'dana' => 'bg-primary text-white',
                                    'transfer bank' => 'bg-info text-dark',
                                    'debit' => 'bg-info text-dark', // Support data lama
                                    default => 'bg-secondary text-white'
                                };
                                // Ubah tampilan teks jika 'debit' jadi 'Transfer Bank'
                                $displayText = ($pm == 'debit') ? 'Transfer Bank' : $transaction->payment_method;
                            @endphp
                            <span class="badge {{ $badgeClass }}">
                                {{ ucwords($displayText) }}
                            </span>
                        </td>
                        <td class="text-center">
                            @if($transaction->payment_status === 'pending')
                                <button type="button" class="btn btn-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#payModalIndex{{ $transaction->id }}" title="Tandai Lunas">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                            @endif
                            <x-button href="{{ route('kasir.transactions.show', $transaction->id) }}" variant="info" class="btn-sm">
                                <i class="fas fa-eye"></i>
                            </x-button>
                            
                            {{-- TOMBOL EDIT BARU DENGAN OTORISASI --}}
                            @can('update', $transaction)
                                <x-button href="{{ route('kasir.transactions.edit', $transaction->id) }}" variant="warning" class="btn-sm">
                                    <i class="fas fa-edit"></i>
                                </x-button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">Anda belum memiliki riwayat transaksi.</td>
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

    {{-- Modal Pelunasan untuk Transaksi Pending --}}
    @foreach ($transactions as $transaction)
    @if($transaction->payment_status === 'pending')
    <div class="modal fade" id="payModalIndex{{ $transaction->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('kasir.transactions.markAsPaid', $transaction->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-content text-start">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="fas fa-check-circle text-success me-2"></i>Pelunasan Transaksi #{{ $transaction->id }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Pelanggan:</span>
                                <strong>{{ $transaction->customer->name ?? 'Pelanggan Umum' }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Tgl Nota:</span>
                                <span>{{ $transaction->transaction_date->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="d-flex justify-content-between fs-5 fw-bold text-danger border-top pt-2 mt-2">
                                <span>Total Tagihan:</span>
                                <span>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Metode Pembayaran</label>
                            <select name="payment_method" id="pm_idx_{{ $transaction->id }}" class="form-select" onchange="checkSettlementModeIdx_{{ $transaction->id }}()" required>
                                <option value="cash">💵 Cash / Tunai</option>
                                <option value="dana">📱 Dana</option>
                                <option value="transfer-bank">🏦 Transfer Bank</option>
                            </select>
                        </div>

                        <div class="mb-3 p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-dark small mb-2">Alokasi Tanggal Pembukuan</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_idx_today_{{ $transaction->id }}" value="today" checked onchange="checkSettlementModeIdx_{{ $transaction->id }}()">
                                <label class="form-check-label" for="settle_idx_today_{{ $transaction->id }}">
                                    <strong>Uang Baru Diterima Hari Ini (Masuk Laci / Kas Hari Ini)</strong>
                                    <br><small class="text-muted">Gunakan jika pelanggan/driver baru menyerahkan uangnya hari ini (Cash, DANA, Transfer).</small>
                                </label>
                            </div>
                            <div class="form-check" id="container_idx_nota_{{ $transaction->id }}">
                                <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_idx_nota_{{ $transaction->id }}" value="nota_date" onchange="checkSettlementModeIdx_{{ $transaction->id }}()">
                                <label class="form-check-label" for="settle_idx_nota_{{ $transaction->id }}">
                                    <strong>Koreksi Lupa Update (Uang Sudah Diterima Saat Tanggal Nota: {{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y') : '' }})</strong>
                                    <br><small class="text-muted">Hanya gunakan jika sebenarnya sudah bayar saat nota dibuat namun kasir lupa mengubah status.</small>
                                </label>
                            </div>
                            <div id="warning_nota_idx_{{ $transaction->id }}" class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none" style="font-size: 0.78rem;">
                                <i class="fas fa-exclamation-triangle me-1"></i> <strong>Perhatian:</strong> Memilih opsi ini tidak akan menambah catatan Saldo Laci hari ini karena uang dianggap sudah masuk pada tanggal nota dibuat.
                            </div>
                        </div>

                        @if(in_array(auth()->user()->role ?? '', ['admin', 'owner']))
                        <div class="mb-3 p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-dark small mb-2"><i class="fas fa-user-shield text-primary me-1"></i> Alokasi Fisik Kas Tunai (Role Admin):</label>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="cash_destination" id="dest_idx_drawer_{{ $transaction->id }}" value="drawer" checked>
                                <label class="form-check-label small" for="dest_idx_drawer_{{ $transaction->id }}">
                                    <strong>Masuk ke Laci Kasir Pembuat Nota</strong> ({{ optional($transaction->createdBy)->name ?? 'Kasir' }})
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="cash_destination" id="dest_idx_owner_{{ $transaction->id }}" value="owner">
                                <label class="form-check-label small" for="dest_idx_owner_{{ $transaction->id }}">
                                    <strong>Dipegang Langsung oleh Admin / Owner</strong> (Tidak masuk laci fisik kasir)
                                </label>
                            </div>
                        </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark d-flex justify-content-between align-items-center">
                                <span><i class="fab fa-whatsapp text-success me-1"></i> Bukti Pembayaran / Transfer Non-Tunai (WhatsApp):</span>
                                <span id="proof_badge_idx_{{ $transaction->id }}"><span class="badge bg-light text-muted border">Opsional (Cash)</span></span>
                            </label>
                            <input type="file" name="payment_proof" id="proof_idx_{{ $transaction->id }}" class="form-control form-control-sm" accept="image/*" onchange="checkSettlementModeIdx_{{ $transaction->id }}()">
                            <div id="proof_help_idx_{{ $transaction->id }}" class="text-muted" style="font-size: 0.72rem;">*Opsional untuk Cash. Ambil dari galeri tablet jika bayar transfer/DANA.</div>
                            {{-- Alert Box Penguncian Tombol Pelunasan --}}
                            <div id="proof_alert_idx_{{ $transaction->id }}" class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none" style="font-size: 0.78rem;">
                                <i class="fas fa-lock me-1"></i> <strong>Tombol Pelunasan Terkunci:</strong> Pembayaran melalui <span class="fw-bold text-uppercase" id="proof_method_name_idx_{{ $transaction->id }}">Non-Tunai</span> WAJIB melampirkan foto / screenshot bukti transfer WhatsApp. Silakan pilih file bukti dari galeri tablet di atas untuk membuka tombol pelunasan.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted">Catatan Pelunasan (Opsional)</label>
                            <input type="text" name="settlement_notes" class="form-control form-control-sm" placeholder="Misal: Bukti transfer terlampir">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success fw-bold" id="btn_submit_idx_{{ $transaction->id }}"><i class="fas fa-check me-1"></i> Validasi Pelunasan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
    function checkSettlementModeIdx_{{ $transaction->id }}() {
        const methodSelect = document.getElementById('pm_idx_{{ $transaction->id }}');
        const method = methodSelect?.value || 'cash';
        const isCash = (method === 'cash');
        const optToday = document.getElementById('settle_idx_today_{{ $transaction->id }}');
        const optNota = document.getElementById('settle_idx_nota_{{ $transaction->id }}');
        const containerNota = document.getElementById('container_idx_nota_{{ $transaction->id }}');
        const warn = document.getElementById('warning_nota_idx_{{ $transaction->id }}');
        const proofInput = document.getElementById('proof_idx_{{ $transaction->id }}');
        const proofBadge = document.getElementById('proof_badge_idx_{{ $transaction->id }}');
        const proofHelp = document.getElementById('proof_help_idx_{{ $transaction->id }}');
        const proofAlert = document.getElementById('proof_alert_idx_{{ $transaction->id }}');
        const methodNameSpan = document.getElementById('proof_method_name_idx_{{ $transaction->id }}');
        const btnSubmit = document.getElementById('btn_submit_idx_{{ $transaction->id }}');
        const hasFile = proofInput && proofInput.files && proofInput.files.length > 0;

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
                proofInput.classList.remove('border-danger', 'border-2', 'border-success');
                if (proofBadge) proofBadge.innerHTML = '<span class="badge bg-light text-muted border">Opsional (Cash)</span>';
                if (proofHelp) proofHelp.innerText = '*Opsional jika pelunasan secara Cash / Tunai.';
            }
            if (proofAlert) proofAlert.classList.add('d-none');
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.className = 'btn btn-success fw-bold';
                btnSubmit.innerHTML = '<i class="fas fa-check me-1"></i> Validasi Pelunasan';
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

            const methodName = methodSelect ? methodSelect.options[methodSelect.selectedIndex].text : 'Non-Tunai';
            if (methodNameSpan) methodNameSpan.textContent = methodName;

            if (proofInput) {
                proofInput.setAttribute('required', 'required');
            }

            if (hasFile) {
                if (proofInput) {
                    proofInput.classList.remove('border-danger');
                    proofInput.classList.add('border-success', 'border-2');
                }
                if (proofBadge) proofBadge.innerHTML = '<span class="badge bg-success text-white"><i class="fas fa-check me-1"></i>File Bukti Terlampir</span>';
                if (proofHelp) proofHelp.innerHTML = '<strong class="text-success"><i class="fas fa-check-circle me-1"></i>Bukti siap diunggah:</strong> ' + proofInput.files[0].name;
                if (proofAlert) proofAlert.classList.add('d-none');
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.className = 'btn btn-success fw-bold';
                    btnSubmit.innerHTML = '<i class="fas fa-check me-1"></i> Validasi Pelunasan';
                }
            } else {
                if (proofInput) {
                    proofInput.classList.remove('border-success');
                    proofInput.classList.add('border-danger', 'border-2');
                }
                if (proofBadge) proofBadge.innerHTML = '<span class="badge bg-danger text-white"><i class="fas fa-asterisk me-1"></i>Wajib Upload Bukti WA</span>';
                if (proofHelp) proofHelp.innerHTML = '<strong class="text-danger">*Wajib diunggah!</strong> Lampirkan screenshot bukti transfer WhatsApp pelanggan.';
                if (proofAlert) proofAlert.classList.remove('d-none');
                if (btnSubmit) {
                    btnSubmit.disabled = true;
                    btnSubmit.className = 'btn btn-secondary text-white fw-bold opacity-75';
                    btnSubmit.innerHTML = '<i class="fas fa-lock me-1"></i> Tombol Terkunci (Wajib Upload Bukti)';
                }
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('payModalIndex{{ $transaction->id }}');
        if (modalEl) {
            modalEl.addEventListener('shown.bs.modal', function() {
                checkSettlementModeIdx_{{ $transaction->id }}();
            });
        }
    });
    </script>
    @endif
    @endforeach
@endsection