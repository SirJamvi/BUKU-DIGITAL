@extends('admin.layouts.app')

@section('title', 'Daftar Transaksi')
@section('breadcrumb')
<li class="breadcrumb-item active">Transaksi</li>
@endsection

@section('content')
<x-card title="Semua Riwayat Transaksi">
    @slot('headerActions')
        <span class="badge bg-primary px-3 py-2 fs-6">
            <i class="fas fa-receipt me-1"></i> {{ number_format($transactions->total()) }} Transaksi
        </span>
    @endslot

    @include('components.alert')

    {{-- Form Pencarian & Filter --}}
    <div class="card border-0 bg-light shadow-sm mb-4" style="border-radius: 10px;">
        <div class="card-body p-3">
            <form action="{{ route('admin.transactions.index') }}" method="GET">
                <div class="row g-2 align-items-end">
                    {{-- Input Pencarian Utama --}}
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="fas fa-search text-primary me-1"></i> Kata Kunci / ID Transaksi
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-hashtag text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" 
                                   placeholder="Cari ID (#123), Pelanggan, Kasir..." 
                                   value="{{ request('search') }}" autofocus>
                        </div>
                    </div>

                    {{-- Filter Status Pembayaran --}}
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label small fw-bold text-dark mb-1">Status Bayar</label>
                        <select name="payment_status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Lunas</option>
                            <option value="pending" {{ request('payment_status') === 'pending' ? 'selected' : '' }}>Kasbon / Tempo</option>
                        </select>
                    </div>

                    {{-- Filter Dari Tanggal --}}
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label small fw-bold text-dark mb-1">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                    </div>

                    {{-- Filter Sampai Tanggal --}}
                    <div class="col-lg-2 col-md-6 col-6">
                        <label class="form-label small fw-bold text-dark mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                    </div>

                    {{-- Tombol Aksi --}}
                    <div class="col-lg-2 col-md-6 col-6 d-flex gap-1">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="fas fa-search me-1"></i> Cari
                        </button>
                        @if(request()->hasAny(['search', 'payment_status', 'start_date', 'end_date']))
                        <a href="{{ route('admin.transactions.index') }}" class="btn btn-outline-secondary" title="Reset Semua Filter">
                            <i class="fas fa-undo"></i>
                        </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Indikator Pencarian Aktif --}}
    @if(request()->filled('search') || request()->filled('payment_status') || request()->filled('start_date') || request()->filled('end_date'))
    <div class="alert alert-info py-2 px-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size: 0.88rem;">
        <div>
            <i class="fas fa-filter text-primary me-2"></i>
            Menampilkan hasil pencarian
            @if(request('search')) untuk ID / kata kunci: <strong>"{{ request('search') }}"</strong> @endif
            @if(request('payment_status')) | Status: <strong>{{ request('payment_status') === 'paid' ? 'Lunas' : 'Kasbon' }}</strong> @endif
            @if(request('start_date')) | Sejak: <strong>{{ request('start_date') }}</strong> @endif
            @if(request('end_date')) | Sampai: <strong>{{ request('end_date') }}</strong> @endif
            (Ditemukan <strong>{{ $transactions->total() }}</strong> transaksi)
        </div>
        <a href="{{ route('admin.transactions.index') }}" class="btn btn-sm btn-outline-dark bg-white">
            <i class="fas fa-times me-1"></i> Hapus Pencarian
        </a>
    </div>
    @endif

    {{-- Tabel Transaksi --}}
    <x-table>
        @slot('thead')
        <tr>
            <th style="width: 100px;">ID Transaksi</th>
            <th>Tanggal</th>
            <th>Pelanggan</th>
            <th>Petugas (Kasir / Driver)</th>
            <th class="text-end">Total & Status</th>
            <th class="text-center" style="width: 180px;">Aksi</th>
        </tr>
        @endslot

        @forelse ($transactions as $transaction)
        <tr>
            {{-- Kolom ID --}}
            <td>
                <span class="badge bg-dark fs-6 px-2 py-1">#{{ $transaction->id }}</span>
                @if($transaction->delivery_trip_code)
                    <br>
                    <span class="badge bg-warning bg-opacity-25 text-dark border border-warning small mt-1" title="Surat Jalan Pengiriman">
                        <i class="fas fa-truck text-warning me-1"></i>{{ $transaction->delivery_trip_code }}
                    </span>
                @endif
            </td>

            {{-- Kolom Tanggal --}}
            <td>
                <span class="fw-semibold text-dark">{{ $transaction->transaction_date ? $transaction->transaction_date->isoFormat('D MMM YYYY') : '-' }}</span>
                <br>
                <small class="text-muted">{{ $transaction->transaction_date ? $transaction->transaction_date->format('H:i') : '' }} WIB</small>
            </td>

            {{-- Kolom Pelanggan --}}
            <td>
                <strong class="text-dark">{{ $transaction->customer->name ?? 'Pelanggan Umum' }}</strong>
                @if($transaction->customer && $transaction->customer->phone)
                    <br><small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $transaction->customer->phone }}</small>
                @endif
            </td>

            {{-- Kolom Kasir / Driver --}}
            <td>
                <span class="text-muted small"><i class="fas fa-user-tie me-1"></i>{{ $transaction->createdBy->name ?? 'Sistem' }}</span>
                @if($transaction->driver)
                    <br><small class="text-primary"><i class="fas fa-motorcycle me-1"></i>{{ $transaction->driver->name }}</small>
                @endif
            </td>

            {{-- Kolom Total & Status Bayar --}}
            <td class="text-end">
                <div class="fw-bold fs-6 text-dark">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</div>
                @php
                    $pm = strtolower($transaction->payment_method ?? 'cash');
                    $isPaid = $transaction->payment_status === 'paid';
                @endphp
                @if($isPaid)
                    <span class="badge bg-success bg-opacity-10 text-success border border-success py-1 px-2" style="font-size: 0.75rem;">
                        <i class="fas fa-check-circle me-1"></i>Lunas ({{ strtoupper($pm) }})
                    </span>
                @else
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger py-1 px-2" style="font-size: 0.75rem;">
                        <i class="fas fa-clock me-1"></i>Kasbon
                    </span>
                @endif
            </td>

            {{-- Kolom Aksi --}}
            <td class="text-center">
                <div class="d-flex justify-content-center gap-1">
                    {{-- Tombol Pelunasan jika Status Pending / Kasbon --}}
                    @if($transaction->payment_status === 'pending')
                    <button type="button" class="btn btn-sm btn-success fw-bold text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAdminPay_{{ $transaction->id }}" title="Validasi Pelunasan Kasbon #{{ $transaction->id }}">
                        <i class="fas fa-check"></i> Lunasi
                    </button>
                    @endif

                    {{-- Detail --}}
                    <a href="{{ route('admin.transactions.show', $transaction->id) }}" class="btn btn-sm btn-info text-white" title="Lihat Rincian Transaksi #{{ $transaction->id }}">
                        <i class="fas fa-eye"></i> Detail
                    </a>

                    {{-- Edit --}}
                    <a href="{{ route('admin.transactions.edit', $transaction->id) }}" class="btn btn-sm btn-warning text-dark fw-bold" title="Edit Transaksi #{{ $transaction->id }}">
                        <i class="fas fa-edit"></i> Edit
                    </a>

                    {{-- Hapus dengan Rollback --}}
                    <form action="{{ route('admin.transactions.destroy', $transaction->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi #{{ $transaction->id }} ini? Semua stok barang, arus kas, dan poin pelanggan terkait akan dikembalikan (Rollback) secara otomatis.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus Transaksi #{{ $transaction->id }}">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="6" class="text-center py-5 text-muted">
                <i class="fas fa-search fa-3x mb-3 text-secondary opacity-50"></i>
                <h6 class="fw-bold text-secondary">Tidak ada transaksi ditemukan</h6>
                @if(request()->hasAny(['search', 'payment_status', 'start_date', 'end_date']))
                    <p class="small mb-2">Tidak ada data transaksi yang cocok dengan kriteria filter pencarian Anda.</p>
                    <a href="{{ route('admin.transactions.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-undo me-1"></i> Reset Pencarian
                    </a>
                @else
                    <p class="small mb-0">Belum ada riwayat transaksi yang tersimpan di sistem.</p>
                @endif
            </td>
        </tr>
        @endforelse
    </x-table>

    {{-- Pagination Links --}}
    @if ($transactions->hasPages())
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">
            Menampilkan data <strong>{{ $transactions->firstItem() ?? 0 }}</strong> sampai <strong>{{ $transactions->lastItem() ?? 0 }}</strong> dari total <strong>{{ $transactions->total() }}</strong> transaksi
        </small>
        <div>
            {{ $transactions->links('components.pagination') }}
        </div>
    </div>
    @endif
