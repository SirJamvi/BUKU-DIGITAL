@extends('kasir.layouts.app') 

@section('title', 'Terima Stok Supplier')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="font-weight-bold text-dark">Penerimaan Stok dari Supplier</h2>
            <p class="text-muted">Gunakan form ini untuk menambah stok harian.</p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('warning_confirmation'))
    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-exclamation-triangle fa-2x text-warning me-3"></i>
            <div>
                <h5 class="fw-bold text-dark mb-1">Perhatian: Penyimpangan Nilai Restock Terdeteksi</h5>
                <p class="text-muted mb-0 small">Sistem mendeteksi deviasi signifikan pada transaksi restock ini:</p>
            </div>
        </div>
        <ul class="mb-3 ps-4">
            @foreach(session('warning_confirmation.messages') as $msg)
                <li class="fw-semibold text-danger">{{ $msg }}</li>
            @endforeach
        </ul>
        <form action="{{ route('kasir.inventory.store_stock') }}" method="POST">
            @csrf
            @foreach(session('warning_confirmation.input') as $key => $val)
                @if(!is_array($val) && $key !== '_token' && $key !== 'confirm')
                    <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                @endif
            @endforeach
            <input type="hidden" name="confirm" value="1">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold px-4 text-dark">
                    <i class="fas fa-check-circle me-1"></i> Lanjutkan (Konfirmasi)
                </button>
                <a href="{{ route('kasir.inventory.add_stock') }}" class="btn btn-secondary px-3">Batal / Input Ulang</a>
            </div>
        </form>
    </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="{{ route('kasir.inventory.store_stock') }}" method="POST">
                @csrf
                
                <!-- Toggle Record Expense -->
                <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 border">
                    <input class="form-check-input ms-0 me-3" type="checkbox" id="record_expense" name="record_expense" value="1" style="transform: scale(1.5);" {{ old('record_expense', '1') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label font-weight-bold pt-1" for="record_expense">Catat Otomatis ke Pengeluaran (Cash Flow)</label>
                    <small class="d-block text-muted ms-5">Matikan sakelar ini jika Anda <b>sudah/lupa</b> mencatat pengeluaran di menu Expenses secara manual.</small>
                </div>

                <h5 class="font-weight-bold mb-3 border-bottom pb-2">Informasi Pembelian</h5>
                <div class="row">
                    <!-- Dropdown Supplier -->
                    <div class="col-md-6 mb-3">
                        <label for="supplier_id" class="form-label font-weight-bold">Pilih Supplier / Pabrik</label>
                        <select class="form-select form-select-lg @error('supplier_id') is-invalid @enderror" id="supplier_id" name="supplier_id" required>
                            <option value="" disabled selected>-- Pilih Supplier --</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Dropdown Produk Dinamis -->
                    <div class="col-md-6 mb-3">
                        <label for="product_id" class="form-label font-weight-bold">Pilih Produk Es Kristal</label>
                        <select class="form-select form-select-lg @error('product_id') is-invalid @enderror" id="product_id" name="product_id" required disabled>
                            <option value="" disabled selected>-- Pilih Supplier Terlebih Dahulu --</option>
                        </select>
                        @error('product_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="quantity" class="form-label font-weight-bold">Jumlah Masuk (Ball/Pcs)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="fas fa-boxes"></i></span>
                            <input type="number" class="form-control @error('quantity') is-invalid @enderror" id="quantity" name="quantity" value="{{ old('quantity') }}" min="1" required placeholder="Contoh: 30">
                        </div>
                        @error('quantity')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="purchase_price" class="form-label font-weight-bold">Harga Beli per Unit (Rp)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">Rp</span>
                            <input type="number" step="any" class="form-control @error('purchase_price') is-invalid @enderror" id="purchase_price" name="purchase_price" value="{{ old('purchase_price') }}" placeholder="Auto terisi dari supplier">
                        </div>
                        <small class="text-muted">Bisa diubah jika ada kenaikan/penurunan harga beli dari supplier saat ini.</small>
                        @error('purchase_price')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Bagian Pembayaran (Bisa Disembunyikan oleh Toggle) -->
                <div id="payment_section" class="mt-4">
                    <h5 class="font-weight-bold mb-3 border-bottom pb-2">Informasi Pembayaran (Opsional)</h5>
                    <div class="row align-items-end">
                        <div class="col-md-6 mb-3">
                            <label for="payment_method" class="form-label font-weight-bold">Metode Pembayaran</label>
                            <select class="form-select form-select-lg @error('payment_method') is-invalid @enderror" id="payment_method" name="payment_method">
                                <option value="" disabled selected>-- Pilih Pembayaran --</option>
                                @foreach ($paymentMethods as $pm)
                                    <option value="{{ $pm->slug }}" {{ old('payment_method') == $pm->slug ? 'selected' : '' }}>
                                        {{ $pm->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payment_method')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-danger">Total Pengeluaran</label>
                            <input type="text" class="form-control form-control-lg bg-light fw-bold text-danger" id="grand_total_display" readonly value="Rp 0">
                        </div>
                    </div>

                    <!-- Pilihan Sumber Dana Pembelian Es -->
                    <div class="mb-3 p-3 bg-white rounded-3 border" style="border: 2px dashed #0d6efd !important;">
                        <label class="form-label font-weight-bold d-block mb-2 text-primary">
                            <i class="fas fa-hand-holding-dollar me-1"></i> Sumber Dana Pembelian Es <span class="text-danger">*</span>
                        </label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="funding_source" id="funding_drawer" value="drawer" {{ old('funding_source', 'drawer') === 'drawer' ? 'checked' : '' }}>
                            <label class="form-check-label" for="funding_drawer">
                                <strong class="text-dark">1. Uang Laci Kasir (Shift Hari Ini)</strong>
                                <span class="d-block small text-muted">Masuk ke arus kas dan <strong>MEMOTONG</strong> Saldo Kas Fisik Laci kasir hari ini.</span>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="funding_source" id="funding_external" value="external" {{ old('funding_source') === 'external' ? 'checked' : '' }}>
                            <label class="form-check-label" for="funding_external">
                                <strong class="text-dark">2. Kas Kemarin / Uang Bos / Transfer</strong>
                                <span class="d-block small text-muted">Stok & HPP Moving Average ter-update, tercatat di riwayat, tetapi <u>TIDAK MEMOTONG</u> Saldo Kas Laci hari ini.</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-4 mt-3">
                    <label for="notes" class="form-label font-weight-bold">Catatan (Opsional)</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" placeholder="Catatan pengiriman/stok...">{{ old('notes') }}</textarea>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-lg px-5 rounded-3">
                        <i class="fas fa-save me-2"></i> Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const suppliers = @json($suppliers);
        
        const supplierSelect = document.getElementById('supplier_id');
        const productSelect = document.getElementById('product_id');
        const qtyInput = document.getElementById('quantity');
        const purchasePriceInput = document.getElementById('purchase_price');
        const totalDisplay = document.getElementById('grand_total_display');
        const paymentSection = document.getElementById('payment_section');
        const paymentMethodSelect = document.getElementById('payment_method');
        const recordExpenseToggle = document.getElementById('record_expense');

        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
        }

        function calculateTotal() {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const defaultPrice = selectedOption && selectedOption.value ? parseFloat(selectedOption.getAttribute('data-price')) : 0;
            const customPrice = parseFloat(purchasePriceInput.value);
            const price = (!isNaN(customPrice) && customPrice > 0) ? customPrice : defaultPrice;
            const qty = parseFloat(qtyInput.value) || 0;
            
            const total = price * qty;
            totalDisplay.value = formatRupiah(total);
        }

        function togglePaymentSection() {
            if (recordExpenseToggle.checked) {
                paymentSection.style.display = 'block';
                paymentMethodSelect.setAttribute('required', 'required');
            } else {
                paymentSection.style.display = 'none';
                paymentMethodSelect.removeAttribute('required');
            }
        }

        recordExpenseToggle.addEventListener('change', togglePaymentSection);

        supplierSelect.addEventListener('change', function() {
            const supplierId = this.value;
            productSelect.innerHTML = '<option value="" disabled selected>-- Pilih Produk --</option>';
            purchasePriceInput.value = '';
            
            if(supplierId) {
                const supplier = suppliers.find(s => s.id == supplierId);
                if(supplier && supplier.products && supplier.products.length > 0) {
                    supplier.products.forEach(product => {
                        const option = document.createElement('option');
                        option.value = product.id;
                        option.setAttribute('data-price', product.pivot.price);
                        option.textContent = `${product.name} - Harga: ${formatRupiah(product.pivot.price)}`;
                        productSelect.appendChild(option);
                    });
                    productSelect.disabled = false;
                } else {
                    productSelect.innerHTML = '<option value="" disabled selected>-- Supplier belum memiliki produk --</option>';
                    productSelect.disabled = true;
                }
            } else {
                productSelect.disabled = true;
            }
            calculateTotal();
        });

        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const defaultPrice = selectedOption.getAttribute('data-price');
                purchasePriceInput.value = defaultPrice || '';
            }
            calculateTotal();
        });

        purchasePriceInput.addEventListener('input', calculateTotal);
        qtyInput.addEventListener('input', calculateTotal);

        // Inisialisasi awal
        togglePaymentSection();
        
        // Retain old value
        if(supplierSelect.value) {
            supplierSelect.dispatchEvent(new Event('change'));
            const oldProductId = '{{ old('product_id') }}';
            if(oldProductId) {
                setTimeout(() => {
                    productSelect.value = oldProductId;
                    calculateTotal();
                }, 100);
            }
        }
    });
</script>
@endpush