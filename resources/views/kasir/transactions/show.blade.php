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
                            <select name="payment_method" class="form-select" required>
                                <option value="cash">💵 Cash / Tunai</option>
                                <option value="dana">📱 Dana</option>
                                <option value="transfer-bank">🏦 Transfer Bank</option>
                            </select>
                        </div>

                        <div class="mb-3 p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-dark small mb-2">Alokasi Tanggal Pembukuan</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_show_today_{{ $transaction->id }}" value="today" checked>
                                <label class="form-check-label" for="settle_show_today_{{ $transaction->id }}">
                                    <strong>Uang Diterima Hari Ini</strong> (Masuk kas laci hari ini)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_show_nota_{{ $transaction->id }}" value="nota_date">
                                <label class="form-check-label" for="settle_show_nota_{{ $transaction->id }}">
                                    <strong>Koreksi: Sudah Dibayar pd Tgl Nota Awal</strong> ({{ $transaction->transaction_date->format('d/m/Y') }})
                                    <br><small class="text-muted">Tidak mengotori/menambah kas laci kasir hari ini</small>
                                </label>
                            </div>
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
    @endif
@endsection