</x-card>

{{-- MODAL PELUNASAN KASBON KHUSUS ADMIN --}}
@foreach ($transactions as $transaction)
@if($transaction->payment_status === 'pending')
<div class="modal fade" id="modalAdminPay_{{ $transaction->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold mb-0">
                    <i class="fas fa-money-bill-wave text-success me-2"></i> Pelunasan Kasbon #{{ $transaction->id }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.transactions.markAsPaid', $transaction->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4 text-start">
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Pelanggan:</span>
                            <strong class="text-dark">{{ optional($transaction->customer)->name ?? 'Pelanggan Umum' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Kasir Pembuat Nota:</span>
                            <strong class="text-primary">{{ optional($transaction->createdBy)->name ?? 'Kasir' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Tanggal Nota:</span>
                            <strong class="text-dark">{{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i') : '-' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <span class="fw-bold text-dark">Total Tagihan:</span>
                            <strong class="fs-5 text-danger">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Metode Pembayaran</label>
                        <select name="payment_method" id="admin_pm_{{ $transaction->id }}" class="form-select" onchange="checkAdminSettlementMode_{{ $transaction->id }}()" required>
                            <option value="cash">💵 Cash / Tunai</option>
                            <option value="dana">📱 Dana</option>
                            <option value="transfer-bank">🏦 Transfer Bank</option>
                        </select>
                    </div>

                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label fw-bold text-dark small mb-2">Alokasi Tanggal Pembukuan</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="settlement_date_mode" id="admin_settle_today_{{ $transaction->id }}" value="today" checked onchange="checkAdminSettlementMode_{{ $transaction->id }}()">
                            <label class="form-check-label" for="admin_settle_today_{{ $transaction->id }}">
                                <strong>Uang Baru Diterima Hari Ini (Masuk Laci / Kas Hari Ini)</strong>
                                <br><small class="text-muted">Gunakan jika pelanggan/driver baru menyerahkan uangnya hari ini (Cash, DANA, Transfer).</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="settlement_date_mode" id="admin_settle_nota_{{ $transaction->id }}" value="nota_date" onchange="checkAdminSettlementMode_{{ $transaction->id }}()">
                            <label class="form-check-label" for="admin_settle_nota_{{ $transaction->id }}">
                                <strong>Koreksi Lupa Update (Uang Sudah Diterima Saat Tanggal Nota: {{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y') : '' }})</strong>
                                <br><small class="text-muted">Hanya gunakan jika sebenarnya sudah bayar saat nota dibuat namun kasir lupa mengubah status.</small>
                            </label>
                        </div>
                        <div id="admin_warning_nota_{{ $transaction->id }}" class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none" style="font-size: 0.78rem;">
                            <i class="fas fa-exclamation-triangle me-1"></i> <strong>Perhatian:</strong> Memilih opsi ini tidak akan menambah catatan Saldo Laci hari ini karena uang dianggap sudah masuk pada tanggal nota dibuat.
                        </div>
                    </div>

                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label fw-bold text-dark small mb-2"><i class="fas fa-user-shield text-primary me-1"></i> Alokasi Fisik Kas Tunai (Role Admin):</label>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="cash_destination" id="admin_dest_drawer_{{ $transaction->id }}" value="drawer" checked>
                            <label class="form-check-label small" for="admin_dest_drawer_{{ $transaction->id }}">
                                <strong>Masuk ke Laci Kasir Pembuat Nota</strong> ({{ optional($transaction->createdBy)->name ?? 'Kasir' }})
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="cash_destination" id="admin_dest_owner_{{ $transaction->id }}" value="owner">
                            <label class="form-check-label small" for="admin_dest_owner_{{ $transaction->id }}">
                                <strong>Dipegang Langsung oleh Admin / Owner</strong> (Tidak masuk laci fisik kasir)
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Catatan Pelunasan (Opsional)</label>
                        <input type="text" name="settlement_notes" class="form-control form-control-sm" placeholder="Misal: Bukti transfer BCA terlampir">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-check me-1"></i> Validasi Pelunasan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function checkAdminSettlementMode_{{ $transaction->id }}() {
    const method = document.getElementById('admin_pm_{{ $transaction->id }}')?.value;
    const isNota = document.getElementById('admin_settle_nota_{{ $transaction->id }}')?.checked;
    const warn = document.getElementById('admin_warning_nota_{{ $transaction->id }}');
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
@endforeach
@endsection