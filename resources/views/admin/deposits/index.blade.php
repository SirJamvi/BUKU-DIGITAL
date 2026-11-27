@extends('admin.layouts.app')

@section('title', 'Deposit Pelanggan')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Pelanggan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Deposit Pelanggan</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    @include('components.alert')

    {{-- 4 KPI SUMMARY CARDS --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #0d9488 !important; border-radius: 10px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Total Saldo Deposit Aktif</span>
                        <span class="badge bg-info bg-opacity-10 text-info p-2 rounded-circle"><i class="fas fa-wallet"></i></span>
                    </div>
                    <h3 class="fw-bold mb-1" style="color: #0f766e;">Rp {{ number_format($totalActiveDepositBalance, 0, ',', '.') }}</h3>
                    <p class="card-text text-muted small mb-0">{{ number_format($customersWithDepositCount) }} pelanggan memiliki saldo aktif.</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #10b981 !important; border-radius: 10px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Setoran Aktif (Filter)</span>
                        <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-circle"><i class="fas fa-arrow-down"></i></span>
                    </div>
                    <h3 class="fw-bold text-success mb-1">+Rp {{ number_format($periodCreditTotal, 0, ',', '.') }}</h3>
                    <p class="card-text text-muted small mb-0">Akumulasi setoran valid (non-void).</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #3b82f6 !important; border-radius: 10px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Terpakai Belanja / Kasbon</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-circle"><i class="fas fa-receipt"></i></span>
                    </div>
                    <h3 class="fw-bold text-primary mb-1">Rp {{ number_format($periodDebitTotal, 0, ',', '.') }}</h3>
                    <p class="card-text text-muted small mb-0">Deposit yang sudah dipotong untuk transaksi.</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #ef4444 !important; border-radius: 10px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Dibatalkan / Void (Contra)</span>
                        <span class="badge bg-danger bg-opacity-10 text-danger p-2 rounded-circle"><i class="fas fa-ban"></i></span>
                    </div>
                    <h3 class="fw-bold text-danger mb-1">Rp {{ number_format($periodReversedTotal, 0, ',', '.') }}</h3>
                    <p class="card-text text-muted small mb-0">{{ number_format($periodReversedCount) }} mutasi pembatalan tercatat.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTER & QUICK INPUT BAR --}}
    <x-card title="Pusat Pengelolaan & Koreksi Deposit Pelanggan" class="mb-4">
        @slot('headerActions')
            <button type="button" class="btn btn-primary fw-bold btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdminAddDeposit">
                <i class="fas fa-plus-circle me-1"></i>Input Deposit / Saldo Awal
            </button>
        @endslot

        <form method="GET" action="{{ route('admin.deposits.index') }}">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label for="dep_filter_search" class="form-label small fw-bold mb-1">Cari Pelanggan / ID Log / Catatan</label>
                    <input type="text" id="dep_filter_search" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Nama, WA, #123, atau alasan...">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label for="dep_filter_branch" class="form-label small fw-bold mb-1">Cabang</label>
                    <select name="branch_id" id="dep_filter_branch" class="form-select form-select-sm">
                        <option value="">-- Semua Cabang --</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ (int) ($branchId ?? 0) === (int) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label for="dep_filter_type" class="form-label small fw-bold mb-1">Jenis Mutasi</label>
                    <select name="type" id="dep_filter_type" class="form-select form-select-sm">
                        <option value="all" {{ request('type', 'all') === 'all' ? 'selected' : '' }}>Semua Mutasi</option>
                        <option value="active_credit" {{ request('type') === 'active_credit' ? 'selected' : '' }}>Setoran Aktif (Bisa Void)</option>
                        <option value="debit" {{ request('type') === 'debit' ? 'selected' : '' }}>Pemakaian Transaksi</option>
                        <option value="reversed" {{ request('type') === 'reversed' ? 'selected' : '' }}>Setoran Sudah Void</option>
                        <option value="refund" {{ request('type') === 'refund' ? 'selected' : '' }}>Jurnal Pembalik (Contra)</option>
                        <option value="void_restore" {{ request('type') === 'void_restore' ? 'selected' : '' }}>Pulih Akibat Void Nota</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label for="dep_filter_start" class="form-label small fw-bold mb-1">Dari Tanggal</label>
                    <input type="date" id="dep_filter_start" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label for="dep_filter_end" class="form-label small fw-bold mb-1">Sampai Tanggal</label>
                    <input type="date" id="dep_filter_end" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                </div>
                <div class="col-lg-1 col-md-4 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold" title="Terapkan Filter">
                        <i class="fas fa-filter"></i>
                    </button>
                    @if(request()->hasAny(['search', 'branch_id', 'type', 'start_date', 'end_date']))
                        <a href="{{ route('admin.deposits.index', ['tab' => $activeTab]) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                            <i class="fas fa-undo"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </x-card>

    {{-- TAB NAVIGATION --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ $activeTab !== 'customers' ? 'active' : '' }}"
               href="{{ route('admin.deposits.index', array_merge(request()->except('tab', 'logs_page', 'cust_page'), ['tab' => 'logs'])) }}">
                <i class="fas fa-list-alt me-1"></i>Riwayat Mutasi &amp; Koreksi ({{ $depositLogs->total() }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ $activeTab === 'customers' ? 'active' : '' }}"
               href="{{ route('admin.deposits.index', array_merge(request()->except('tab', 'logs_page', 'cust_page'), ['tab' => 'customers'])) }}">
                <i class="fas fa-users me-1"></i>Saldo Deposit Pelanggan ({{ $customersWithDeposit->total() }})
            </a>
        </li>
    </ul>

    @if($activeTab !== 'customers')
        <x-card title="Buku Besar Mutasi Deposit (Audit Trail FMCG)">
            @include('admin.deposits._ledger_table', ['logs' => $depositLogs, 'showCustomerColumn' => true])

            @if($depositLogs->hasPages())
                <div class="mt-3">{{ $depositLogs->links('components.pagination') }}</div>
            @endif
        </x-card>
    @else
        <x-card title="Daftar Pelanggan dengan Saldo Deposit Aktif">
            <x-table>
                @slot('thead')
                    <tr>
                        <th>ID</th>
                        <th>Nama Pelanggan</th>
                        <th>No. WhatsApp / Telepon</th>
                        <th class="text-end">Saldo Deposit Aktif</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                @endslot
                @forelse($customersWithDeposit as $cust)
                    <tr>
                        <td>#{{ $cust->id }}</td>
                        <td class="fw-bold">{{ $cust->name }}</td>
                        <td>{{ $cust->phone ?? '-' }}</td>
                        <td class="text-end">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1 fs-6">
                                Rp {{ number_format((float) $cust->deposit_balance, 0, ',', '.') }}
                            </span>
                        </td>
                        <td class="text-center">
                            <button type="button"
                                    class="btn btn-sm btn-primary me-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalAdminAddDeposit"
                                    data-prefill-customer-id="{{ $cust->id }}">
                                <i class="fas fa-plus me-1"></i>Tambah / Saldo Awal
                            </button>
                            <a href="{{ route('admin.customers.show', $cust->id) }}" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-eye me-1"></i>Detail &amp; Mutasi
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Belum ada pelanggan dengan saldo deposit aktif.</td>
                    </tr>
                @endforelse
            </x-table>

            @if($customersWithDeposit->hasPages())
                <div class="mt-3">{{ $customersWithDeposit->links('components.pagination') }}</div>
            @endif
        </x-card>
    @endif
</div>

@include('admin.deposits._modals')
@endsection
