{{-- resources/views/kasir/transactions/show.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Detail Transaksi')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('kasir.transactions.index') }}">Riwayat Transaksi</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail #{{ $transaction->id }}</li>
@endsection

@section('content')
    <x-card>
        @slot('title')
            Detail Transaksi #{{ $transaction->id }}
        @endslot
        @slot('headerActions')
            <a href="#" class="btn btn-secondary" onclick="window.print(); return false;">
                <i class="fas fa-print me-2"></i>Cetak Struk
            </a>
        @endslot

        <div class="receipt-preview p-3">
            <div class="header text-center mb-4">
                <h4 class="fw-bold">{{ config('app.name', 'Toko Anda') }}</h4>
                <p class="text-muted mb-0">Alamat Toko Anda di Sini<br>Telp: 08123456789</p>
            </div>
            
            <hr style="border-style: dashed;">

            <div class="info row mb-3">
                <div class="col-6">
                    <div><strong>No. Struk:</strong> #{{ $transaction->id }}</div>
                    <div><strong>Tanggal:</strong> {{ $transaction->transaction_date->format('d/m/Y H:i') }}</div>
                </div>
                <div class="col-6 text-end">
                    <div><strong>Kasir:</strong> {{ $transaction->createdBy->name ?? 'N/A' }}</div>
                    <div><strong>Pelanggan:</strong> {{ $transaction->customer->name ?? 'Umum' }}</div>
                </div>
            </div>
            
            <hr style="border-style: dashed;">

            <div class="items-table">
                @foreach($transaction->details as $item)
                    <div class="row g-2 mb-2">
                        <div class="col-12"><strong>{{ $item->product->name }}</strong></div>
                        <div class="col-6">{{ $item->quantity }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }}</div>
                        <div class="col-6 text-end fw-bold">Rp {{ number_format($item->total_price, 0, ',', '.') }}</div>
                    </div>
                @endforeach
            </div>

            <hr style="border-style: dashed;">
            
            <div class="totals mt-3">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Subtotal</span>
                    <span>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</span>
                </div>
                <div class="d-flex justify-content-between fs-5 fw-bold mt-2 pt-2 border-top">
                    <span >Total</span>
                    <span style="color: var(--kasir-accent);">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</span>
                </div>
                 <div class="d-flex justify-content-between mt-1">
                    <span class="text-muted">Metode Pembayaran</span>
                    <span>{{ $transaction->payment_method }}</span>
                </div>
            </div>

            <div class="footer text-center mt-4">
                <p class="text-muted">--- Terima kasih telah berbelanja ---</p>
            </div>
        </div>
    </x-card>

    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-preview, .receipt-preview * {
                visibility: visible;
            }
            .receipt-preview {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
@endsection