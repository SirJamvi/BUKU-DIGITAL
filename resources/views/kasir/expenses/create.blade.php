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

    <form action="{{ route('kasir.expenses.store') }}" method="POST" id="expenseForm">
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
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        id="amount_display"
                        class="form-control @error('amount') is-invalid @enderror"
                        value="{{ old('amount') ? number_format((float) old('amount'), 0, ',', '.') : '' }}"
                        placeholder="0"
                        required>
                    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                {{-- Nilai asli (tanpa titik) yang benar-benar dikirim ke server --}}
                <input type="hidden" name="amount" id="amount_raw" value="{{ old('amount') }}">
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const displayInput = document.getElementById('amount_display');
        const rawInput = document.getElementById('amount_raw');
        const form = document.getElementById('expenseForm');

        // Format angka jadi "300.000" gaya IDR (titik sebagai pemisah ribuan)
        function formatRupiah(value) {
            const numberOnly = value.replace(/\D/g, ''); // buang semua selain digit
            if (!numberOnly) return '';
            return new Intl.NumberFormat('id-ID').format(parseInt(numberOnly, 10));
        }

        // Ambil angka murni dari tampilan yang sudah berformat
        function unformatRupiah(value) {
            return value.replace(/\D/g, '');
        }

        displayInput.addEventListener('input', function (e) {
            const cursorFromEnd = this.value.length - this.selectionStart;
            this.value = formatRupiah(this.value);
            rawInput.value = unformatRupiah(this.value);

            // Jaga posisi kursor tetap wajar saat mengetik di tengah angka
            const newPos = this.value.length - cursorFromEnd;
            this.setSelectionRange(newPos, newPos);
        });

        // Pastikan nilai mentah sinkron tepat sebelum submit (jaga-jaga)
        form.addEventListener('submit', function (e) {
            rawInput.value = unformatRupiah(displayInput.value);

            if (!rawInput.value || parseInt(rawInput.value, 10) < 1) {
                e.preventDefault();
                displayInput.classList.add('is-invalid');
                displayInput.focus();
            }
        });

        // Sinkronkan nilai awal jika ada old('amount') saat reload karena validasi gagal
        if (displayInput.value) {
            rawInput.value = unformatRupiah(displayInput.value);
        }
    });
</script>
@endsection