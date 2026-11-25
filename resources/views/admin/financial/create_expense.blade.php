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


            
            {{-- ======================================================= --}}
            {{-- SUMBER DANA PENGELUARAN --}}
            {{-- ======================================================= --}}
            <div class="form-group mb-3">
                <label class="form-label d-block fw-bold">Sumber Dana Pengeluaran <span class="text-danger">*</span></label>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="funding_source" id="exp_funding_owner" value="owner" {{ old('funding_source', 'owner') === 'owner' ? 'checked' : '' }} onchange="toggleDrawerWarning(this.value)">
                    <label class="form-check-label text-primary fw-semibold" for="exp_funding_owner">
                        <i class="fas fa-user-shield me-1"></i> Rekening / Kas Owner (Tidak Potong Laci Kasir) <span class="badge bg-primary text-white ms-1">Default Admin</span>
                    </label>
                    <div class="small text-muted ms-4">Biaya operasional yang dibayar melalui rekening owner/perusahaan.</div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="funding_source" id="exp_funding_external" value="external" {{ old('funding_source') === 'external' ? 'checked' : '' }} onchange="toggleDrawerWarning(this.value)">
                    <label class="form-check-label text-success fw-semibold" for="exp_funding_external">
                        <i class="fas fa-money-bill-wave me-1"></i> Dana Eksternal / Gaji Bos (Tidak Potong Laci Kasir)
                    </label>
                    <div class="small text-muted ms-4">Biaya payroll/gaji karyawan atau dana talangan bos luar laci.</div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="funding_source" id="exp_funding_drawer" value="drawer" {{ old('funding_source') === 'drawer' ? 'checked' : '' }} onchange="toggleDrawerWarning(this.value)">
                    <label class="form-check-label text-danger fw-semibold" for="exp_funding_drawer">
                        <i class="fas fa-cash-register me-1"></i> Kas Laci Kasir Cabang (⚠️ Memotong Fisik Uang Laci Kasir)
                    </label>
                    <div class="small text-muted ms-4">Hanya gunakan jika uang pengeluaran diambil tunai dari laci meja kasir hari ini.</div>
                </div>
                <div id="drawer_warning_alert" class="alert alert-warning py-2 px-3 mt-2 d-none" style="font-size: 0.8rem; border-left: 4px solid #f59e0b;">
                    <i class="fas fa-exclamation-triangle me-1 text-warning"></i> <strong>Perhatian:</strong> Memilih <em>Kas Laci Kasir</em> akan memotong perhitungan fisik uang kasir saat tutup shift kasir di cabang bersangkutan.
                </div>
                <div class="form-text text-muted" id="funding_source_hint">
                    Gunakan <strong>Rekening / Kas Owner</strong> sebagai pilihan utama Admin agar tidak mengganggu rekonsiliasi uang fisik laci kasir cabang.
                </div>
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
    function toggleDrawerWarning(val) {
        const alertEl = document.getElementById('drawer_warning_alert');
        if (alertEl) {
            if (val === 'drawer') {
                alertEl.classList.remove('d-none');
            } else {
                alertEl.classList.add('d-none');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const checked = document.querySelector('input[name="funding_source"]:checked');
        if (checked) {
            toggleDrawerWarning(checked.value);
        }
        const categorySelect = document.getElementById('category_name');
        const newCategoryInput = document.getElementById('new_category_input');
        const newCategoryName = document.getElementById('new_category_name');
        const form = document.querySelector('form');

        function checkSalaryCategory(categoryText) {
            const isSalary = /gaji|salary|payroll/i.test(categoryText || '');
            const externalRadio = document.getElementById('exp_funding_external');
            const drawerRadio = document.getElementById('exp_funding_drawer');
            const hint = document.getElementById('funding_source_hint');
            if (isSalary && externalRadio) {
                externalRadio.checked = true;
                if (hint) {
                    hint.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-info-circle me-1"></i>Kategori Gaji otomatis dialokasikan ke Uang Bos / Eksternal (tidak memotong kas laci kasir).</span>';
                }
            } else if (hint) {
                hint.innerHTML = 'Gunakan <strong>Uang Bos / Eksternal</strong> untuk gaji karyawan atau operasional yang tidak diambil dari uang kas fisik laci kasir.';
            }
        }

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
                checkSalaryCategory(this.value);
            }
        });

        if (newCategoryName) {
            newCategoryName.addEventListener('input', function() {
                checkSalaryCategory(this.value);
            });
        }

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