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
                            <option value="{{ $category->name }}">{{ $category->name }}</option>
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
            const btnNewCategory = document.getElementById('btn_new_category');

            // Event ketika select berubah
            categorySelect.addEventListener('change', function() {
                if (this.value === 'new') {
                    newCategoryInput.style.display = 'block';
                    newCategoryName.focus();
                    this.value = ''; // Reset select
                } else {
                    newCategoryInput.style.display = 'none';
                    newCategoryName.value = '';
                }
            });

            // Event untuk submit form
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                // Jika ada input kategori baru, gunakan itu
                if (newCategoryInput.style.display === 'block' && newCategoryName.value.trim()) {
                    categorySelect.value = newCategoryName.value.trim();
                    categorySelect.name = 'category_name'; // Pastikan name attribute benar
                } else if (!categorySelect.value) {
                    e.preventDefault();
                    alert('Silakan pilih atau buat kategori pengeluaran');
                    return false;
                }
            });
        });
    </script>
@endsection