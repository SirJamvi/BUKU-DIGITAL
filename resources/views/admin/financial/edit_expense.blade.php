@extends('admin.layouts.app')

@section('title', 'Edit Pengeluaran')
@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
<li class="breadcrumb-item"><a href="{{ route('admin.financial.expenses') }}">Pengeluaran</a></li>
<li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<x-card title="Formulir Edit Pengeluaran">

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('admin.financial.expenses.update', $expense->id) }}" method="POST">
        @csrf
        @method('PUT')

        <x-input
            name="category_name"
            label="Kategori Pengeluaran"
            :value="old('category_name', $expense->category->name ?? '')"
            required />

        <x-input type="number" name="amount" label="Jumlah (Rp)" :value="old('amount', $expense->amount)" required />

        <div class="mb-3">
            <label class="form-label fw-bold">Metode Pembayaran <span class="text-danger">*</span></label>
            <select name="payment_method" class="form-select" required>
                <option value="">-- Pilih Metode Pembayaran --</option>
                @foreach($paymentMethods as $method)
                <option value="{{ $method->slug }}" {{ (old('payment_method', $expense->payment_method) == $method->slug) ? 'selected' : '' }}>
                    {{ $method->name }}
                </option>
                @endforeach
            </select>
        </div>

        <x-input type="date" name="date" label="Tanggal Pengeluaran" :value="old('date', \Carbon\Carbon::parse($expense->date)->toDateString())" required />

        <x-input type="textarea" name="description" label="Deskripsi" :value="old('description', $expense->description)" required />

        <div class="d-flex justify-content-end mt-4">
            <x-button href="{{ route('admin.financial.expenses') }}" variant="secondary" class="me-2">Batal</x-button>
            <x-button type="submit" variant="primary">Simpan Perubahan</x-button>
        </div>
    </form>
</x-card>
@endsection