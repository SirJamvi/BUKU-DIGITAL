{{-- resources/views/admin/users/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Manajemen Pengguna & Driver')

@section('breadcrumb')
<li class="breadcrumb-item active" aria-current="page">Pengguna & Driver</li>
@endsection

@push('styles')
<style>
    .nav-tabs .nav-link {
        font-weight: 600;
        color: #64748b;
        padding: 0.85rem 1.5rem;
        border: none;
        border-bottom: 3px solid transparent;
        background: transparent;
        transition: all 0.2s ease;
    }
    .nav-tabs .nav-link:hover {
        color: #1e293b;
        border-color: #cbd5e1;
    }
    .nav-tabs .nav-link.active {
        color: #2563eb;
        border-bottom: 3px solid #2563eb;
        background-color: transparent;
    }
    .stat-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 1.25rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    }
    .quick-date-btn {
        font-size: 0.82rem;
        padding: 0.28rem 0.65rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }
</style>
@endpush

@section('content')
<div class="mb-4">
    @include('components.alert')

    {{-- Navigasi Tab Utama --}}
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs border-bottom-0 px-3 pt-2" id="userManagementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'users' ? 'active' : '' }}" 
                            id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab">
                        <i class="fas fa-users-gear me-2 text-primary"></i> Daftar Semua Pengguna
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'driver_recap' ? 'active' : '' }}" 
                            id="driver-recap-tab" data-bs-toggle="tab" data-bs-target="#driver-recap-pane" type="button" role="tab">
                        <i class="fas fa-truck-fast me-2 text-warning"></i> Rekap Pengiriman Driver
                        <span class="badge bg-warning text-dark ms-1">{{ count($driverRecap ?? []) }} Driver</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="userManagementTabsContent">
        {{-- ========================================================= --}}
        {{-- TAB 1: DAFTAR SEMUA PENGGUNA --}}
        {{-- ========================================================= --}}
        <div class="tab-pane fade {{ $activeTab === 'users' ? 'show active' : '' }}" id="users-pane" role="tabpanel">
            <x-card>
                @slot('title')
                <i class="fas fa-users me-2 text-primary"></i> Daftar Akun Pengguna
                @endslot
                @slot('headerActions')
                <x-button href="{{ route('admin.users.create') }}" variant="primary">
                    <i class="fas fa-plus me-2"></i>Tambah User Baru
                </x-button>
                @endslot

                <x-table>
                    @slot('thead')
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Tanggal Bergabung</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                    @endslot

                    @forelse ($users as $user)
                    <tr>
                        <td>{{ $users->firstItem() + $loop->index }}</td>
                        <td>
                            <strong class="text-dark">{{ $user->name }}</strong>
                            @if($user->phone)
                            <br><small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $user->phone }}</small>
                            @endif
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if ($user->role == 'admin')
                            <span class="badge bg-danger">{{ $user->role === 'ko' ? 'KO' : ucfirst($user->role) }}</span>
                            @elseif ($user->role == 'driver')
                            <span class="badge bg-warning text-dark"><i class="fas fa-motorcycle me-1"></i>Driver</span>
                            @elseif ($user->role == 'kasir')
                            <span class="badge bg-success">Kasir</span>
                            @else
                            <span class="badge bg-info">{{ $user->role === 'ko' ? 'KO' : ucfirst($user->role) }}</span>
                            @endif
                        </td>
                        <td>
                            {{-- Mencegah user menonaktifkan dirinya sendiri --}}
                            @if (Auth::id() !== $user->id)
                            <div class="form-check form-switch">
                                <input class="form-check-input toggle-status" type="checkbox"
                                    data-id="{{ $user->id }}"
                                    {{ $user->is_active ? 'checked' : '' }}>
                                <label class="form-check-label status-label-{{ $user->id }}">
                                    @if ($user->is_active)
                                    <span class="badge bg-success">Aktif</span>
                                    @else
                                    <span class="badge bg-secondary">Nonaktif</span>
                                    @endif
                                </label>
                            </div>
                            @else
                            <span class="badge bg-success">Aktif (Anda)</span>
                            @endif
                        </td>
                        <td>{{ $user->created_at->isoFormat('D MMMM YYYY') }}</td>
                        <td class="text-center">
                            <x-button href="{{ route('admin.users.edit', $user->id) }}" variant="warning" class="btn-sm">
                                <i class="fas fa-edit"></i>
                            </x-button>
                            {{-- Mencegah admin menghapus dirinya sendiri --}}
                            @if (Auth::id() !== $user->id)
                            <x-button type="button" variant="danger" class="btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $user->id }}">
                                <i class="fas fa-trash"></i>
                            </x-button>
                            @endif

                            {{-- Modal Konfirmasi Hapus --}}
                            <x-modal id="deleteModal-{{ $user->id }}" title="Konfirmasi Hapus Pengguna">
                                <p>Apakah Anda yakin ingin menghapus pengguna <strong>{{ $user->name }}</strong>? Semua data terkait pengguna ini mungkin akan hilang.</p>
                                @slot('footer')
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="button" variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                                    <x-button type="submit" variant="danger">Ya, Hapus</x-button>
                                </form>
                                @endslot
                            </x-modal>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4">Tidak ada data pengguna ditemukan.</td>
                    </tr>
                    @endforelse
                </x-table>

                @if ($users->hasPages())
                <div class="mt-3">
                    {{ $users->links('components.pagination') }}
                </div>
                @endif
            </x-card>
        </div>

        {{-- ========================================================= --}}
        {{-- TAB 2: REKAP PENGIRIMAN DRIVER (DENGAN FILTER TANGGAL)   --}}
        {{-- ========================================================= --}}
        <div class="tab-pane fade {{ $activeTab === 'driver_recap' ? 'show active' : '' }}" id="driver-recap-pane" role="tabpanel">
            {{-- Form Filter Rentang Tanggal & Driver --}}
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <form action="{{ route('admin.users.index') }}" method="GET" id="driverFilterForm">
                        <input type="hidden" name="tab" value="driver_recap">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label fw-semibold text-dark small">
                                    <i class="fas fa-calendar-day text-primary me-1"></i> Dari Tanggal
                                </label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}" required>
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label fw-semibold text-dark small">
                                    <i class="fas fa-calendar-day text-danger me-1"></i> Hingga Tanggal
                                </label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}" required>
                            </div>
                            <div class="col-md-3">
                                <label for="driver_id" class="form-label fw-semibold text-dark small">
                                    <i class="fas fa-user-tie text-secondary me-1"></i> Pilih Driver
                                </label>
                                <select class="form-select" id="driver_id" name="driver_id">
                                    <option value="all" {{ $selectedDriverId === 'all' ? 'selected' : '' }}>-- Semua Driver --</option>
                                    @foreach($driversList as $drv)
                                    <option value="{{ $drv->id }}" {{ (string)$selectedDriverId === (string)$drv->id ? 'selected' : '' }}>
                                        {{ $drv->name }} {{ $drv->phone ? '(' . $drv->phone . ')' : '' }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary fw-semibold w-100">
                                    <i class="fas fa-filter me-1"></i> Filter Data
                                </button>
                                <a href="{{ route('admin.users.index', ['tab' => 'driver_recap']) }}" class="btn btn-outline-secondary" title="Reset Filter">
                                    <i class="fas fa-rotate-left"></i>
                                </a>
                            </div>
                        </div>

                        {{-- Tombol Shortcut Periode Cepat --}}
                        <div class="mt-3 pt-3 border-top d-flex align-items-center gap-2 flex-wrap">
                            <span class="text-muted small fw-semibold me-1"><i class="fas fa-bolt text-warning me-1"></i>Shortcut Cepat:</span>
                            <button type="button" class="btn btn-sm btn-outline-primary quick-date-btn" onclick="setQuickDate('today')">Hari Ini</button>
                            <button type="button" class="btn btn-sm btn-outline-primary quick-date-btn" onclick="setQuickDate('last_7_days')">7 Hari Terakhir</button>
                            <button type="button" class="btn btn-sm btn-outline-primary quick-date-btn" onclick="setQuickDate('last_14_days')">14 Hari Terakhir</button>
                            <button type="button" class="btn btn-sm btn-outline-primary quick-date-btn" onclick="setQuickDate('this_month')">Bulan Ini</button>
                            <button type="button" class="btn btn-sm btn-outline-primary quick-date-btn" onclick="setQuickDate('last_month')">Bulan Lalu</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Kartu Ringkasan KPI Monitoring Driver --}}
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted small fw-semibold">DRIVER BERTUGAS</div>
                                <h3 class="fw-bold mb-0 mt-1 text-dark">{{ count($driverRecap ?? []) }}</h3>
                            </div>
                            <div class="p-3 bg-secondary bg-opacity-10 rounded-circle text-secondary">
                                <i class="fas fa-user-tie fa-xl"></i>
                            </div>
                        </div>
                        <small class="text-muted">Total driver terdata</small>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted small fw-semibold">RITASE (TRIP)</div>
                                <h3 class="fw-bold mb-0 mt-1 text-dark">{{ collect($driverRecap ?? [])->sum('total_trips') }}</h3>
                            </div>
                            <div class="p-3 bg-dark bg-opacity-10 rounded-circle text-dark">
                                <i class="fas fa-truck-moving fa-xl"></i>
                            </div>
                        </div>
                        <small class="text-muted">Total trip / surat jalan</small>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted small fw-semibold">TOTAL PESANAN</div>
                                <h3 class="fw-bold mb-0 mt-1 text-primary">{{ collect($driverRecap ?? [])->sum('total_orders') }}</h3>
                            </div>
                            <div class="p-3 bg-primary bg-opacity-10 rounded-circle text-primary">
                                <i class="fas fa-receipt fa-xl"></i>
                            </div>
                        </div>
                        <small class="text-muted">Toko / struk pengantaran</small>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted small fw-semibold">TOTAL MUATAN</div>
                                <h3 class="fw-bold mb-0 mt-1 text-warning">{{ collect($driverRecap ?? [])->sum('total_qty') }}</h3>
                            </div>
                            <div class="p-3 bg-warning bg-opacity-10 rounded-circle text-warning">
                                <i class="fas fa-boxes-stacked fa-xl"></i>
                            </div>
                        </div>
                        <small class="text-muted">Ball / pack produk fisik</small>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-muted small fw-semibold">TOTAL TAGIHAN</div>
                                <h4 class="fw-bold mb-0 mt-1 text-success">Rp {{ number_format(collect($driverRecap ?? [])->sum('total_amount'), 0, ',', '.') }}</h4>
                            </div>
                            <div class="p-3 bg-success bg-opacity-10 rounded-circle text-success">
                                <i class="fas fa-money-bill-wave fa-xl"></i>
                            </div>
                        </div>
                        <small class="text-muted">Nilai transaksi driver</small>
                    </div>
                </div>
            </div>

            {{-- Tabel Papan Rekap Pengiriman Driver --}}
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-truck-fast text-warning me-2"></i> Rekap Monitoring Pengiriman Driver
                        </h5>
                        <small class="text-white-50">
                            Periode: <strong>{{ \Carbon\Carbon::parse($startDate)->locale('id')->isoFormat('D MMMM YYYY') }}</strong> s/d <strong>{{ \Carbon\Carbon::parse($endDate)->locale('id')->isoFormat('D MMMM YYYY') }}</strong>
                        </small>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-secondary px-3 py-2 fs-6">
                            <i class="fas fa-user-tie me-1"></i> {{ count($driverRecap ?? []) }} Driver
                        </span>
                        <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold">
                            <i class="fas fa-boxes-stacked me-1"></i> {{ collect($driverRecap ?? [])->sum('total_qty') }} Ball
                        </span>
                        <span class="badge bg-success px-3 py-2 fs-6 fw-bold">
                            Rp {{ number_format(collect($driverRecap ?? [])->sum('total_amount'), 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <div class="card-body p-0">
                    @if(isset($driverRecap) && count($driverRecap) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 py-3" style="width: 50px;">No</th>
                                    <th>Nama Driver</th>
                                    <th class="text-center">Ritase (Trip)</th>
                                    <th class="text-center">Total Pesanan</th>
                                    <th>Total Muatan (Semua Produk)</th>
                                    <th class="text-end">Total Tagihan</th>
                                    <th>Status Pembayaran</th>
                                    <th class="text-center pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($driverRecap as $index => $d)
                                <tr>
                                    <td class="ps-3 text-muted fw-bold">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="p-2 rounded-circle me-3" style="background-color: #fef3c7 !important; color: #d97706 !important;">
                                                <i class="fas fa-motorcycle fa-lg"></i>
                                            </div>
                                            <div>
                                                <strong class="fs-6 text-dark">{{ $d['driver_name'] }}</strong>
                                                @if($d['driver_phone'] && $d['driver_phone'] !== '-')
                                                <br><small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $d['driver_phone'] }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-dark rounded-pill px-3 py-2 fs-6">
                                            {{ $d['total_trips'] }} Ritase
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                                            {{ $d['total_orders'] }} Toko / Struk
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary fs-6">{{ $d['total_qty'] }} Ball / Qty</div>
                                        <div class="small text-muted mt-1">
                                            @foreach($d['product_quantities'] as $pName => $qty)
                                            <span class="badge bg-light text-dark border me-1 mb-1">{{ $pName }}: <strong>{{ $qty }}</strong></span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold fs-6 text-dark">
                                        Rp {{ number_format($d['total_amount'], 0, ',', '.') }}
                                    </td>
                                    <td>
                                        <div class="small">
                                            <span class="text-success fw-bold">
                                                <i class="fas fa-check-circle me-1"></i>Lunas: Rp {{ number_format($d['total_paid'], 0, ',', '.') }}
                                            </span>
                                            @if($d['total_pending'] > 0)
                                            <br>
                                            <span class="text-danger fw-bold">
                                                <i class="fas fa-clock me-1"></i>Kasbon: Rp {{ number_format($d['total_pending'], 0, ',', '.') }}
                                            </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center pe-3">
                                        <button type="button" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalDriverDetail_{{ $d['driver_id'] }}">
                                            <i class="fas fa-list-check me-1"></i> Lihat Detail Trip
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted">
                            <i class="fas fa-truck-ramp-box fa-3x"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Tidak Ada Data Pengiriman Driver</h5>
                        <p class="text-muted small">
                            Tidak ditemukan aktivitas pengiriman pada rentang tanggal 
                            <strong>{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}</strong> s/d <strong>{{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</strong>.
                        </p>
                        <a href="{{ route('admin.users.index', ['tab' => 'driver_recap']) }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-rotate-left me-1"></i> Reset Rentang Tanggal
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================= --}}
{{-- MODAL DETAIL PENGIRIMAN PER DRIVER                        --}}
{{-- ========================================================= --}}
@if(isset($driverRecap) && count($driverRecap) > 0)
@foreach($driverRecap as $d)
<div class="modal fade" id="modalDriverDetail_{{ $d['driver_id'] }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fas fa-motorcycle text-warning me-2"></i> Rincian Pengiriman Driver: <span class="text-warning">{{ $d['driver_name'] }}</span>
                    </h5>
                    <small class="text-white-50">
                        Periode: <strong>{{ \Carbon\Carbon::parse($startDate)->locale('id')->isoFormat('D MMMM YYYY') }}</strong> s/d <strong>{{ \Carbon\Carbon::parse($endDate)->locale('id')->isoFormat('D MMMM YYYY') }}</strong> | Total Muatan: <strong>{{ $d['total_qty'] }} Ball</strong> | Total Tagihan: <strong>Rp {{ number_format($d['total_amount'], 0, ',', '.') }}</strong>
                    </small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                {{-- Ringkasan Atas Modal --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded border text-center shadow-sm">
                            <div class="text-muted small fw-semibold">TOTAL RITASE / TRIP</div>
                            <h4 class="fw-bold mb-0 text-dark">{{ $d['total_trips'] }} Trip</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded border text-center shadow-sm">
                            <div class="text-muted small fw-semibold">TOTAL TOKO DIKIRIM</div>
                            <h4 class="fw-bold mb-0 text-primary">{{ $d['total_orders'] }} Toko</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded border text-center shadow-sm">
                            <div class="text-muted small fw-semibold">TOTAL MUATAN FISIK</div>
                            <h4 class="fw-bold mb-0 text-success">{{ $d['total_qty'] }} Ball</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded border text-center shadow-sm">
                            <div class="text-muted small fw-semibold">TOTAL NILAI TRANSAKSI</div>
                            <h4 class="fw-bold mb-0 text-danger">Rp {{ number_format($d['total_amount'], 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>

                {{-- Perulangan per Trip / Surat Jalan --}}
                @foreach($d['trips'] as $tIdx => $trip)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-dark fs-6 px-3 py-2">
                                <i class="fas fa-file-invoice text-warning me-1"></i> Surat Jalan: {{ $trip['trip_code'] }}
                            </span>
                            <span class="badge bg-secondary">
                                <i class="fas fa-calendar-day me-1"></i> {{ $trip['time']->locale('id')->isoFormat('D MMM YYYY, HH:mm') }} WIB
                            </span>
                            <span class="badge bg-primary">
                                Muatan: {{ $trip['total_qty'] }} Ball
                            </span>
                            @foreach($trip['product_breakdown'] as $pName => $qty)
                            <span class="badge bg-light text-dark border">{{ $pName }}: {{ $qty }}</span>
                            @endforeach
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark me-2">Subtotal Trip: <span class="text-danger">Rp {{ number_format($trip['total_amount'], 0, ',', '.') }}</span></span>
                            @if($trip['is_batch'] && Route::has('kasir.pos.deliveryReceipt'))
                            <a href="{{ route('kasir.pos.deliveryReceipt', $trip['trip_code']) }}" target="_blank" class="btn btn-sm btn-outline-warning text-dark bg-warning bg-opacity-25 fw-bold">
                                <i class="fas fa-print me-1"></i> Cetak Surat Jalan
                            </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 py-2">Toko / Customer</th>
                                        <th>Item Pesanan</th>
                                        <th class="text-center">Status Pembayaran</th>
                                        <th class="text-center">Metode</th>
                                        <th class="text-end pe-3">Total Tagihan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($trip['transactions'] as $trx)
                                    <tr>
                                        <td class="ps-3">
                                            <strong>{{ $trx->customer->name ?? 'Pelanggan Umum' }}</strong>
                                            @if($trx->customer && $trx->customer->phone)
                                            <br><small class="text-muted">{{ $trx->customer->phone }}</small>
                                            @endif
                                            @if($trx->customer && $trx->customer->address)
                                            <br><small class="text-muted"><i class="fas fa-location-dot me-1"></i>{{ $trx->customer->address }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach($trx->details as $dItem)
                                            <div>
                                                {{ $dItem->product->name ?? 'Produk' }} 
                                                <strong>x{{ (int) $dItem->quantity }}</strong>
                                            </div>
                                            @endforeach
                                        </td>
                                        <td class="text-center">
                                            @if($trx->payment_status === 'paid')
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Lunas</span>
                                            @else
                                            <span class="badge bg-danger"><i class="fas fa-clock me-1"></i>Kasbon</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">
                                                {{ strtoupper(str_replace('-', ' ', $trx->payment_method ?: 'Cash')) }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-3 fw-bold">
                                            Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- 1. Toggle Status Pengguna Aktif / Nonaktif ---
        const toggleButtons = document.querySelectorAll('.toggle-status');
        const urlTemplate = "{{ route('admin.users.toggle-status', ['user' => 0]) }}";

        toggleButtons.forEach(button => {
            button.addEventListener('change', function() {
                const userId = this.getAttribute('data-id');
                const isChecked = this.checked;
                const label = document.querySelector(`.status-label-${userId}`);
                const url = urlTemplate.replace('/0', `/${userId}`);

                fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.is_active) {
                                label.innerHTML = '<span class="badge bg-success">Aktif</span>';
                            } else {
                                label.innerHTML = '<span class="badge bg-secondary">Nonaktif</span>';
                            }
                        } else {
                            this.checked = !isChecked;
                            alert('Gagal merubah status: ' + data.message);
                        }
                    })
                    .catch(() => {
                        this.checked = !isChecked;
                        alert('Terjadi kesalahan sistem.');
                    });
            });
        });

        // --- 2. Tab Navigation URL State Handler ---
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam === 'driver_recap') {
            const driverRecapTabEl = document.getElementById('driver-recap-tab');
            if (driverRecapTabEl) {
                const tab = new bootstrap.Tab(driverRecapTabEl);
                tab.show();
            }
        }
    });

    // --- 3. Shortcut Cepat Pilihan Rentang Tanggal ---
    function setQuickDate(type) {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const form = document.getElementById('driverFilterForm');
        
        const now = new Date();
        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        let start, end;
        end = formatDate(now);

        if (type === 'today') {
            start = end;
        } else if (type === 'last_7_days') {
            const d = new Date();
            d.setDate(d.getDate() - 6);
            start = formatDate(d);
        } else if (type === 'last_14_days') {
            const d = new Date();
            d.setDate(d.getDate() - 13);
            start = formatDate(d);
        } else if (type === 'this_month') {
            const d = new Date(now.getFullYear(), now.getMonth(), 1);
            start = formatDate(d);
        } else if (type === 'last_month') {
            const firstDayLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDayLastMonth = new Date(now.getFullYear(), now.getMonth(), 0);
            start = formatDate(firstDayLastMonth);
            end = formatDate(lastDayLastMonth);
        }

        if (start && end) {
            startDateInput.value = start;
            endDateInput.value = end;
            form.submit();
        }
    }
</script>
@endpush