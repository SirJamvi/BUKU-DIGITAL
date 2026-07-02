{{-- resources/views/kasir/customers/show.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Detail Pelanggan')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('kasir.customers.index') }}">Pelanggan</a></li>
<li class="breadcrumb-item active" aria-current="page">Detail</li>
@endsection

@section('content')

{{-- Menampilkan pesan sukses atau error jika halaman reload --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="row">
    <div class="col-md-4">
        <x-card title="Informasi Pelanggan">
            <div class="text-center mb-3">
                <i class="fas fa-user-circle fa-5x" style="color: var(--kasir-accent);"></i>
                <h4 class="mt-3">{{ $customer->name }}</h4>
                <span class="text-muted">Bergabung sejak {{ \Carbon\Carbon::parse($customer->join_date)->isoFormat('D MMM YYYY') }}</span>
            </div>
            <hr>
            <ul class="list-unstyled">
                <li class="mb-2"><i class="fas fa-phone-alt fa-fw me-2"></i> {{ $customer->phone ?? '-' }}</li>
                <li class="mb-2"><i class="fas fa-envelope fa-fw me-2"></i> {{ $customer->email ?? '-' }}</li>
                <li class="mb-2"><i class="fas fa-map-marker-alt fa-fw me-2"></i> {{ $customer->address ?? '-' }}</li>
            </ul>
        </x-card>
    </div>

    <div class="col-md-8">
        <x-card title="Riwayat Transaksi Pelanggan">
            <div class="table-responsive">
                <x-table>
                    @slot('thead')
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th class="text-end">Total Belanja</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                    @endslot

                    @forelse ($transactions as $transaction)
                    <tr>
                        <td>#{{ $transaction->id }}</td>
                        <td>{{ $transaction->transaction_date->isoFormat('D MMM YYYY, HH:mm') }}</td>
                        <td class="text-end">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if($transaction->payment_status === 'pending')
                            <span class="badge bg-danger mb-2 d-block">Belum Lunas</span>
                            {{-- Tombol Trigger Modal (Modalnya dipindah ke bawah) --}}
                            <button type="button" class="btn btn-sm btn-success w-100" data-bs-toggle="modal" data-bs-target="#payModal{{ $transaction->id }}">
                                <i class="fas fa-check-circle"></i> Approve Lunas
                            </button>
                            @else
                            <span class="badge bg-success mb-2 d-block">Lunas</span>
                            <x-button href="{{ route('kasir.transactions.show', $transaction->id) }}" variant="info" class="btn-sm w-100">
                                <i class="fas fa-receipt"></i> Struk
                            </x-button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center">Pelanggan ini belum memiliki riwayat transaksi.</td>
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
    </div>
</div>

{{-- ============================================================= --}}
{{-- MODAL AREA (Wajib diletakkan di luar struktur Table / Card)   --}}
{{-- ============================================================= --}}
@foreach ($transactions as $transaction)
@if($transaction->payment_status === 'pending')
<div class="modal fade" id="payModal{{ $transaction->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('kasir.transactions.markAsPaid', $transaction->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-content text-start">
                <div class="modal-header">
                    <h5 class="modal-title">Pelunasan Transaksi #{{ $transaction->id }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Total Tagihan: <strong>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</strong></p>
                    <div class="mb-3">
                        <label class="form-label">Uang masuk ke mana?</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="dana">Dana</option>
                            <option value="transfer-bank">Transfer Bank</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Validasi Pembayaran</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach
{{-- ============================================================= --}}

@endsection