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
@endsection