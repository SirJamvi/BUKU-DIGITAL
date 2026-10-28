@extends('admin.layouts.app')

@section('title', 'Audit Bukti Transaksi Kasir')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Audit Bukti Kasir</li>
@endsection

@push('styles')
<style>
    .proof-thumbnail {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        border: 2px solid #e2e8f0;
    }
    .proof-thumbnail:hover {
        transform: scale(1.08);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-color: #3b82f6;
    }
    .nav-tabs .nav-link {
        font-weight: 600;
        color: #64748b;
        padding: 0.75rem 1.25rem;
    }
    .nav-tabs .nav-link.active {
        color: #0f172a;
        border-bottom: 3px solid #3b82f6;
    }
    .stat-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 1.25rem;
        transition: box-shadow 0.2s;
    }
    .stat-card:hover {
        box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    @include('components.alert')

    {{-- Header Banner & Filter Tanggal --}}
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">
                        <i class="fas fa-clipboard-check text-primary me-2"></i>Audit Bukti Transaksi Kasir
                    </h4>
                    <p class="text-muted mb-0 small">
                        Pemeriksaan digital bukti nota pengeluaran fisik dan screenshot transfer WhatsApp customer untuk pelunasan non-tunai.
                    </p>
                </div>
                <div>
                    {{-- Quick Date Filter Buttons --}}
                    <div class="btn-group shadow-sm" role="group">
                        <a href="{{ route('admin.proof-audit.index', ['quick_date' => 'today', 'tab' => $activeTab]) }}" 
                           class="btn btn-sm {{ $quickDate === 'today' ? 'btn-primary fw-bold' : 'btn-outline-secondary' }}">
                            <i class="fas fa-calendar-day me-1"></i> Hari Ini
                        </a>
                        <a href="{{ route('admin.proof-audit.index', ['quick_date' => 'yesterday', 'tab' => $activeTab]) }}" 
                           class="btn btn-sm {{ $quickDate === 'yesterday' ? 'btn-primary fw-bold' : 'btn-outline-secondary' }}">
                            <i class="fas fa-history me-1"></i> Kemarin
                        </a>
                        <a href="{{ route('admin.proof-audit.index', ['quick_date' => 'last_7_days', 'tab' => $activeTab]) }}" 
                           class="btn btn-sm {{ $quickDate === 'last_7_days' ? 'btn-primary fw-bold' : 'btn-outline-secondary' }}">
                            <i class="fas fa-calendar-week me-1"></i> 7 Hari Terakhir
                        </a>
                    </div>
                </div>
            </div>

            <hr class="my-3 text-muted opacity-25">

            {{-- Form Filter Tanggal Custom --}}
            <form action="{{ route('admin.proof-audit.index') }}" method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="quick_date" value="custom">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                
                <div class="col-auto">
                    <span class="small fw-bold text-muted"><i class="fas fa-filter me-1"></i>Rentang Tanggal:</span>
                </div>
                <div class="col-auto">
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" required>
                </div>
                <div class="col-auto text-muted">s/d</div>
                <div class="col-auto">
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-dark">
                        <i class="fas fa-search me-1"></i> Tampilkan
                    </button>
                </div>
                @if($quickDate === 'custom')
                <div class="col-auto">
                    <a href="{{ route('admin.proof-audit.index') }}" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
                @endif
            </form>
        </div>
    </div>

    {{-- Ringkasan Statistik --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: Pengeluaran --}}
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Total Pengeluaran (Expenses)</span>
                        <h4 class="fw-bold mb-0 text-danger mt-1">Rp {{ number_format($totalExpensesAmount, 0, ',', '.') }}</h4>
                    </div>
                    <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-3">
                        <i class="fas fa-receipt fa-lg"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    <i class="fas fa-check-circle text-success me-1"></i><strong>{{ $expensesWithProofCount }}</strong> dari {{ $totalExpensesCount }} pengeluaran memiliki bukti nota.
                </div>
            </div>
        </div>

        {{-- Card 2: Pelunasan Non-Tunai --}}
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Pelunasan Non-Tunai (Transfer/DANA)</span>
                        <h4 class="fw-bold mb-0 text-success mt-1">Rp {{ number_format($totalPaymentsAmount, 0, ',', '.') }}</h4>
                    </div>
                    <div class="p-2 bg-success bg-opacity-10 text-success rounded-3">
                        <i class="fab fa-whatsapp fa-lg"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    <i class="fas fa-layer-group text-primary me-1"></i><strong>{{ $totalPaymentBatchesCount ?? $totalPaymentsCount }}</strong> Transfer Mutasi (<strong>{{ $totalPaymentsCount }}</strong> Nota).
                </div>
            </div>
        </div>

        {{-- Card 3: Status Perhatian --}}
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Menunggu Audit Admin</span>
                        <h4 class="fw-bold mb-0 text-warning mt-1">{{ $unverifiedTotal }} Bukti</h4>
                    </div>
                    <div class="p-2 bg-warning bg-opacity-10 text-warning rounded-3">
                        <i class="fas fa-user-check fa-lg"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    Bukti kasir yang belum ditandai cocok/diverifikasi oleh admin.
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Section --}}
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs border-bottom-0 px-3 pt-2" id="proofTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'all' || $activeTab === 'expenses' ? 'active' : '' }}" 
                            id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expenses-pane" type="button" role="tab">
                        <i class="fas fa-receipt me-1 text-danger"></i> Bukti Pengeluaran Kasir (Expenses)
                        <span class="badge bg-danger ms-1">{{ $totalExpensesCount }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'payments' ? 'active' : '' }}" 
                            id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments-pane" type="button" role="tab">
                        <i class="fab fa-whatsapp me-1 text-success"></i> Bukti Pelunasan Non-Tunai WA
                        <span class="badge bg-success ms-1">{{ ($viewMode ?? 'grouped') === 'grouped' ? ($totalPaymentBatchesCount ?? $totalPaymentsCount) . ' Transfer' : $totalPaymentsCount . ' Nota' }}</span>
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="proofTabsContent">
                
                {{-- TAB 1: BUKTI PENGELUARAN (EXPENSES) --}}
                <div class="tab-pane fade {{ $activeTab === 'all' || $activeTab === 'expenses' ? 'show active' : '' }}" id="expenses-pane" role="tabpanel">
                    
                    {{-- Filter Opsi Tab Pengeluaran --}}
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="text-muted small">
                            Menampilkan data pengeluaran tanggal: <strong>{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</strong> s/d <strong>{{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</strong>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <a href="{{ request()->fullUrlWithQuery(['expense_filter' => 'all', 'tab' => 'expenses']) }}" 
                               class="btn {{ !request('expense_filter') || request('expense_filter') === 'all' ? 'btn-secondary' : 'btn-outline-secondary' }}">Semua</a>
                            <a href="{{ request()->fullUrlWithQuery(['expense_filter' => 'with_proof', 'tab' => 'expenses']) }}" 
                               class="btn {{ request('expense_filter') === 'with_proof' ? 'btn-primary' : 'btn-outline-secondary' }}">Hanya Ber-Nota</a>
                            <a href="{{ request()->fullUrlWithQuery(['expense_filter' => 'without_proof', 'tab' => 'expenses']) }}" 
                               class="btn {{ request('expense_filter') === 'without_proof' ? 'btn-warning' : 'btn-outline-secondary' }}">Tanpa Nota</a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">Bukti Nota</th>
                                    <th>Tanggal & Jam</th>
                                    <th>Kategori</th>
                                    <th>Sumber Dana</th>
                                    <th>Kasir</th>
                                    <th>Deskripsi</th>
                                    <th class="text-end">Nominal</th>
                                    <th class="text-center" style="width: 140px;">Status Audit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($expenses as $expense)
                                <tr>
                                    <td>
                                        @if(!empty($expense->receipt_image))
                                            @php
                                                $ext = strtolower(pathinfo($expense->receipt_image, PATHINFO_EXTENSION));
                                            @endphp
                                            @if($ext === 'pdf')
                                                <a href="{{ asset($expense->receipt_image) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="Buka Dokumen PDF">
                                                    <i class="fas fa-file-pdf fa-2x"></i>
                                                </a>
                                            @else
                                                <img src="{{ asset($expense->receipt_image) }}" 
                                                     alt="Nota" 
                                                     class="proof-thumbnail" 
                                                     onclick="showLightbox('{{ asset($expense->receipt_image) }}', 'Nota Pengeluaran #{{ $expense->id }} - Rp {{ number_format($expense->amount, 0, ',', '.') }}', '{{ $expense->category->name ?? 'Operasional' }} | {{ $expense->description }}')">
                                            @endif
                                        @else
                                            <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">
                                                <i class="fas fa-times-circle text-danger me-1"></i>Tanpa Nota
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold">{{ $expense->date ? $expense->date->format('d/m/Y') : '-' }}</span><br>
                                        <small class="text-muted">{{ $expense->created_at ? $expense->created_at->format('H:i') : '' }} WIB</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ $expense->category->name ?? 'Pengeluaran' }}</span>
                                        @if($expense->is_cogs)
                                            <span class="badge bg-warning text-dark" style="font-size: 0.68rem;">HPP</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($expense->funding_source === 'drawer')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.75rem;">
                                                <i class="fas fa-cash-register me-1"></i>Laci Kasir
                                            </span>
                                        @else
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.75rem;">
                                                <i class="fas fa-wallet me-1"></i>Uang Bos / Luar
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="small fw-semibold text-dark">
                                            <i class="fas fa-user text-muted me-1"></i>{{ $expense->createdBy->name ?? 'Kasir' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="small text-muted">{{ $expense->description }}</span>
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        Rp {{ number_format($expense->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="text-center">
                                        @if($expense->proof_verified_at)
                                            <span class="badge bg-success text-white mb-1" style="font-size: 0.75rem;" title="Diverifikasi: {{ $expense->proof_verified_at->format('d/m/Y H:i') }}">
                                                <i class="fas fa-check-double me-1"></i> Terverifikasi
                                            </span>
                                            <form action="{{ route('admin.proof-audit.unverify-expense', $expense->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-link text-danger p-0 d-block mx-auto" style="font-size: 0.68rem;" title="Batalkan Verifikasi">
                                                    Batalkan
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('admin.proof-audit.verify-expense', $expense->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2" style="font-size: 0.75rem;">
                                                    <i class="fas fa-check me-1"></i> Verifikasi
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                        Tidak ada catatan pengeluaran pada rentang tanggal ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($expenses->hasPages())
                    <div class="mt-3">
                        {{ $expenses->links('components.pagination') }}
                    </div>
                    @endif

                </div>

                {{-- TAB 2: BUKTI PELUNASAN NON-TUNAI WA (TRANSACTIONS) --}}
                <div class="tab-pane fade {{ $activeTab === 'payments' ? 'show active' : '' }}" id="payments-pane" role="tabpanel">
                    
                    {{-- Filter Opsi & Mode Tampilan Tab Pelunasan --}}
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="text-muted small">
                            Menampilkan transaksi pelunasan non-tunai tanggal: <strong>{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</strong> s/d <strong>{{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</strong>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            {{-- View Mode Toggle --}}
                            <div class="btn-group btn-group-sm border rounded shadow-xs bg-white p-1" role="group">
                                <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'grouped', 'tab' => 'payments']) }}" 
                                   class="btn btn-sm {{ ($viewMode ?? 'grouped') === 'grouped' ? 'btn-primary fw-bold' : 'btn-light text-muted' }}"
                                   title="Rangkum transaksi per Bukti Transfer / Mutasi Rekening (Rapi & Tidak Menumpuk)">
                                    <i class="fas fa-layer-group me-1"></i> Rangkuman Transfer (Ringkas)
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'detailed', 'tab' => 'payments']) }}" 
                                   class="btn btn-sm {{ ($viewMode ?? 'grouped') === 'detailed' ? 'btn-primary fw-bold' : 'btn-light text-muted' }}"
                                   title="Tampilkan seluruh nota satuan satu per satu">
                                    <i class="fas fa-list me-1"></i> Seluruh Nota Satuan (Rinci)
                                </a>
                            </div>

                            {{-- Filter Bukti --}}
                            <div class="btn-group btn-group-sm">
                                <a href="{{ request()->fullUrlWithQuery(['payment_filter' => 'all', 'tab' => 'payments']) }}" 
                                   class="btn {{ !request('payment_filter') || request('payment_filter') === 'all' ? 'btn-secondary' : 'btn-outline-secondary' }}">Semua</a>
                                <a href="{{ request()->fullUrlWithQuery(['payment_filter' => 'with_proof', 'tab' => 'payments']) }}" 
                                   class="btn {{ request('payment_filter') === 'with_proof' ? 'btn-primary' : 'btn-outline-secondary' }}">Hanya Ada Bukti WA</a>
                                <a href="{{ request()->fullUrlWithQuery(['payment_filter' => 'without_proof', 'tab' => 'payments']) }}" 
                                   class="btn {{ request('payment_filter') === 'without_proof' ? 'btn-warning' : 'btn-outline-secondary' }}">Belum Ada Bukti</a>
                            </div>
                        </div>
                    </div>

                    @if(($viewMode ?? 'grouped') === 'grouped')
                        {{-- ========================================================================= --}}
                        {{-- MODE RANGKUMAN PER TRANSFER (RINGKAS - DEFAULT):                          --}}
                        {{-- 1 Baris Utama = 1 Bukti Transfer WA / Mutasi Rekening Bank/DANA            --}}
                        {{-- ========================================================================= --}}
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Bukti WA</th>
                                        <th>No. Struk / Batch</th>
                                        <th>Waktu Transaksi</th>
                                        <th>Pelanggan</th>
                                        <th>Kasir</th>
                                        <th>Metode Bayar</th>
                                        <th>Catatan / Keterangan</th>
                                        <th class="text-end">Total Mutasi Bank</th>
                                        <th class="text-center" style="width: 140px;">Cocok Mutasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payments as $batch)
                                        @php
                                            $batchKey = $batch->batch_key;
                                            $items = ($loadedTransactions ?? collect())->get($batchKey, collect());
                                            $firstTx = $items->first();
                                            $batchCount = $batch->batch_count;
                                            $isBulk = $batchCount > 1;
                                            $totalAmount = $batch->total_batch_amount;
                                            $isVerified = !empty($batch->batch_verified_at);
                                            $hash = md5($batchKey);
                                            
                                            $uniqueCustomers = $items->pluck('customer.name')->filter()->unique();
                                            $customerName = $uniqueCustomers->first() ?? ($firstTx->customer->name ?? 'Pelanggan Umum');
                                            $customerPhone = $firstTx->customer->phone ?? null;
                                            
                                            $uniqueCashiers = $items->pluck('createdBy.name')->filter()->unique()->values();
                                            $cashierNames = $uniqueCashiers->take(2)->implode(', ') . ($uniqueCashiers->count() > 2 ? ' +' . ($uniqueCashiers->count() - 2) : '');
                                            $bulkInvoiceList = $items->map(fn($item) => '#' . str_pad($item->id, 6, '0', STR_PAD_LEFT))->implode(', ');
                                        @endphp
                                        <tr>
                                            <td>
                                                @if($firstTx && !empty($firstTx->payment_proof))
                                                    <div class="position-relative d-inline-block">
                                                        <img src="{{ asset($firstTx->payment_proof) }}" 
                                                             alt="Bukti Transfer" 
                                                             class="proof-thumbnail" 
                                                             onclick="showLightbox('{{ asset($firstTx->payment_proof) }}', '{{ addslashes($isBulk ? "Bukti Transfer Bulk ({$batchCount} Nota) - Total: Rp " . number_format($totalAmount, 0, ',', '.') : "Bukti Transfer Transaksi #" . str_pad($firstTx->id, 6, "0", STR_PAD_LEFT) . " - Rp " . number_format($totalAmount, 0, ',', '.')) }}', '{{ addslashes($isBulk ? "Melunasi Nota: {$bulkInvoiceList} | Pelanggan: {$customerName} | " . strtoupper($firstTx->payment_method ?? "TRANSFER") : "Pelanggan: {$customerName} | Metode: " . strtoupper($firstTx->payment_method ?? "TRANSFER")) }}')">
                                                        @if($isBulk)
                                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark border border-white shadow-sm" style="font-size: 0.65rem;" title="{{ $batchCount }} Transaksi Dilunasi Sekaligus">
                                                                {{ $batchCount }}x
                                                            </span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">
                                                        <i class="fas fa-exclamation-circle text-warning me-1"></i>Belum Ada
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($firstTx)
                                                    <a href="{{ route('admin.transactions.show', $firstTx->id) }}" class="fw-bold text-primary text-decoration-none">
                                                        #{{ str_pad($firstTx->id, 6, '0', STR_PAD_LEFT) }}
                                                    </a>
                                                @endif
                                                @if($isBulk)
                                                    <span class="small text-muted">+ {{ $batchCount - 1 }} Nota</span>
                                                    <div class="mt-1">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" 
                                                                style="font-size: 0.72rem; border-radius: 6px;"
                                                                data-bs-toggle="collapse" 
                                                                data-bs-target="#batch_collapse_{{ $hash }}"
                                                                aria-expanded="false"
                                                                title="Klik untuk membuka/menutup rincian seluruh nota dalam transfer ini">
                                                            <i class="fas fa-layer-group text-warning"></i>
                                                            <span>Rincian {{ $batchCount }} Nota</span>
                                                            <i class="fas fa-chevron-down fa-xs ms-1"></i>
                                                        </button>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="fw-semibold">{{ \Carbon\Carbon::parse($batch->batch_updated_at)->format('d/m/Y') }}</span><br>
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($batch->batch_updated_at)->format('H:i') }} WIB</small>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $customerName }}</div>
                                                @if($customerPhone)
                                                    <small class="text-muted"><i class="fab fa-whatsapp text-success me-1"></i>{{ $customerPhone }}</small>
                                                @endif
                                                @if($uniqueCustomers->count() > 1)
                                                    <span class="badge bg-light text-secondary border mt-1" style="font-size: 0.68rem;">+ {{ $uniqueCustomers->count() - 1 }} Pelanggan Lain</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="small fw-semibold text-dark">
                                                    <i class="fas fa-user text-muted me-1"></i>{{ $cashierNames ?: 'Kasir' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary text-white">
                                                    {{ strtoupper($firstTx->payment_method ?? 'NON-TUNAI') }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($isBulk)
                                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle mb-1" style="font-size: 0.72rem;">
                                                        <i class="fas fa-check-circle me-1"></i>Pelunasan Bulk ({{ $batchCount }} Nota)
                                                    </span>
                                                    @php
                                                        $noteItems = $items->pluck('notes')->filter()->values();
                                                    @endphp
                                                    @if($noteItems->isNotEmpty())
                                                        <div class="small text-muted text-truncate" style="max-width: 200px;" title="{{ $noteItems->implode('; ') }}">
                                                            {{ $noteItems->first() }}
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="small text-muted">{{ $firstTx->notes ?? '-' }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="fw-bold text-success fs-6">
                                                    Rp {{ number_format($totalAmount, 0, ',', '.') }}
                                                </div>
                                                @if($isBulk)
                                                    <div class="small fw-semibold text-primary mt-1" style="font-size: 0.70rem;" title="Total mutasi uang masuk pada rekening bank yang dilunasi sekaligus">
                                                        <i class="fas fa-money-bill-wave me-1"></i>Total Mutasi Bank
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($firstTx)
                                                    @if($isVerified)
                                                        <span class="badge bg-success text-white mb-1" style="font-size: 0.75rem;" title="Cocok Mutasi: {{ $batch->batch_verified_at ? \Carbon\Carbon::parse($batch->batch_verified_at)->format('d/m/Y H:i') : '' }}">
                                                            <i class="fas fa-check-double me-1"></i> Cocok Mutasi
                                                        </span>
                                                        <form action="{{ route('admin.proof-audit.unverify-payment', $firstTx->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-link text-danger p-0 d-block mx-auto" style="font-size: 0.68rem;" title="Batalkan Verifikasi Seluruh Batch">
                                                                Batalkan
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form action="{{ route('admin.proof-audit.verify-payment', $firstTx->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold" style="font-size: 0.75rem;" title="Verifikasi Sesuai Mutasi Bank (Meliputi {{ $batchCount }} Nota)">
                                                                <i class="fas fa-check me-1"></i> Cocok Mutasi
                                                            </button>
                                                        </form>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>

                                        {{-- INLINE COLLAPSIBLE ACCORDION UNTUK RINCIAN NOTA DALAM BATCH INI --}}
                                        @if($isBulk)
                                        <tr class="collapse" id="batch_collapse_{{ $hash }}">
                                            <td colspan="9" class="p-3 bg-light border-start border-4 border-primary">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-bold small text-dark">
                                                        <i class="fas fa-layer-group text-primary me-1"></i>
                                                        Rincian {{ $batchCount }} Nota untuk Transfer Mutasi Ini (Total: <span class="text-success">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>)
                                                    </span>
                                                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" data-bs-toggle="collapse" data-bs-target="#batch_collapse_{{ $hash }}" style="font-size: 0.72rem;">
                                                        <i class="fas fa-times me-1"></i>Tutup Rincian
                                                    </button>
                                                </div>
                                                <div class="table-responsive rounded border bg-white shadow-sm">
                                                    <table class="table table-sm table-striped table-hover align-middle mb-0" style="font-size: 0.80rem;">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th class="ps-3" style="width: 110px;">No. Struk</th>
                                                                <th style="width: 140px;">Waktu Transaksi</th>
                                                                <th style="width: 130px;">Kasir</th>
                                                                <th>Catatan / Pengiriman</th>
                                                                <th class="text-end pe-3" style="width: 140px;">Nominal Nota</th>
                                                                <th class="text-center" style="width: 80px;">Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($items as $item)
                                                            <tr>
                                                                <td class="ps-3 fw-bold">
                                                                    <a href="{{ route('admin.transactions.show', $item->id) }}" target="_blank" class="text-primary text-decoration-none">
                                                                        #{{ str_pad($item->id, 6, '0', STR_PAD_LEFT) }} <i class="fas fa-external-link-alt fa-xs text-muted"></i>
                                                                    </a>
                                                                </td>
                                                                <td>{{ $item->transaction_date ? $item->transaction_date->format('d/m/Y H:i') : '-' }} WIB</td>
                                                                <td>{{ $item->createdBy->name ?? '-' }}</td>
                                                                <td class="text-muted">{{ $item->notes ?? '-' }}</td>
                                                                <td class="text-end pe-3 fw-bold text-success">
                                                                    Rp {{ number_format($item->total_amount, 0, ',', '.') }}
                                                                </td>
                                                                <td class="text-center">
                                                                    <a href="{{ route('admin.transactions.show', $item->id) }}" target="_blank" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.70rem;">
                                                                        Buka
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                        <tfoot class="table-light fw-bold">
                                                            <tr>
                                                                <td colspan="4" class="ps-3 text-end text-dark">Total Seluruh {{ $batchCount }} Nota:</td>
                                                                <td class="text-end pe-3 text-success fs-6">Rp {{ number_format($totalAmount, 0, ',', '.') }}</td>
                                                                <td></td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif

                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                            Tidak ada transaksi pelunasan non-tunai pada rentang tanggal ini.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        {{-- ========================================================================= --}}
                        {{-- MODE RINCI PER NOTA SATUAN:                                               --}}
                        {{-- Menampilkan seluruh nota transaksi satu per satu secara terpisah          --}}
                        {{-- ========================================================================= --}}
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Bukti WA</th>
                                        <th>No. Struk</th>
                                        <th>Waktu Transaksi</th>
                                        <th>Pelanggan</th>
                                        <th>Kasir</th>
                                        <th>Metode Bayar</th>
                                        <th>Catatan</th>
                                        <th class="text-end">Total Pelunasan</th>
                                        <th class="text-center" style="width: 140px;">Cocok Mutasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payments as $tx)
                                    @php
                                        $bulkGroup = ($tx->payment_proof && isset($bulkGroups[$tx->payment_proof])) ? $bulkGroups[$tx->payment_proof] : collect([$tx]);
                                        $bulkCount = $bulkGroup->count();
                                        $isBulk = $bulkCount > 1;
                                        $bulkTotalAmount = $bulkGroup->sum('total_amount');
                                        $bulkHash = md5($tx->payment_proof ?? $tx->id);
                                        $bulkInvoiceList = $bulkGroup->map(fn($item) => '#' . str_pad($item->id, 6, '0', STR_PAD_LEFT))->implode(', ');
                                    @endphp
                                    <tr>
                                        <td>
                                            @if(!empty($tx->payment_proof))
                                                <div class="position-relative d-inline-block">
                                                    <img src="{{ asset($tx->payment_proof) }}" 
                                                         alt="Bukti Transfer" 
                                                         class="proof-thumbnail" 
                                                         onclick="showLightbox('{{ asset($tx->payment_proof) }}', '{{ addslashes($isBulk ? "Bukti Transfer Bulk ({$bulkCount} Nota) - Total: Rp " . number_format($bulkTotalAmount, 0, ',', '.') : "Bukti Transfer Transaksi #{$tx->id} - Rp " . number_format($tx->total_amount, 0, ',', '.')) }}', '{{ addslashes($isBulk ? "Melunasi Nota: {$bulkInvoiceList} | Pelanggan: " . ($tx->customer->name ?? 'Umum') . " | " . strtoupper($tx->payment_method) : "Pelanggan: " . ($tx->customer->name ?? 'Umum') . " | Metode: " . strtoupper($tx->payment_method)) }}')">
                                                    @if($isBulk)
                                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark border border-white shadow-sm" style="font-size: 0.65rem;" title="{{ $bulkCount }} Transaksi Dilunasi Sekaligus">
                                                            {{ $bulkCount }}x
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">
                                                    <i class="fas fa-exclamation-circle text-warning me-1"></i>Belum Ada
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.transactions.show', $tx->id) }}" class="fw-bold text-primary text-decoration-none">
                                                #{{ str_pad($tx->id, 6, '0', STR_PAD_LEFT) }}
                                            </a>
                                            @if($isBulk)
                                                <div class="mt-1">
                                                    <button type="button" 
                                                            class="btn btn-warning text-dark py-0 px-2 fw-bold shadow-sm d-inline-flex align-items-center gap-1" 
                                                            style="font-size: 0.70rem; border-radius: 6px;"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#bulkDetailModal_{{ $bulkHash }}"
                                                            title="Klik untuk melihat seluruh transaksi yang dilunasi dengan transfer ini">
                                                        <i class="fas fa-layer-group"></i>
                                                        <span>Bulk ({{ $bulkCount }} Nota)</span>
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-semibold">{{ $tx->transaction_date ? $tx->transaction_date->format('d/m/Y') : '-' }}</span><br>
                                            <small class="text-muted">{{ $tx->transaction_date ? $tx->transaction_date->format('H:i') : '' }} WIB</small>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $tx->customer->name ?? 'Pelanggan Umum' }}</div>
                                            @if($tx->customer && $tx->customer->phone)
                                                <small class="text-muted"><i class="fab fa-whatsapp text-success me-1"></i>{{ $tx->customer->phone }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="small fw-semibold text-dark">
                                                <i class="fas fa-user text-muted me-1"></i>{{ $tx->createdBy->name ?? 'Kasir' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary text-white">
                                                {{ strtoupper($tx->payment_method) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="small text-muted">{{ $tx->notes ?? '-' }}</span>
                                        </td>
                                        <td class="text-end">
                                            <div class="fw-bold text-success">
                                                Rp {{ number_format($tx->total_amount, 0, ',', '.') }}
                                            </div>
                                            @if($isBulk)
                                                <div class="small fw-semibold text-primary mt-1" style="font-size: 0.72rem;" title="Total uang transfer gabungan yang harus dicari di mutasi rekening bank / DANA">
                                                    <i class="fas fa-receipt me-1"></i>Total Bulk: <strong>Rp {{ number_format($bulkTotalAmount, 0, ',', '.') }}</strong>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($tx->proof_verified_at)
                                                <span class="badge bg-success text-white mb-1" style="font-size: 0.75rem;" title="Cocok Mutasi: {{ $tx->proof_verified_at->format('d/m/Y H:i') }}">
                                                    <i class="fas fa-check-double me-1"></i> Cocok Mutasi
                                                </span>
                                                <form action="{{ route('admin.proof-audit.unverify-payment', $tx->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-link text-danger p-0 d-block mx-auto" style="font-size: 0.68rem;" title="Batalkan Verifikasi">
                                                        Batalkan
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('admin.proof-audit.verify-payment', $tx->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2" style="font-size: 0.75rem;" title="Verifikasi dengan Mutasi Bank">
                                                        <i class="fas fa-check me-1"></i> Cocok Mutasi
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                            Tidak ada transaksi pelunasan non-tunai pada rentang tanggal ini.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if($payments->hasPages())
                    <div class="mt-3">
                        {{ $payments->links('components.pagination') }}
                    </div>
                    @endif

                </div>

            </div>
        </div>
    </div>
</div>

{{-- MODAL DETAIL TRANSAKSI BULK --}}
@if(isset($bulkGroups) && $bulkGroups->isNotEmpty())
    @foreach($bulkGroups as $proofKey => $group)
        @if($group->count() > 1)
            @php
                $groupHash = md5($proofKey);
                $groupTotal = $group->sum('total_amount');
                $firstItem = $group->first();
            @endphp
            <div class="modal fade" id="bulkDetailModal_{{ $groupHash }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content shadow border-0" style="border-radius: 14px;">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title fs-6">
                                <i class="fas fa-layer-group text-warning me-2"></i> Rincian Pembayaran Sekaligus (Bulk Settlement)
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            {{-- Banner Informasi Mutasi --}}
                            <div class="alert alert-primary bg-primary bg-opacity-10 border-primary text-dark py-3 px-3 mb-3 rounded-3">
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-6">
                                        <div class="small text-muted text-uppercase fw-bold">Pelanggan:</div>
                                        <div class="fw-bold fs-6 text-dark">{{ $firstItem->customer->name ?? 'Pelanggan Umum' }}</div>
                                        @if($firstItem->customer && $firstItem->customer->phone)
                                            <div class="small text-muted"><i class="fab fa-whatsapp text-success me-1"></i>{{ $firstItem->customer->phone }}</div>
                                        @endif
                                    </div>
                                    <div class="col-sm-6 text-sm-end">
                                        <div class="small text-muted text-uppercase fw-bold">Nominal Mutasi Bank/E-Wallet yang Dicari:</div>
                                        <div class="fw-bold fs-4 text-success">Rp {{ number_format($groupTotal, 0, ',', '.') }}</div>
                                        <span class="badge bg-primary text-white">{{ strtoupper($firstItem->payment_method) }}</span>
                                        <span class="badge bg-warning text-dark fw-bold">{{ $group->count() }} Nota Digabung</span>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                {{-- Preview Gambar Bukti Transfer --}}
                                <div class="col-md-5 text-center">
                                    <div class="p-2 border rounded bg-light mb-2">
                                        <img src="{{ asset($firstItem->payment_proof) }}" 
                                             alt="Bukti Transfer WA" 
                                             class="img-fluid rounded shadow-sm" 
                                             style="max-height: 280px; object-fit: contain; cursor: pointer;"
                                             onclick="showLightbox('{{ asset($firstItem->payment_proof) }}', 'Bukti Transfer Bulk ({{ $group->count() }} Transaksi) - Total: Rp {{ number_format($groupTotal, 0, ',', '.') }}', '{{ $firstItem->customer->name ?? 'Umum' }}')">
                                    </div>
                                    <small class="text-muted"><i class="fas fa-search-plus me-1"></i> Klik gambar untuk memperbesar</small>
                                </div>

                                {{-- Tabel Transaksi dalam Batch ini --}}
                                <div class="col-md-7">
                                    <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-list-check text-primary me-2"></i>Daftar Nota dalam Bukti Transfer ini:</h6>
                                    <div class="table-responsive border rounded" style="max-height: 280px; overflow-y: auto;">
                                        <table class="table table-sm table-striped align-middle mb-0" style="font-size: 0.82rem;">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th>No. Struk</th>
                                                    <th>Tgl Nota</th>
                                                    <th>Catatan</th>
                                                    <th class="text-end">Nominal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($group as $item)
                                                <tr>
                                                    <td class="fw-bold">
                                                        <a href="{{ route('admin.transactions.show', $item->id) }}" target="_blank" class="text-decoration-none">
                                                            #{{ str_pad($item->id, 6, '0', STR_PAD_LEFT) }} <i class="fas fa-external-link-alt fa-xs"></i>
                                                        </a>
                                                    </td>
                                                    <td>{{ $item->transaction_date ? $item->transaction_date->format('d/m/Y') : '-' }}</td>
                                                    <td class="text-muted">{{ Str::limit($item->notes ?? '-', 25) }}</td>
                                                    <td class="text-end fw-bold text-success">Rp {{ number_format($item->total_amount, 0, ',', '.') }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="table-light fw-bold">
                                                <tr>
                                                    <td colspan="3" class="text-end">Total Seluruh Nota:</td>
                                                    <td class="text-end text-success">Rp {{ number_format($groupTotal, 0, ',', '.') }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light justify-content-between">
                            <div class="small text-muted">
                                <i class="fas fa-info-circle text-primary me-1"></i> Kasir Pemroses: <strong>{{ $firstItem->createdBy->name ?? 'Kasir' }}</strong>
                            </div>
                            <div>
                                @if(!$firstItem->proof_verified_at)
                                <form action="{{ route('admin.proof-audit.verify-payment', $firstItem->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm fw-bold">
                                        <i class="fas fa-check-double me-1"></i> Cocok Mutasi Seluruh Nota (Rp {{ number_format($groupTotal, 0, ',', '.') }})
                                    </button>
                                </form>
                                @endif
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endif

{{-- MODAL LIGHTBOX INTERAKTIF UNTUK PREVIEW BUKTI RESOLUSI TINGGI --}}
<div class="modal fade" id="proofLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <div>
                    <h5 class="modal-title mb-0" id="lightboxTitle">Preview Bukti</h5>
                    <small class="text-muted" id="lightboxSubtitle"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-3 bg-secondary bg-opacity-10" style="min-height: 400px; max-height: 80vh; overflow: auto;">
                <img id="lightboxImage" src="" alt="Bukti Transaksi" class="img-fluid rounded shadow-sm" style="max-height: 70vh; transition: transform 0.3s;">
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="rotateLightboxImage()">
                        <i class="fas fa-redo me-1"></i> Putar (Rotate)
                    </button>
                    <a id="lightboxOpenLink" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i> Buka Ukuran Asli
                    </a>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentRotation = 0;

    function showLightbox(imageUrl, title, subtitle) {
        currentRotation = 0;
        const img = document.getElementById('lightboxImage');
        img.src = imageUrl;
        img.style.transform = 'rotate(0deg)';
        document.getElementById('lightboxTitle').textContent = title || 'Bukti Transaksi';
        document.getElementById('lightboxSubtitle').textContent = subtitle || '';
        document.getElementById('lightboxOpenLink').href = imageUrl;

        const modal = new bootstrap.Modal(document.getElementById('proofLightboxModal'));
        modal.show();
    }

    function rotateLightboxImage() {
        currentRotation = (currentRotation + 90) % 360;
        document.getElementById('lightboxImage').style.transform = `rotate(${currentRotation}deg)`;
    }
</script>
@endsection
