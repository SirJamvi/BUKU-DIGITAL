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
    {{-- [BARU] KARTU PENGIRIMAN DRIVER BERJALAN (CONTAINER DI ATAS KASBON) --}}
    {{-- ================================================================= --}}
    @if(isset($activeTrips) && $activeTrips->isNotEmpty())
    <div class="card border-0 mb-4 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-transparent text-white d-flex justify-content-between align-items-center py-3 border-bottom border-secondary border-opacity-25">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-truck-moving text-warning me-2"></i> PENGIRIMAN DRIVER BERJALAN
                </h5>
                <small class="text-white-50">Terdapat {{ $activeTrips->count() }} rombongan pengiriman driver sedang dalam perjalanan membawa pesanan.</small>
            </div>
            <div class="text-end">
                <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold">
                    <i class="fas fa-spinner fa-spin me-1"></i> {{ $activeTrips->flatten()->count() }} Pesanan Belum Selesai
                </span>
            </div>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                @foreach($activeTrips as $tripKey => $tripTrx)
                @php
                    $firstTrx = $tripTrx->first();
                    $driverName = $firstTrx->driver->name ?? 'Driver';
                    $driverPhone = $firstTrx->driver->phone ?? '-';
                    $tripCode = $firstTrx->delivery_trip_code ?? $tripKey;
                    $tripTotal = $tripTrx->sum('total_amount');
                    $modalTripId = 'modalTrip_' . \Illuminate\Support\Str::slug($tripKey);
                    $totalQtyInTrip = 0;
                    foreach($tripTrx as $trxItem) {
                        $totalQtyInTrip += $trxItem->details->sum('quantity');
                    }
                @endphp
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border border-secondary border-opacity-50 shadow-sm" style="background-color: rgba(255, 255, 255, 0.05); color: #fff; border-radius: 10px;">
                        <div class="card-header bg-transparent border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center py-2">
                            <div>
                                <span class="badge bg-primary text-white me-1">{{ $tripCode }}</span>
                                <strong class="fs-6 text-white">{{ $driverName }}</strong>
                            </div>
                            <span class="badge bg-warning text-dark small">
                                <i class="fas fa-motorcycle me-1"></i> Mengantar
                            </span>
                        </div>
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between text-white-50 small mb-2">
                                <span><i class="far fa-clock me-1"></i>{{ $firstTrx->transaction_date->format('H:i') }} WIB</span>
                                <span>{{ $firstTrx->transaction_date->diffForHumans() }}</span>
                            </div>

                            <div class="p-2 rounded mb-2" style="background: rgba(0,0,0,0.2); max-height: 120px; overflow-y: auto;">
                                <small class="text-white-50 fw-bold d-block mb-1">Daftar Pelanggan ({{ $tripTrx->count() }} Toko):</small>
                                <ul class="list-unstyled mb-0 small">
                                    @foreach($tripTrx as $trx)
                                    <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary border-opacity-25">
                                        <span class="text-truncate" style="max-width: 170px;">
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i><strong>{{ $trx->customer->name ?? 'Pelanggan Umum' }}</strong>
                                        </span>
                                        <span class="text-warning fw-bold">Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</span>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="d-flex justify-content-between align-items-center text-white small">
                                <span>Total Muatan: <strong>{{ $totalQtyInTrip }} Item</strong></span>
                                <span class="fw-bold fs-6 text-success">Total: Rp {{ number_format($tripTotal, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top border-secondary border-opacity-25 p-2">
                            <button type="button" class="btn btn-warning btn-sm w-100 fw-bold text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#{{ $modalTripId }}">
                                <i class="fas fa-check-double me-1"></i> Selesaikan & Input Real Payment
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
    {{-- ================================================================= --}}

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
                <span class="d-block text-white-50 small" style="font-size: 0.75rem;">Total Seluruh Tagihan:</span>
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
                                <br><small class="text-muted"><i class="fab fa-whatsapp text-success small me-1"></i>{{ $firstTx->customer->phone }}</small>
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
                                    Sejak {{ (int) $oldestDate->diffInDays(now()) }} hari lalu
                                </small>
                                @else
                                <small class="text-info fw-bold">Hari Ini</small>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-danger fs-6">
                                Rp {{ number_format($totalTagihanCust, 0, ',', '.') }}
                            </td>
                            <td class="text-center pe-3">
                                <div class="btn-group shadow-sm" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-3" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                        <i class="fas fa-list-ul me-1"></i> Lihat & Bayar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success fw-bold px-3" data-bs-toggle="modal" data-bs-target="#modalRekap_{{ $groupKey }}" title="Rekap Semua Transaksi {{ $customerName }} & Kirim Struk ke WA">
                                        <i class="fab fa-whatsapp me-1"></i> Rekap Struk (WA)
                                    </button>
                                </div>
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
{{-- [BARU] MODAL PENYELESAIAN PENGIRIMAN DRIVER KOLEKTIF          --}}
{{-- ============================================================= --}}
@if(isset($activeTrips) && $activeTrips->isNotEmpty())
@foreach($activeTrips as $tripKey => $tripTrx)
@php
    $firstTrx = $tripTrx->first();
    $driverName = $firstTrx->driver->name ?? 'Driver';
    $tripCode = $firstTrx->delivery_trip_code ?? $tripKey;
    $modalTripId = 'modalTrip_' . \Illuminate\Support\Str::slug($tripKey);
    $totalTripAmount = $tripTrx->sum('total_amount');
@endphp
<div class="modal fade" id="{{ $modalTripId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('kasir.transactions.completeDeliveryTrip') }}" method="POST">
                @csrf
                <input type="hidden" name="trip_code" value="{{ $tripCode }}">

                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-truck-loading text-warning me-2"></i>Penyelesaian Pengiriman: <strong>Driver {{ $driverName }}</strong> (<span class="text-warning">{{ $tripCode }}</span>)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-0">
                    <div class="bg-light p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="text-muted">Total <strong>{{ $tripTrx->count() }} pesanan</strong> dalam pengiriman ini.</span>
                            <br><small class="text-muted">Pilih metode pembayaran riil (Cash, DANA, Transfer, atau Kasbon) untuk masing-masing pelanggan setelah driver kembali.</small>
                        </div>
                        <h4 class="mb-0 text-danger fw-bold">Total: Rp {{ number_format($totalTripAmount, 0, ',', '.') }}</h4>
                    </div>

                    <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3">No. Struk</th>
                                    <th>Pelanggan / Toko</th>
                                    <th>Detail Barang</th>
                                    <th class="text-end">Nilai Tagihan</th>
                                    <th style="min-width: 220px;">Metode Pembayaran Riil</th>
                                    <th style="min-width: 160px;" class="pe-3">Catatan (Opsional)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tripTrx as $trx)
                                <tr>
                                    <td class="ps-3 fw-bold text-primary">
                                        #{{ str_pad($trx->id, 6, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td>
                                        <strong class="fs-6 text-dark">{{ $trx->customer->name ?? 'Pelanggan Umum' }}</strong>
                                        @if($trx->customer && $trx->customer->phone)
                                        <br><small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $trx->customer->phone }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <ul class="list-unstyled mb-0 small text-muted">
                                            @foreach($trx->details as $item)
                                            <li>• {{ $item->product->name ?? 'Item' }} ({{ $item->quantity }} {{ $item->product->unit ?? 'pcs' }})</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td class="text-end fw-bold text-danger fs-6">
                                        Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                                    </td>
                                    <td>
                                        <select name="payments[{{ $trx->id }}]" class="form-select form-select-sm fw-bold border-primary" required>
                                            <option value="" disabled>-- Pilih Pembayaran Riil --</option>
                                            @if(isset($paymentMethods) && $paymentMethods->isNotEmpty())
                                                @foreach($paymentMethods as $pm)
                                                <option value="{{ $pm->slug }}" {{ strtolower($pm->slug) === 'cash' ? 'selected' : '' }}>
                                                    @if(str_contains(strtolower($pm->slug), 'cash')) 💵
                                                    @elseif(str_contains(strtolower($pm->slug), 'dana')) 📱
                                                    @elseif(str_contains(strtolower($pm->slug), 'transfer')) 🏦
                                                    @elseif(str_contains(strtolower($pm->slug), 'qris')) 📲
                                                    @else 💳 @endif
                                                    {{ $pm->name }} (Lunas)
                                                </option>
                                                @endforeach
                                            @else
                                                <option value="cash" selected>💵 Cash / Tunai (Lunas)</option>
                                                <option value="transfer-bank">🏦 Transfer Bank (Lunas)</option>
                                                <option value="dana">📱 DANA (Lunas)</option>
                                            @endif
                                            <option value="kasbon" class="text-danger fw-bold">
                                                ⚠️ TETAP KASBON (Belum Lunas)
                                            </option>
                                        </select>
                                    </td>
                                    <td class="pe-3">
                                        <input type="text" name="notes[{{ $trx->id }}]" class="form-control form-control-sm" placeholder="Ket...">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer bg-light justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i> Transaksi lunas otomatis tercatat ke <strong>Cash Flow</strong>. Transaksi 'Tetap Kasbon' masuk ke piutang kasbon di bawah.
                        </small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm">
                            <i class="fas fa-check-circle me-1"></i> Simpan Semua Pelunasan & Selesaikan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif

{{-- ============================================================= --}}
{{-- [BARU] MODAL RINCIAN STRUK PER PELANGGAN (DENGAN CHECKBOX) --}}
{{-- ============================================================= --}}
@if(isset($kasbonByCustomer) && $kasbonByCustomer->isNotEmpty())
@foreach($kasbonByCustomer as $groupKey => $transactions)
@php
$firstTx = $transactions->first();
$customerName = $firstTx->customer->name ?? 'Pelanggan Umum';
$totalTagihanCust = $transactions->sum('total_amount');
$oldestDate = $firstTx ? $firstTx->transaction_date : now();
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
                        <div>
                            <span class="text-muted">Total {{ $transactions->count() }} transaksi tertunda.</span>
                            @if($firstTx->customer && $firstTx->customer->phone)
                            <div class="small text-muted"><i class="fab fa-whatsapp text-success me-1"></i> {{ $firstTx->customer->phone }}</div>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-success fw-bold shadow-sm" onclick="shareCustomerKasbonWA('{{ $groupKey }}')" title="Kirim Tagihan ke WhatsApp Pelanggan">
                                <i class="fab fa-whatsapp me-1"></i> Kirim WA Tagihan
                            </button>
                            <h5 class="mb-0 text-danger fw-bold ms-2">Total: Rp {{ number_format($transactions->sum('total_amount'), 0, ',', '.') }}</h5>
                        </div>
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

{{-- ============================================================= --}}
{{-- MODAL STRUK REKAP TAGIHAN PER PELANGGAN (KASAE, DLL) --}}
{{-- ============================================================= --}}
@php
    $rekapTotal = $transactions->sum('total_amount');
    $rekapOldest = $transactions->first() ? $transactions->first()->transaction_date : now();
    $rekapNewest = $transactions->last() ? $transactions->last()->transaction_date : now();
@endphp
<div class="modal fade" id="modalRekap_{{ $groupKey }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="fab fa-whatsapp me-2"></i> Rekap Struk Tagihan: {{ $customerName }}
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 bg-light text-center" style="max-height: 75vh; overflow-y: auto;">
                {{-- KERTAS STRUK TERMAL (80mm) YANG AKAN DICAPTURE KE WHATSAPP --}}
                <div class="rekap-receipt-card text-start mx-auto p-3 bg-white border shadow-sm" id="rekap-area-{{ $groupKey }}" style="max-width: 80mm; font-family: 'Courier New', Courier, monospace; font-size: 9.5pt; color: #111;">
                    <div class="text-center pb-2 mb-2 border-bottom border-2 border-dark">
                        <h5 class="fw-bold mb-0">ADIN CRYSTAL</h5>
                        <div class="small fw-semibold">Es Kristal Premium</div>
                        <div class="small text-muted" style="font-size: 8pt;">Telp: 087811192774</div>
                    </div>

                    <div class="text-center bg-danger text-white fw-bold py-1 mb-2 small rounded">
                        STRUK REKAP TAGIHAN KASBON
                    </div>

                    <div class="small mb-2" style="font-size: 9pt;">
                        <table class="w-100">
                            <tr>
                                <td style="width: 38%; font-weight: 600;">Pelanggan</td>
                                <td>: <strong>{{ $customerName }}</strong></td>
                            </tr>
                            @if($firstTx->customer && $firstTx->customer->phone)
                            <tr>
                                <td style="font-weight: 600;">No. WhatsApp</td>
                                <td>: {{ $firstTx->customer->phone }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td style="font-weight: 600;">Tgl Rekap</td>
                                <td>: {{ now()->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600;">Total Struk</td>
                                <td>: {{ $transactions->count() }} Transaksi Belum Lunas</td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600;">Periode</td>
                                <td>: {{ $rekapOldest->format('d/m/Y') }} s/d {{ $rekapNewest->format('d/m/Y') }}</td>
                            </tr>
                        </table>
                    </div>

                    <div class="py-2 my-2" style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; font-size: 8.5pt;">
                        <div class="fw-bold mb-1 text-center">--- RINCIAN TRANSAKSI TERTUNDA ---</div>
                        @foreach($transactions as $tIdx => $t)
                        <div class="mb-2 {{ !$loop->last ? 'border-bottom border-light pb-1' : '' }}">
                            <div class="d-flex justify-content-between fw-bold">
                                <span>{{ $tIdx + 1 }}. Struk #{{ str_pad($t->id, 6, '0', STR_PAD_LEFT) }}</span>
                                <span>{{ $t->transaction_date->format('d/m/Y H:i') }}</span>
                            </div>
                            @if($t->details && $t->details->isNotEmpty())
                                @foreach($t->details as $det)
                                <div class="d-flex justify-content-between text-muted" style="padding-left: 10px;">
                                    <span>• {{ $det->product->name ?? 'Produk' }} (x{{ $det->quantity }})</span>
                                    <span>Rp {{ number_format($det->total_price ?? ($det->quantity * $det->unit_price), 0, ',', '.') }}</span>
                                </div>
                                @endforeach
                            @endif
                            @if($t->notes)
                            <div class="text-muted fst-italic" style="font-size: 7.5pt; padding-left: 10px;">Ket: {{ $t->notes }}</div>
                            @endif
                            <div class="d-flex justify-content-between fw-semibold text-danger" style="padding-left: 10px;">
                                <span>Subtotal:</span>
                                <span>Rp {{ number_format($t->total_amount, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="pt-1">
                        <div class="d-flex justify-content-between align-items-center fs-6 fw-bold border-top border-2 border-dark pt-1">
                            <span>TOTAL TAGIHAN:</span>
                            <span class="text-danger fs-5">Rp {{ number_format($rekapTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="text-center text-danger border border-danger p-1 my-2 fw-bold small" style="border-style: dashed !important; font-size: 8.5pt;">
                        STATUS: BELUM LUNAS (KASBON)
                    </div>

                    <div class="text-center mt-2 text-muted" style="font-size: 8pt;">
                        Mohon kesediaannya untuk melakukan pembayaran.<br>
                        Dapat melalui Tunai / Transfer.<br>
                        Terima kasih atas kerjasamanya! 🙏
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex flex-column gap-2">
                <button type="button" class="btn btn-success fw-bold w-100 py-2 shadow-sm" id="btnShareStruk_{{ $groupKey }}" onclick="shareStrukRekapImage('{{ $groupKey }}', '{{ $customerName }}', '{{ $firstTx->customer->phone ?? '' }}')">
                    <i class="fas fa-share-alt me-1"></i> 📱 Bagikan Struk Tagihan (WhatsApp)
                </button>
                <div class="d-flex justify-content-between w-100 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-outline-success btn-sm fw-semibold" onclick="sendChatWAText('{{ $groupKey }}', '{{ $customerName }}', '{{ $firstTx->customer->phone ?? '' }}', {{ $rekapTotal }})">
                        <i class="fab fa-whatsapp me-1"></i> Kirim Chat Teks WA
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" onclick="printStrukRekap('rekap-area-{{ $groupKey }}')">
                        <i class="fas fa-print me-1"></i> Cetak Struk
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach
@endif

{{-- Script untuk fungsi Centang Semua (Select All) & WhatsApp Share Rekap Kasbon --}}
<!-- Panggil Library HTML2Canvas untuk Capture Gambar Struk -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    // Data rekap kasbon untuk WhatsApp
    const kasbonData = {
        @if(isset($kasbonByCustomer) && $kasbonByCustomer->isNotEmpty())
        @foreach($kasbonByCustomer as $gKey => $trxs)
        @php
            $cTx = $trxs->first();
            $cName = $cTx->customer->name ?? 'Pelanggan Umum (Tanpa Nama)';
            $cPhone = $cTx->customer->phone ?? '';
            $cTotal = $trxs->sum('total_amount');
        @endphp
        '{{ $gKey }}': {
            customerName: @json($cName),
            customerPhone: @json($cPhone),
            totalAmount: {{ $cTotal }},
            items: [
                @foreach($trxs as $tx)
                {
                    id: '{{ str_pad($tx->id, 6, "0", STR_PAD_LEFT) }}',
                    date: '{{ $tx->transaction_date->format("d/m/Y H:i") }}',
                    amount: {{ $tx->total_amount }},
                    notes: @json($tx->notes ?? ''),
                    products: [
                        @if($tx->details)
                            @foreach($tx->details as $d)
                            {
                                name: @json($d->product->name ?? 'Produk'),
                                quantity: {{ $d->quantity }}
                            },
                            @endforeach
                        @endif
                    ]
                },
                @endforeach
            ]
        },
        @endforeach
        @endif
    };

    /**
     * Bagikan Gambar Struk Rekap Akumulasi Tagihan Customer ke WhatsApp (HTML2Canvas)
     */
    function shareStrukRekapImage(groupKey, customerName, defaultPhone) {
        const btn = document.getElementById('btnShareStruk_' + groupKey);
        const originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.innerHTML = '⏳ Menyiapkan Struk Gambar...';
            btn.disabled = true;
        }

        const rekapArea = document.getElementById('rekap-area-' + groupKey);
        if (!rekapArea) {
            alert('Area struk tidak ditemukan!');
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
            return;
        }

        let phone = defaultPhone || '';
        if (!phone && kasbonData[groupKey] && kasbonData[groupKey].customerPhone) {
            phone = kasbonData[groupKey].customerPhone;
        }
        if (phone) {
            phone = phone.replace(/[^0-9]/g, '');
            if (phone.startsWith('0')) {
                phone = '62' + phone.substring(1);
            } else if (phone.startsWith('8')) {
                phone = '62' + phone;
            }
        }

        const shareTitle = 'Rekap Tagihan Kasbon - ' + customerName;
        const shareText = `Halo kak ${customerName}, berikut kami kirimkan rincian rekap struk tagihan belanja Anda di Adin Crystal yang belum terlunasi. Mohon kesediaannya untuk melakukan pelunasan. Terima kasih banyak 🙏`;

        html2canvas(rekapArea, {
            scale: 2,
            backgroundColor: "#ffffff",
            logging: false,
            useCORS: true
        }).then(canvas => {
            canvas.toBlob(function(blob) {
                const cleanName = customerName.replace(/[^a-zA-Z0-9]/g, '_');
                const fileName = `Rekap_Tagihan_${cleanName}_${Date.now()}.png`;
                const file = new File([blob], fileName, { type: 'image/png' });

                // Cek apakah browser mendukung Web Share API dengan File Gambar (Khusus Mobile / Browser Modern)
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    navigator.share({
                        files: [file],
                        title: shareTitle,
                        text: shareText
                    }).then(() => {
                        if (btn) {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    }).catch(err => {
                        console.log('Share dibatalkan:', err);
                        if (btn) {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    });
                } else {
                    // Fallback Desktop Browser: Download otomatis gambar & buka link WhatsApp
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = fileName;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);

                    let waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`;
                    if (phone) {
                        waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(shareText)}`;
                    }

                    alert(`Struk telah diunduh sebagai gambar (${fileName}). Anda akan dialihkan ke WhatsApp untuk mengirimkannya ke ${customerName}.`);
                    window.open(waUrl, '_blank');

                    if (btn) {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }
            }, 'image/png');
        }).catch(err => {
            console.error('Error saat membuat gambar struk:', err);
            alert('Gagal membuat gambar struk. Silakan gunakan tombol "Kirim Chat Teks WA".');
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    }

    /**
     * Cetak Struk Rekap Thermal ke Printer
     */
    function printStrukRekap(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;

        const printWindow = window.open('', '_blank', 'width=450,height=650');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Cetak Struk Rekap Kasbon</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @page { size: 80mm auto; margin: 2mm; }
                    body {
                        margin: 0;
                        padding: 6px;
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 9.5pt;
                        color: #000;
                        background: #fff;
                    }
                    .rekap-receipt-card {
                        width: 100% !important;
                        max-width: 80mm !important;
                        border: none !important;
                        box-shadow: none !important;
                        padding: 0 !important;
                    }
                </style>
            </head>
            <body>
                ${el.outerHTML}
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() { window.close(); }, 500);
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    /**
     * Kirim Teks Rincian Tagihan ke WhatsApp
     */
    function sendChatWAText(groupKey, customerName, defaultPhone, totalAmount) {
        shareCustomerKasbonWA(groupKey);
    }

    function shareCustomerKasbonWA(groupKey) {
        const data = kasbonData[groupKey];
        if (!data) return;

        let phone = data.customerPhone || '';
        if (!phone) {
            phone = prompt(`Masukkan nomor WhatsApp untuk ${data.customerName} (contoh: 08123456789):`, "");
            if (!phone) return;
        }

        // Format nomor agar standar internasional 62...
        phone = phone.replace(/[^0-9]/g, '');
        if (phone.startsWith('0')) {
            phone = '62' + phone.substring(1);
        } else if (phone.startsWith('8')) {
            phone = '62' + phone;
        }

        let msg = `*TAGIHAN KASBON - ADIN CRYSTAL*\n`;
        msg += `Yth. *${data.customerName}*,\n\n`;
        msg += `Berikut rincian tagihan pesanan Anda yang belum terselesaikan:\n`;
        msg += `----------------------------------------\n`;

        data.items.forEach((item, idx) => {
            msg += `${idx + 1}. *Struk #${item.id}* (${item.date})\n`;
            if (item.products && item.products.length > 0) {
                const prodList = item.products.map(p => `${p.name} (${p.quantity})`).join(', ');
                msg += `   Item: ${prodList}\n`;
            }
            if (item.notes) {
                msg += `   Ket: ${item.notes}\n`;
            }
            msg += `   Nominal: *Rp ${new Intl.NumberFormat('id-ID').format(item.amount)}*\n\n`;
        });

        msg += `----------------------------------------\n`;
        msg += `💰 *TOTAL TAGIHAN: Rp ${new Intl.NumberFormat('id-ID').format(data.totalAmount)}*\n\n`;
        msg += `Mohon kesediaannya untuk segera melakukan pelunasan via Tunai / Transfer.\n`;
        msg += `Terima kasih atas perhatian dan kerjasamanya! 🙏`;

        const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(msg)}`;

        if (navigator.share && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            navigator.share({
                title: 'Tagihan Kasbon ' + data.customerName,
                text: msg
            }).catch(() => {
                window.open(waUrl, '_blank');
            });
        } else {
            window.open(waUrl, '_blank');
        }
    }

    function shareGlobalKasbonWA() {
        if (!kasbonData || Object.keys(kasbonData).length === 0) {
            alert('Tidak ada data kasbon aktif untuk direkap.');
            return;
        }

        let msg = `🚨 *REKAP SELURUH PIUTANG KASBON* 🚨\n`;
        msg += `Tanggal: ${new Date().toLocaleDateString('id-ID')}\n`;
        msg += `Toko: *ADIN CRYSTAL*\n`;
        msg += `----------------------------------------\n\n`;

        let totalAll = 0;
        let count = 1;

        for (const key in kasbonData) {
            const item = kasbonData[key];
            totalAll += item.totalAmount;
            const phoneInfo = item.customerPhone ? ` (${item.customerPhone})` : '';
            msg += `${count}. *${item.customerName}*${phoneInfo}\n`;
            msg += `   • ${item.items.length} Struk tertunda\n`;
            msg += `   • Total: *Rp ${new Intl.NumberFormat('id-ID').format(item.totalAmount)}*\n\n`;
            count++;
        }

        msg += `----------------------------------------\n`;
        msg += `💰 *GRAND TOTAL PIUTANG: Rp ${new Intl.NumberFormat('id-ID').format(totalAll)}*\n\n`;
        msg += `Mohon bantuan tim untuk follow up penagihan. Terima kasih.`;

        const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(msg)}`;

        if (navigator.share && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            navigator.share({
                title: 'Rekap Seluruh Kasbon Adin Crystal',
                text: msg
            }).catch(() => {
                window.open(waUrl, '_blank');
            });
        } else {
            window.open(waUrl, '_blank');
        }
    }

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