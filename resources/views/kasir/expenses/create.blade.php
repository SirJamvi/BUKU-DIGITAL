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
                <label class="form-label fw-bold">Kategori Pengeluaran <span class="text-danger">*</span></label>
                <select name="category_name" id="category_select" class="form-select @error('category_name') is-invalid @enderror" required>
                    <option value="" disabled {{ old('category_name') ? '' : 'selected' }}>-- Pilih Kategori Pengeluaran --</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->name }}" {{ old('category_name') == $category->name ? 'selected' : '' }}>
                        {{ $category->name }} ({{ $category->is_cogs ? 'HPP' : 'Operasional' }})
                    </option>
                    @endforeach
                    @if(auth()->user()->isAdmin() || auth()->user()->role === 'admin')
                    <option value="__NEW__" {{ old('category_name') == '__NEW__' ? 'selected' : '' }}>+ Buat Kategori Baru (Khusus Admin)...</option>
                    @endif
                </select>
                @error('category_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- Form Kategori Baru Khusus Admin --}}
        @if(auth()->user()->isAdmin() || auth()->user()->role === 'admin')
        <div id="new_category_section" class="row mb-3 p-3 bg-light rounded border {{ old('category_name') == '__NEW__' ? '' : 'd-none' }}">
            <div class="col-md-6 mb-2">
                <label class="form-label fw-bold text-primary">Nama Kategori Baru <span class="text-danger">*</span></label>
                <input type="text" name="new_category_name" id="new_category_name" class="form-control @error('new_category_name') is-invalid @enderror" value="{{ old('new_category_name') }}" placeholder="Contoh: Perbaikan Mesin">
                @error('new_category_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-2">
                <label class="form-label fw-bold text-primary">Jenis Klasifikasi Akuntansi <span class="text-danger">*</span></label>
                <select name="new_category_type" id="new_category_type" class="form-select @error('new_category_type') is-invalid @enderror">
                    <option value="Operasional" {{ old('new_category_type') == 'Operasional' ? 'selected' : '' }}>Operasional (Beban Usaha Rutin)</option>
                    <option value="HPP" {{ old('new_category_type') == 'HPP' ? 'selected' : '' }}>HPP (Harga Pokok Penjualan)</option>
                    <option value="Piutang Karyawan" {{ old('new_category_type') == 'Piutang Karyawan' ? 'selected' : '' }}>Piutang Karyawan (Kasbon)</option>
                    <option value="Non-usaha" {{ old('new_category_type') == 'Non-usaha' ? 'selected' : '' }}>Non-usaha (Prive / Pengeluaran Luar Usaha)</option>
                </select>
                @error('new_category_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        @endif

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

        {{-- Pilihan Sumber Dana --}}
        <div class="row mb-3">
            <div class="col-12">
                <label class="form-label fw-bold d-block text-primary">
                    <i class="fas fa-hand-holding-dollar me-1"></i> Sumber Dana Pengeluaran <span class="text-danger">*</span>
                </label>
                <div class="p-3 bg-white rounded border" style="border: 2px dashed #0d6efd !important;">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="funding_source" id="exp_funding_drawer" value="drawer" {{ old('funding_source', 'drawer') === 'drawer' ? 'checked' : '' }}>
                        <label class="form-check-label" for="exp_funding_drawer">
                            <strong class="text-dark">1. Uang Laci Kasir (Shift Hari Ini)</strong>
                            <span class="d-block small text-muted">Masuk ke pengeluaran dan <strong>MEMOTONG</strong> Saldo Kas Fisik Laci kasir hari ini.</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="funding_source" id="exp_funding_external" value="external" {{ old('funding_source') === 'external' ? 'checked' : '' }}>
                        <label class="form-check-label" for="exp_funding_external">
                            <strong class="text-dark">2. Kas Kemarin / Uang Bos / Transfer</strong>
                            <span class="d-block small text-muted">Tercatat di pengeluaran, tetapi <u>TIDAK MEMOTONG</u> Saldo Kas Fisik Laci kasir hari ini.</span>
                        </label>
                    </div>
                </div>
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
        const categorySelect = document.getElementById('category_select');
        const newCatSection = document.getElementById('new_category_section');
        const newCatName = document.getElementById('new_category_name');
        if (categorySelect && newCatSection) {
            categorySelect.addEventListener('change', function() {
                if (this.value === '__NEW__') {
                    newCatSection.classList.remove('d-none');
                    if (newCatName) newCatName.setAttribute('required', 'required');
                } else {
                    newCatSection.classList.add('d-none');
                    if (newCatName) newCatName.removeAttribute('required');
                }
            });
        }

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