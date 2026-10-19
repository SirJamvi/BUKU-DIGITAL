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
                                <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_idx_today_{{ $transaction->id }}" value="today" checked>
                                <label class="form-check-label" for="settle_idx_today_{{ $transaction->id }}">
                                    <strong>Uang Diterima Hari Ini</strong> (Masuk kas laci hari ini)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="settlement_date_mode" id="settle_idx_nota_{{ $transaction->id }}" value="nota_date">
                                <label class="form-check-label" for="settle_idx_nota_{{ $transaction->id }}">
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
    @endforeach
@endsection