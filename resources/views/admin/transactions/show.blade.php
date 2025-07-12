@extends('admin.layouts.app')

@section('title', 'Detail Transaksi')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.transactions.index') }}">Transaksi</a></li>
    <li class="breadcrumb-item active">Detail #{{ $transaction->id }}</li>
@endsection

@section('content')
    <x-card title="Detail Transaksi #{{ $transaction->id }}">
         @slot('headerActions')
            <a href="#" class="btn btn-secondary" onclick="window.print(); return false;"><i class="fas fa-print me-2"></i>Cetak</a>
        @endslot
        @include('kasir.transactions.show', ['transaction' => $transaction])
    </x-card>
@endsection