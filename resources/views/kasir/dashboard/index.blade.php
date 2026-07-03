{{-- resources/views/kasir/dashboard/index.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Dashboard Kasir')

@section('breadcrumb')
@endsection

@section('content')
<div class="container-fluid">
    {{-- Salam Pembuka --}}
    <div class="mb-4">
        <h3 class="fw-bold">Selamat Datang, {{ Auth::user()->name }}!</h3>
        <p class="text-muted">Berikut adalah ringkasan aktivitas penjualan Anda hari ini.</p>
    </div>

    @include('components.alert')

    {{-- ================================================================= --}}
    {{-- [BARU] KARTU PERINGATAN KASBON (DIPERKECIL / DIGRUP PER PELANGGAN) --}}
    {{-- ================================================================= --}}
    @if(isset($kasbonByCustomer) && $kasbonByCustomer->isNotEmpty())
    <div class="card border-danger mb-4 shadow-sm">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center py-3">
            <div>
                <h6 class="mb-0 fw-bold">
                    <i class="fas fa-exclamation-triangle me-2"></i> RINGKASAN PIUTANG KASBON AKTIF
                </h6>
                <small class="opacity-75">Terdapat {{ $totalKasbonCount }} struk tertunda dari {{ $kasbonByCustomer->count() }} pelanggan / mitra.</small>
            </div>
            <div class="text-end">
                <span class="d-block text-white-50 small">Total Seluruh Tagihan:</span>
                <span class="fs-5 fw-bold badge bg-white text-danger">Rp {{ number_format($totalKasbonAmount, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Nama Pelanggan / Mitra</th>
                            <th class="text-center">Struk Tertunda</th>
                            <th>Order Tertua</th>
                            <th class="text-end">Total Tagihan</th>
                            <th class="text-center pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kasbonByCustomer as $groupKey => $transactions)
                        @php
                        $firstTx = $transactions->first();
                        $customerName = $firstTx->customer->name ?? 'Pelanggan Umum (Tanpa Nama)';
                        $totalTagihanCust = $transactions->sum('total_amount');
                        $oldestDate = $firstTx->transaction_date;
                        $modalId = 'modalGroup_' . $groupKey;
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <span class="fw-bold fs-6 text-dark">{{ $customerName }}</span>
                                @if($firstTx->customer && $firstTx->customer->phone)
                                <br><small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $firstTx->customer->phone }}</small>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary rounded-pill px-3 py-2 fs-6">
                                    {{ $transactions->count() }} Struk
                                </span>
                            </td>
                            <td>
                                <span class="text-dark">{{ $oldestDate->format('d/m/Y') }}</span><br>
                                @if($oldestDate->diffInDays(now()) > 0)
                                <small class="badge bg-warning text-dark">
                                    Sejak {{ $oldestDate->diffInDays(now()) }} hari lalu
                                </small>
                                @else
                                <small class="text-info fw-bold">Hari Ini</small>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-danger fs-6">
                                Rp {{ number_format($totalTagihanCust, 0, ',', '.') }}
                            </td>
                            <td class="text-center pe-3">
                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                    <i class="fas fa-list-ul me-1"></i> Lihat & Bayar
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    {{-- ================================================================= --}}

    {{-- Kartu Ringkasan Data --}}
    <div class="row g-4">
        {{-- Saldo Kasir Hari Ini (Real-Time Setelah Dipotong Pengeluaran) --}}
        <div class="col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--kasir-bg-secondary);">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="p-3 rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10">
                            <i class="fas fa-wallet fa-2x text-success"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="text-muted mb-1">Saldo Laci Kasir (Hari Ini)</h6>
                        <h4 class="fw-bold mb-0 text-success">Rp {{ number_format($myNetCashToday ?? 0, 0, ',', '.') }}</h4>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            Masuk: Rp {{ number_format($mySalesToday ?? 0, 0, ',', '.') }} |
                            Keluar: <span class="text-danger">Rp {{ number_format($myExpensesToday ?? 0, 0, ',', '.') }}</span>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Jumlah Transaksi --}}
        <div class="col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--kasir-bg-dominant);">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="p-3 rounded-circle d-flex align-items-center justify-content-center" style="background-color: rgba(247, 86, 124, 0.1);">
                            <i class="fas fa-receipt fa-2x" style="color: var(--kasir-accent);"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="text-muted mb-1">Transaksi Lunas</h6>
                        <h4 class="fw-bold mb-0">{{ number_format($myTransactionsToday ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- Produk Terjual Hari Ini --}}
        <div class="col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--kasir-bg-dominant);">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 me-3">
                            <div class="p-3 rounded-circle d-flex align-items-center justify-content-center" style="background-color: rgba(247, 86, 124, 0.1);">
                                <i class="fas fa-box-open fa-2x" style="color: var(--kasir-accent);"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-1">Produk Keluar Hari Ini</h6>
                        </div>
                    </div>

                    <div class="list-group list-group-flush">
                        @forelse ($productsSoldToday as $product)
                        <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-1">
                            <span class="small">{{ $product->name }}</span>
                            <span class="badge bg-secondary rounded-pill">{{ $product->total_quantity_sold }} Pcs</span>
                        </div>
                        @empty
                        <p class="text-center text-muted mt-2 small">Belum ada produk terjual.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Kartu Rincian Saldo per Metode Pembayaran (Real-Time) --}}
    <div class="row mt-4">
        <div class="col-12">
            <x-card title="Saldo Kas Aktual Hari Ini per Metode Pembayaran">
                @if(isset($balancesByMethod) && $balancesByMethod->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Metode Pembayaran</th>
                                <th class="text-end">Uang Masuk (Penjualan/Kasbon)</th>
                                <th class="text-end">Uang Keluar (Expenses)</th>
                                <th class="text-end">Saldo Akhir Aktual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($balancesByMethod as $method => $data)
                            <tr>
                                <td class="fw-bold fs-6">
                                    @if(strtolower($method) == 'cash') 💵 @elseif(strtolower($method) == 'dana') 📱 @else 🏦 @endif
                                    {{ strtoupper(str_replace('-', ' ', $method)) }}
                                </td>
                                <td class="text-end text-success fw-semibold">
                                    + Rp {{ number_format($data['income'], 0, ',', '.') }}
                                </td>
                                <td class="text-end text-danger fw-semibold">
                                    - Rp {{ number_format($data['expense'], 0, ',', '.') }}
                                </td>
                                <td class="text-end fw-bold fs-6">
                                    <span class="badge {{ $data['balance'] >= 0 ? 'bg-success' : 'bg-danger' }} px-3 py-2 fs-6">
                                        Rp {{ number_format($data['balance'], 0, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted text-center my-3">Belum ada arus kas (masuk maupun keluar) hari ini.</p>
                @endif
            </x-card>
        </div>
    </div>

    {{-- Kartu Rincian Metode Pembayaran --}}
    <div class="row mt-4">
        <div class="col-12">
            <x-card title="Rincian Penjualan Hari Ini per Metode Pembayaran">
                @if(isset($salesByPaymentMethod) && $salesByPaymentMethod->isNotEmpty())
                <div class="list-group">
                    @foreach($salesByPaymentMethod as $method => $total)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="fw-bold">{{ ucfirst($method) }}</span>
                        <span class="badge rounded-pill" style="background-color: var(--kasir-accent); font-size: 1rem;">
                            Rp {{ number_format($total, 0, ',', '.') }}
                        </span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted text-center">Belum ada penjualan hari ini.</p>
                @endif
            </x-card>
        </div>
    </div>

    {{-- Kartu Aksi Cepat --}}
    <div class="row mt-4">
        <div class="col-12">
            <x-card title="Aksi Cepat">
                <p>Mulai pekerjaan Anda dengan mengakses fitur yang paling sering digunakan.</p>
                <div class="d-grid gap-2 d-md-flex">
                    <a href="{{ route('kasir.pos.index') }}" class="btn btn-lg" style="background-color: var(--kasir-accent); color: white;">
                        <i class="fas fa-desktop me-2"></i>Mulai Transaksi (POS)
                    </a>
                    <a href="{{ route('kasir.customers.create') }}" class="btn btn-lg btn-outline-secondary">
                        <i class="fas fa-user-plus me-2"></i>Tambah Pelanggan
                    </a>
                </div>
            </x-card>
        </div>
    </div>
</div>
{{-- ============================================================= --}}
{{-- [BARU] MODAL RINCIAN STRUK PER PELANGGAN (DENGAN CHECKBOX) --}}
{{-- ============================================================= --}}
@if(isset($kasbonByCustomer) && $kasbonByCustomer->isNotEmpty())
@foreach($kasbonByCustomer as $groupKey => $transactions)
@php
$firstTx = $transactions->first();
$customerName = $firstTx->customer->name ?? 'Pelanggan Umum';
$modalId = 'modalGroup_' . $groupKey;
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            {{-- FORM DIMULAI DARI SINI, MEMBUNGKUS SELURUH MODAL --}}
            <form action="{{ route('kasir.transactions.bulkMarkAsPaid') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-clock me-2 text-warning"></i>Daftar Kasbon: <strong>{{ $customerName }}</strong>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-0">
                    <div class="bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="text-muted">Total {{ $transactions->count() }} transaksi tertunda.</span>
                        <h5 class="mb-0 text-danger fw-bold">Total: Rp {{ number_format($transactions->sum('total_amount'), 0, ',', '.') }}</h5>
                    </div>

                    <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    {{-- Checkbox Pilih Semua --}}
                                    <th class="ps-3" style="width: 40px;">
                                        <input class="form-check-input select-all-btn" type="checkbox" data-target=".chk-{{ $groupKey }}" style="transform: scale(1.2);">
                                    </th>
                                    <th>No. Struk</th>
                                    <th>Tanggal Order</th>
                                    <th>Catatan</th>
                                    <th class="text-end pe-4">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $kasbon)
                                <tr>
                                    <td class="ps-3">
                                        {{-- Checkbox Individual --}}
                                        <input class="form-check-input chk-{{ $groupKey }} item-checkbox" type="checkbox" name="transaction_ids[]" value="{{ $kasbon->id }}" data-amount="{{ $kasbon->total_amount }}" style="transform: scale(1.2);">
                                    </td>
                                    <td class="fw-bold text-primary">
                                        #{{ str_pad($kasbon->id, 6, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td>
                                        <span>{{ $kasbon->transaction_date->format('d/m/Y') }}</span><br>
                                        <small class="text-muted">{{ $kasbon->transaction_date->format('H:i') }} WIB</small>
                                    </td>
                                    <td>
                                        <small class="text-muted fst-italic">{{ $kasbon->notes ?? '-' }}</small>
                                    </td>
                                    <td class="text-end fw-bold pe-4">
                                        Rp {{ number_format($kasbon->total_amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer bg-light justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <label class="fw-bold mb-0">Pembayaran via:</label>
                        <select name="payment_method" class="form-select form-select-sm" style="width: 150px;" required>
                            <option value="cash">💵 Cash / Tunai</option>
                            <option value="transfer-bank">🏦 Transfer Bank</option>
                            <option value="dana">📱 DANA</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="fas fa-check-double me-1"></i> Lunasi Transaksi Terpilih
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif

{{-- Script untuk fungsi Centang Semua (Select All) --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllBtns = document.querySelectorAll('.select-all-btn');

        selectAllBtns.forEach(btn => {
            btn.addEventListener('change', function() {
                const targetClass = this.getAttribute('data-target');
                const checkboxes = document.querySelectorAll(targetClass);

                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
            });
        });

        // Opsional: Jika salah satu uncheck, hapus centang dari "Pilih Semua"
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');
        itemCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const modal = this.closest('.modal');
                const selectAllBtn = modal.querySelector('.select-all-btn');
                const allItems = modal.querySelectorAll('.item-checkbox');
                const allChecked = Array.from(allItems).every(i => i.checked);
                const someChecked = Array.from(allItems).some(i => i.checked);

                selectAllBtn.checked = allChecked;
                selectAllBtn.indeterminate = someChecked && !allChecked;
            });
        });
    });
</script>

@endsection