{{-- resources/views/kasir/transactions/show.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Detail Transaksi')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('kasir.transactions.index') }}">Riwayat Transaksi</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail #{{ $transaction->id }}</li>
@endsection

@section('content')
    <x-card>
        @slot('title')
            Detail Transaksi #{{ $transaction->id }}
        @endslot
        @slot('headerActions')
            @if($transaction->payment_status === 'pending')
                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#payModalShow{{ $transaction->id }}">
                    <i class="fas fa-check-circle me-1"></i> Tandai Lunas
                </button>
            @endif
            <a href="#" class="btn btn-secondary" onclick="window.print(); return false;">
                <i class="fas fa-print me-2"></i>Cetak Struk
            </a>
        @endslot

        <div class="receipt-preview p-3">
            <div class="header text-center mb-4">
                <h4 class="fw-bold">{{ config('app.name', 'Toko Anda') }}</h4>
                <p class="text-muted mb-0">Alamat Toko Anda di Sini<br>Telp: 08123456789</p>
            </div>
            
            <hr style="border-style: dashed;">

            <div class="info row mb-3">
                <div class="col-6">
                    <div><strong>No. Struk:</strong> #{{ $transaction->id }}</div>
                    <div><strong>Tanggal:</strong> {{ $transaction->transaction_date->format('d/m/Y H:i') }}</div>
                </div>
                <div class="col-6 text-end">
                    <div><strong>Kasir:</strong> {{ $transaction->createdBy->name ?? 'N/A' }}</div>
                    <div><strong>Pelanggan:</strong> {{ $transaction->customer->name ?? 'Umum' }}</div>
                </div>
            </div>
            
            <hr style="border-style: dashed;">

            <div class="items-table">
                @foreach($transaction->details as $item)
                    <div class="row g-2 mb-2">
                        <div class="col-12"><strong>{{ $item->product->name }}</strong></div>
                        <div class="col-6">{{ $item->quantity }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }}</div>
                        <div class="col-6 text-end fw-bold">Rp {{ number_format($item->total_price, 0, ',', '.') }}</div>
                    </div>
                @endforeach
            </div>

            <hr style="border-style: dashed;">
            
            <div class="totals mt-3">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Subtotal</span>
                    <span>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</span>
                </div>
                <div class="d-flex justify-content-between fs-5 fw-bold mt-2 pt-2 border-top">
                    <span >Total</span>
                    <span style="color: var(--kasir-accent);">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</span>
                </div>
                 <div class="d-flex justify-content-between mt-1">
                    <span class="text-muted">Metode Pembayaran</span>
                    <span>{{ $transaction->payment_method }}</span>
                </div>
            </div>

            <div class="footer text-center mt-4">
                <p class="text-muted">--- Terima kasih telah berbelanja ---</p>
            </div>
        </div>
    </x-card>

    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-preview, .receipt-preview * {
                visibility: visible;
            }
            .receipt-preview {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>

    @if($transaction->payment_status === 'pending')
    <div class="modal fade" id="payModalShow{{ $transaction->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('kasir.transactions.markAsPaid', $transaction->id) }}" method="POST">
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
                            <select name="payment_method" id="pm_show_{{ $transaction->id }}" class="form-select" onchange="checkSettlementMode_{{ $transaction->id }}()" required>
                                <option value="cash">💵 Cash / Tunai</option>
                                <option value="dana">📱 Dana</option>
                                <option value="transfer-bank">🏦 Transfer Bank</option>
                            </select>
                        </div>

                        <div class="mb-3 p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-dark small mb-2">Alokasi Tanggal Pembukuan</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_show_today_{{ $transaction->id }}" value="today" checked onchange="checkSettlementMode_{{ $transaction->id }}()">
                                <label class="form-check-label" for="settle_show_today_{{ $transaction->id }}">
                                    <strong>Uang Baru Diterima Hari Ini (Masuk Laci / Kas Hari Ini)</strong>
                                    <br><small class="text-muted">Gunakan jika pelanggan/driver baru menyerahkan uangnya hari ini (Cash, DANA, Transfer).</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_show_nota_{{ $transaction->id }}" value="nota_date" onchange="checkSettlementMode_{{ $transaction->id }}()">
                                <label class="form-check-label" for="settle_show_nota_{{ $transaction->id }}">
                                    <strong>Koreksi Lupa Update (Uang Sudah Diterima Saat Tanggal Nota: {{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y') : '' }})</strong>
                                    <br><small class="text-muted">Hanya gunakan jika sebenarnya sudah bayar saat nota dibuat namun kasir lupa mengubah status.</small>
                                </label>
                            </div>
                            <div id="warning_nota_show_{{ $transaction->id }}" class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none" style="font-size: 0.78rem;">
                                <i class="fas fa-exclamation-triangle me-1"></i> <strong>Perhatian:</strong> Memilih opsi ini tidak akan menambah catatan Saldo Laci hari ini karena uang dianggap sudah masuk pada tanggal nota dibuat.
                            </div>
                        </div>

                        @if(in_array(auth()->user()->role ?? '', ['admin', 'owner']))
                        <div class="mb-3 p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-dark small mb-2"><i class="fas fa-user-shield text-primary me-1"></i> Alokasi Fisik Kas Tunai (Role Admin):</label>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="cash_destination" id="dest_show_drawer_{{ $transaction->id }}" value="drawer" checked>
                                <label class="form-check-label small" for="dest_show_drawer_{{ $transaction->id }}">
                                    <strong>Masuk ke Laci Kasir Pembuat Nota</strong> ({{ optional($transaction->createdBy)->name ?? 'Kasir' }})
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="cash_destination" id="dest_show_owner_{{ $transaction->id }}" value="owner">
                                <label class="form-check-label small" for="dest_show_owner_{{ $transaction->id }}">
                                    <strong>Dipegang Langsung oleh Admin / Owner</strong> (Tidak masuk laci fisik kasir)
                                </label>
                            </div>
                        </div>
                        @endif

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
    function checkSettlementMode_{{ $transaction->id }}() {
        const method = document.getElementById('pm_show_{{ $transaction->id }}')?.value;
        const isNota = document.getElementById('settle_show_nota_{{ $transaction->id }}')?.checked;
        const warn = document.getElementById('warning_nota_show_{{ $transaction->id }}');
        if (warn) {
            if (isNota && method === 'cash') {
                warn.classList.remove('d-none');
            } else {
                warn.classList.add('d-none');
            }
        }
    }
    </script>
    @endif
@endsection