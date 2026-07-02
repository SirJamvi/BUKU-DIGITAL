@extends('admin.layouts.app')

@section('title', 'Edit Transaksi #' . $transaction->id)
@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('admin.transactions.index') }}">Transaksi</a></li>
<li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
{{-- Karena form dan logikanya sama, kita bisa memanggil view edit milik kasir --}}
{{-- Ini adalah praktik cerdas untuk menghindari duplikasi kode (prinsip DRY) --}}
@include('kasir.transactions.edit', [
'transaction' => $transaction,
'products' => $products,
'customers' => $customers,
// [BARU] Kirimkan rute update khusus untuk Admin
'update_route' => route('admin.transactions.update', $transaction->id)
])
@endsection