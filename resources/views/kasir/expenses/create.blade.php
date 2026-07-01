@extends('kasir.layouts.app')

@section('title', 'Catat Pengeluaran Baru')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('kasir.expenses.index') }}">Pengeluaran</a></li>
<li class="breadcrumb-item active" aria-current="page">Tambah Baru</li>
@endsection

@section('content')
<x-card title="Form Pencatatan Pengeluaran">
    @slot('headerActions')
    <a href="{{ route('kasir.expenses.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
    @endslot

    <form action="{{ route('kasir.expenses.store') }}" method="POST">
        @csrf

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Tanggal Pengeluaran <span class="text-danger">*</span></label>
                <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', date('Y-m-d')) }}" required>
                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold">Kategori / Tulis Baru <span class="text-danger">*</span></label>
                <input class="form-control @error('category_name') is-invalid @enderror" list="categoryOptions" name="category_name" value="{{ old('category_name') }}" placeholder="Ketik atau pilih kategori..." required>
                <datalist id="categoryOptions">
                    @foreach($categories as $category)
                    <option value="{{ $category->name }}">
                        @endforeach
                </datalist>
                @error('category_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Metode Pembayaran <span class="text-danger">*</span></label>
                <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                    <option value="" disabled selected>-- Pilih Sumber Dana --</option>
                    @foreach($paymentMethods as $method)
                    <option value="{{ $method->slug }}" {{ old('payment_method') == $method->slug ? 'selected' : '' }}>
                        {{ $method->name }}
                    </option>
                    @endforeach
                </select>
                @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold">Jumlah (Rp) <span class="text-danger">*</span></label>
                <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" min="1" required>
                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Deskripsi <span class="text-danger">*</span></label>
            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="text-end">
            <x-button type="submit" variant="primary" class="px-4">
                <i class="fas fa-save me-1"></i> Simpan Data
            </x-button>
        </div>
    </form>
</x-card>
@endsection