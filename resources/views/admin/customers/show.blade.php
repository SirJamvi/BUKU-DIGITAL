@extends('admin.layouts.app')

@section('title', 'Detail Pelanggan')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Pelanggan</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@section('content')
    @include('components.alert')

    <div class="row g-3">
        <div class="col-md-4">
            <x-card title="Profil Pelanggan" class="mb-3">
                <div class="text-center mb-3">
                    <i class="fas fa-user-circle fa-5x text-secondary mb-3"></i>
                    <h4>{{ $customer->name }}</h4>
                </div>
                <p><strong>Email:</strong> {{ $customer->email ?? '-' }}</p>
                <p><strong>Telepon:</strong> {{ $customer->phone ?? '-' }}</p>
                <p><strong>Alamat:</strong> {{ $customer->address ?? '-' }}</p>
                <p class="mb-0"><strong>Bergabung:</strong> {{ $customer->join_date->isoFormat('D MMMM YYYY') }}</p>
            </x-card>

            <x-card title="Saldo Deposit Pelanggan">
                <div class="text-center py-2">
                    <small class="text-muted text-uppercase fw-bold d-block mb-1">Saldo Aktif Saat Ini</small>
                    <h2 class="fw-bold mb-3" style="color: #0f766e;">
                        Rp {{ number_format((float) ($customer->deposit_balance ?? 0), 0, ',', '.') }}
                    </h2>
                    <div class="d-grid gap-2">
                        <button type="button"
                                class="btn btn-primary fw-bold"
                                data-bs-toggle="modal"
                                data-bs-target="#modalAdminAddDeposit"
                                data-prefill-customer-id="{{ $customer->id }}">
                            <i class="fas fa-plus-circle me-1"></i>Input Deposit / Saldo Awal
                        </button>
                        <a href="{{ route('admin.deposits.index', ['search' => $customer->name]) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-wallet me-1"></i>Buka di Pusat Deposit Admin
                        </a>
                    </div>
                </div>
            </x-card>
        </div>

        <div class="col-md-8">
            <x-card title="Riwayat Mutasi & Koreksi Deposit" class="mb-3">
                @include('admin.deposits._ledger_table', ['logs' => $depositLogs, 'showCustomerColumn' => false])
                @if ($depositLogs->hasPages())
                    <div class="mt-3">{{ $depositLogs->links('components.pagination') }}</div>
                @endif
            </x-card>

            <x-card title="Riwayat Transaksi">
                <x-table>
                    @slot('thead')
                        <tr><th>ID</th><th>Tanggal</th><th class="text-end">Total</th></tr>
                    @endslot
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>#{{ $transaction->id }}</td>
                            <td>{{ $transaction->transaction_date->isoFormat('D MMM YYYY, HH:mm') }}</td>
                            <td class="text-end">Rp {{ number_format($transaction->total_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center">Tidak ada riwayat transaksi.</td></tr>
                    @endforelse
                </x-table>
                @if ($transactions->hasPages())
                    <div class="mt-3">{{ $transactions->links('components.pagination') }}</div>
                @endif
            </x-card>
        </div>
    </div>

    @include('admin.deposits._modals', ['defaultCustomerId' => $customer->id])
@endsection
