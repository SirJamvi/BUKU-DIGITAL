@extends('admin.layouts.app')

@section('title', 'Catat Pengeluaran Baru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.expenses') }}">Pengeluaran</a></li>
    <li class="breadcrumb-item active">Catat Pengeluaran</li>
@endsection

@section('content')
    <x-card title="Formulir Pencatatan Pengeluaran">
        <form action="{{ route('admin.financial.expenses.store') }}" method="POST">
            @csrf
            
            {{-- ======================================================= --}}
            {{-- KATEGORI PENGELUARAN DENGAN DROPDOWN --}}
            {{-- ======================================================= --}}
            <div class="mb-3">
                <label for="category_name" class="form-label">Kategori Pengeluaran <span class="text-danger">*</span></label>
                <div class="input-group">
                    <select 
                        name="category_name" 
                        id="category_name"
                        class="form-select @error('category_name') is-invalid @enderror"
                        required
                    >
                        <option value="" disabled selected>-- Pilih Kategori --</option>
                        <option value="new">+ Tambah Kategori Baru</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->name }}" {{ old('category_name') == $category->name ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-secondary" type="button" id="btn_new_category" style="display: none;">
                        Tambah
                    </button>
                </div>
                @error('category_name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">Pilih kategori dari daftar atau buat kategori baru</div>
            </div>

            {{-- INPUT UNTUK KATEGORI BARU (Hidden) --}}
            <div class="mb-3" id="new_category_input" style="display: none;">
                <label for="new_category_name" class="form-label">Nama Kategori Baru</label>
                <input 
                    type="text" 
                    id="new_category_name" 
                    class="form-control"
                    placeholder="Masukkan nama kategori baru"
                >
                <div class="form-text">Kategori baru akan tersimpan setelah pengeluaran disimpan</div>
            </div>
            {{-- ======================================================= --}}


            {{-- ======================================================= --}}
            {{-- METODE PEMBAYARAN - DINAMIS DARI DATABASE --}}
            {{-- ======================================================= --}}
            <div class="form-group mb-3">
                <label for="payment_method" class="form-label">Metode Pembayaran <span class="text-danger">*</span></label>
                <select name="payment_method" id="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                    <option value="" disabled selected>-- Pilih Metode Pembayaran --</option>
                    
                    @foreach($paymentMethods as $method)
                        {{-- 
                           Value: kita gunakan 'slug' (contoh: 'transfer-bank') untuk disimpan ke database.
                           Label: kita tampilkan 'name' (contoh: 'Transfer Bank') untuk dibaca user.
                        --}}
                        <option value="{{ $method->slug }}" {{ old('payment_method') == $method->slug ? 'selected' : '' }}>
                            {{ $method->name }}
                        </option>
                    @endforeach

                </select>
                @error('payment_method')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">Pilih metode pembayaran yang digunakan</div>
            </div>
            {{-- ======================================================= --}}


            
            <x-input type="number" name="amount" label="Jumlah (Rp)" placeholder="Masukkan jumlah pengeluaran" required />
            <x-input type="date" name="date" label="Tanggal Pengeluaran" :value="now()->toDateString()" required />
            <x-input type="textarea" name="description" label="Deskripsi" placeholder="Contoh: Pembelian bahan baku dari Supplier X" required />
            
            <div class="d-flex justify-content-end mt-4">
                <x-button href="{{ route('admin.financial.expenses') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary">Simpan Pengeluaran</x-button>
            </div>
        </form>
    </x-card>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('category_name');
        const newCategoryInput = document.getElementById('new_category_input');
        const newCategoryName = document.getElementById('new_category_name');
        const form = document.querySelector('form');

        // 1. Event ketika select kategori berubah
        categorySelect.addEventListener('change', function() {
            if (this.value === 'new') {
                // Tampilkan input kategori baru
                newCategoryInput.style.display = 'block';
                newCategoryName.required = true;
                newCategoryName.focus();
                // Reset value select (agar tidak submit value "new")
                this.value = '';
            } else {
                // Sembunyikan input kategori baru
                newCategoryInput.style.display = 'none';
                newCategoryName.required = false;
                newCategoryName.value = '';
            }
        });

        // 2. Event ketika form di-submit
        form.addEventListener('submit', function(e) {
            // Jika input kategori baru sedang ditampilkan
            if (newCategoryInput.style.display === 'block') {
                const newCatValue = newCategoryName.value.trim();

                if (!newCatValue) {
                    e.preventDefault();
                    alert('Silakan masukkan nama kategori baru!');
                    newCategoryName.focus();
                    return false;
                }

                // Set value select dengan kategori baru
                categorySelect.value = newCatValue;
                
                // Jika value tidak bisa di-set (karena option tidak ada), buat option baru
                if (categorySelect.value !== newCatValue) {
                    const newOption = document.createElement('option');
                    newOption.value = newCatValue;
                    newOption.text = newCatValue;
                    newOption.selected = true;
                    categorySelect.appendChild(newOption);
                }
            } else if (!categorySelect.value || categorySelect.value === 'new') {
                // Jika tidak ada kategori yang dipilih
                e.preventDefault();
                alert('Silakan pilih kategori pengeluaran!');
                categorySelect.focus();
                return false;
            }
        });
    });
    </script>
@endsection