{{-- resources/views/kasir/pos/index.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Point of Sale (POS)')

@section('breadcrumb')
<li class="breadcrumb-item active" aria-current="page">POS</li>
@endsection

@push('styles')
<style>
    .pos-product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 1rem;
        max-height: 75vh;
        overflow-y: auto;
        padding: 5px;
    }

    .product-card {
        cursor: pointer;
        border: 1px solid #eee;
        transition: all 0.2s ease;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        border-color: var(--kasir-accent);
    }

    .pos-cart {
        background-color: var(--kasir-bg-dominant);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .cart-items {
        flex-grow: 1;
        overflow-y: auto;
        min-height: 200px;
    }

    .btn-checkout {
        background-color: var(--kasir-accent);
        color: white;
        font-weight: bold;
        padding: 15px;
    }

    .btn-checkout:hover {
        background-color: #d64a6a;
        color: white;
    }

    /* ============================================== */
    /* STYLE BARU: Untuk search pelanggan yang mirip dengan produk */
    /* ============================================== */
    .customer-dropdown {
        position: relative;
    }

    .customer-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ced4da;
        border-top: none;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .customer-search-results.show {
        display: block;
    }

    .customer-item {
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }

    .customer-item:hover {
        background-color: #f8f9fa;
    }

    .customer-item.selected {
        background-color: #e7f3ff;
        color: var(--kasir-accent);
        font-weight: 600;
    }

    .no-results {
        padding: 0.75rem;
        text-align: center;
        color: #6c757d;
    }
</style>
@endpush

@section('content')
<form action="{{ route('kasir.pos.store') }}" method="POST" id="pos-form">
    @csrf
    <div class="row g-4">
        {{-- Kolom Kiri: Daftar Produk --}}
        <div class="col-lg-7">
            <x-card>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="product-search" class="form-control" placeholder="Cari produk berdasarkan nama atau SKU...">
                </div>
                <div class="pos-product-grid">
                    @forelse ($products as $product)
                    {{-- KARTU PRODUK UTAMA --}}
                    <div class="card product-card mb-3 cursor-pointer shadow-sm"
                        data-id="{{ $product->id }}"
                        data-variant-id=""
                        data-name="{{ $product->name }}"
                        data-price="{{ $product->base_price }}">
                        <div class="card-body p-3 text-center">
                            <h6 class="fw-bold mb-1">{{ $product->name }}</h6>
                            <div class="text-primary fw-bold">Rp {{ number_format($product->base_price, 0, ',', '.') }}</div>
                            <small class="text-muted">Stok: {{ $product->inventory->current_stock }} {{ $product->unit }}</small>
                        </div>
                    </div>

                    {{-- KARTU VARIAN (jika ada) --}}
                    @if($product->variants && $product->variants->count() > 0)
                    @foreach($product->variants as $variant)
                    @php
                    $customPrice = $product->base_price + $variant->price_adjustment;
                    @endphp
                    <div class="card product-card mb-3 cursor-pointer shadow-sm bg-light"
                        data-id="{{ $product->id }}"
                        data-variant-id="{{ $variant->id }}"
                        data-name="{{ $product->name }} ({{ $variant->variant_name }})"
                        data-price="{{ $customPrice }}">
                        <div class="card-body p-3 text-center">
                            <h6 class="fw-bold mb-1">{{ $product->name }} <span class="badge bg-warning text-dark">{{ $variant->variant_name }}</span></h6>
                            <div class="text-danger fw-bold">Rp {{ number_format($customPrice, 0, ',', '.') }}</div>
                            <small class="text-muted">Stok Master: {{ $product->inventory->current_stock }}</small>
                        </div>
                    </div>
                    @endforeach
                    @endif
                    @empty
                    <p class="text-muted">Tidak ada produk yang tersedia.</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        {{-- Kolom Kanan: Keranjang & Pembayaran --}}
        <div class="col-lg-5">
            <x-card class="pos-cart shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h5 class="card-title mb-0 fw-bold d-inline-block">Keranjang</h5>
                        <span id="order-mode-badge" class="badge bg-secondary ms-2 small">Pesanan Langsung</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="btn-add-delivery-order" title="Klik untuk menambah pesanan pelanggan lain dalam 1 pengiriman driver">
                        <i class="fas fa-plus-circle me-1"></i> + Tambah Transaksi ke Pengiriman
                    </button>
                </div>

                {{-- Alert Mode Pengiriman Rombongan --}}
                <div id="multi-order-banner" class="alert alert-info py-2 px-3 small d-none mb-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-truck-moving fs-5 me-2 text-primary"></i>
                        <div>
                            <strong>Mode Pengiriman Multi-Customer:</strong>
                            <div class="small">Pesanan otomatis dicatat sebagai <strong>Kasbon / Pending</strong> hingga driver kembali.</div>
                        </div>
                    </div>
                </div>

                {{-- Tab Navigasi Pesanan (Aktif jika pesanan >= 2) --}}
                <div id="order-tabs-wrapper" class="d-none mb-2">
                    <label class="form-label small text-muted mb-1 fw-bold">Pilih Pesanan Toko yang Sedang Diedit:</label>
                    <div class="d-flex gap-1 align-items-center flex-nowrap" id="order-tabs-list" style="overflow-x: auto; padding-bottom: 4px;">
                        <!-- Tab tombol pesanan & tombol tambah akan dirender oleh JavaScript -->
                    </div>
                </div>

                <hr class="mt-1 mb-3">

                {{-- Custom search untuk pelanggan pada pesanan aktif --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="customer_search" class="form-label fw-semibold small mb-0">
                            <i class="fas fa-store text-primary me-1"></i>Pelanggan / Toko (<span id="active-order-label">Pesanan 1</span>)
                            <span class="text-danger fw-bold d-none" id="customer-required-badge" title="Wajib diisi jika Kasbon atau Pengiriman Driver">*</span>
                        </label>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 fw-semibold" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#quickAddCustomerModal">
                            <i class="fas fa-plus me-1"></i>+ Toko Baru
                        </button>
                    </div>
                    <div class="customer-dropdown position-relative">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text"
                                id="customer_search"
                                class="form-control"
                                placeholder="Cari nama toko / ketik nama toko baru..."
                                autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="clear-customer" title="Reset Pelanggan">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="hidden" name="customer_id" id="customer_id" value="">
                        <input type="hidden" name="customer_name" id="customer_name_input" value="">
                        <div class="customer-search-results shadow-sm" id="customer-results">
                            {{-- Shortcut cepat jika mengetik nama toko baru --}}
                            <div class="customer-item text-primary fw-bold bg-light d-none" id="quick-add-from-search" style="border-bottom: 2px dashed #0d6efd;">
                                <i class="fas fa-plus-circle me-1"></i> Daftarkan "<span id="typed-customer-name"></span>" sebagai Toko Baru
                            </div>
                            <div class="customer-item" data-id="" data-name="Pelanggan Umum" id="opt-pelanggan-umum">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>Pelanggan Umum</strong>
                                    <span class="badge bg-secondary" style="font-size: 0.65rem;">Khusus Tunai Lunas</span>
                                </div>
                            </div>
                            @foreach ($customers as $customer)
                            <div class="customer-item" data-id="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone ?? '' }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>{{ $customer->name }}</span>
                                    @if($customer->phone)
                                    <small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $customer->phone }}</small>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="text-muted" id="customer-helper-text">Pilih toko penerima untuk pesanan yang aktif</small>
                        <small class="text-danger fw-semibold d-none" id="customer-kasbon-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>Kasbon / Pengiriman dilarang 'Pelanggan Umum'
                        </small>
                    </div>
                </div>

                <div class="cart-items mb-2 table-responsive">
                    <table class="table table-sm">
                        <tbody id="cart-items-body">
                        </tbody>
                    </table>
                </div>

                <div class="mt-auto">
                    <hr>
                    <div>
                        <div class="d-flex justify-content-between">
                            <h6 class="text-muted" id="subtotal-label">Subtotal Pesanan Ini</h6>
                            <h6 id="cart-subtotal">Rp 0</h6>
                        </div>
                        <div class="d-flex justify-content-between">
                            <h5 class="fw-bold" id="total-label">Total Pesanan Ini</h5>
                            <h5 class="fw-bold" id="cart-total" style="color: var(--kasir-accent);">Rp 0</h5>
                        </div>
                    </div>

                    {{-- Container Ringkasan Multi-Order (jika >= 2 pesanan) --}}
                    <div id="multi-order-summary-box" class="d-none mt-2 p-2 rounded bg-light border">
                        <small class="text-muted fw-bold d-block mb-1">Ringkasan Rombongan Pengiriman:</small>
                        <div id="multi-order-summary-list" class="small mb-2"></div>
                        <div class="d-flex justify-content-between fw-bold text-dark pt-1 border-top">
                            <span>TOTAL SEMUA PESANAN:</span>
                            <span class="text-danger fs-6" id="multi-order-grand-total">Rp 0</span>
                        </div>
                    </div>

                    <div id="hidden-items"></div>
                    <input type="hidden" name="total_amount" id="total_amount_hidden" value="0">

                    <hr>

                    {{-- Dropdown Driver Pengantar (Selalu ada, opsional untuk 1 order, wajib untuk multi-order) --}}
                    <div class="mb-3" id="driver-select-container">
                        <label for="driver_id" class="form-label fw-bold d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-motorcycle text-primary me-1"></i> Driver Pengantar <span class="text-danger d-none" id="driver-required-star">*</span></span>
                            <span class="badge bg-light text-muted border fw-normal" id="driver-badge-status" style="font-size: 0.72rem;">Opsional</span>
                        </label>
                        <select name="driver_id" id="driver_id" class="form-select border-primary">
                            <option value="">-- Tanpa Driver (Beli Langsung di Tempat) --</option>
                            @if(isset($drivers) && $drivers->isNotEmpty())
                                @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->name }} ({{ $driver->phone ?? 'Driver' }})</option>
                                @endforeach
                            @else
                                <option value="" disabled>Belum ada user role Driver</option>
                            @endif
                        </select>
                        <small class="text-muted" id="driver-help-text">Pilih driver jika pesanan ingin diantar, atau biarkan kosong jika beli langsung di toko</small>
                    </div>

                    {{-- Dropdown Metode Pembayaran (Hanya untuk 1 order beli langsung) --}}
                    <div class="mb-3" id="payment-method-container">
                        <label for="payment_method" class="form-label fw-bold">Metode Pembayaran</label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="" disabled selected>-- Pilih Metode --</option>
                            <option value="kasbon" class="text-danger fw-bold">⚠️ BELUM LUNAS (KASBON)</option>
                            @foreach($paymentMethods as $method)
                            <option value="{{ $method->slug }}">
                                {{ $method->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Badge Status Kasbon saat Mode Pengiriman Driver Aktif --}}
                    <div id="multi-order-payment-badge" class="d-none mb-3 text-center">
                        <div class="badge bg-danger bg-opacity-10 text-danger border border-danger w-100 py-2 fs-6 text-wrap text-start px-3">
                            <div class="fw-bold" id="delivery-badge-title"><i class="fas fa-motorcycle me-1"></i> STATUS: DIKIRIM VIA DRIVER</div>
                            <small class="text-dark d-block mt-1" id="delivery-badge-subtitle" style="font-size: 0.78rem;">
                                Pembayaran diset sementara sebagai <strong>Kasbon / Pending</strong>. Kasir menginput pembayaran riil (Cash/DANA/Transfer) di Dashboard saat Driver kembali.
                            </small>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-checkout" id="btn-submit-order">
                            <i class="fas fa-check-circle me-2"></i> PROSES PEMBAYARAN
                        </button>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</form>

{{-- ======================================================== --}}
{{-- MODAL QUICK ADD PELANGGAN / TOKO BARU (TANPA RELOAD)     --}}
{{-- ======================================================== --}}
<div class="modal fade" id="quickAddCustomerModal" tabindex="-1" aria-labelledby="quickAddCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="quickAddCustomerModalLabel">
                    <i class="fas fa-store me-2"></i> Tambah Pelanggan / Toko Baru
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickAddCustomerForm">
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 px-3 small border-0 mb-3 d-flex align-items-center">
                        <i class="fas fa-info-circle me-2 fs-5 text-info"></i>
                        <span>Toko akan langsung tersimpan ke data pelanggan & otomatis terpilih untuk <strong id="quick-add-order-target">Pesanan Aktif</strong>.</span>
                    </div>

                    <div id="quick-customer-alert" class="alert alert-danger py-2 px-3 small d-none mb-3"></div>

                    <div class="mb-3">
                        <label for="quick_customer_name" class="form-label fw-bold small mb-1">
                            Nama Toko / Pelanggan <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="quick_customer_name" name="name" required placeholder="Contoh: Toko Berkah / Warung Bu Siti" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label for="quick_customer_phone" class="form-label fw-bold small mb-1">
                            No. WhatsApp / HP <span class="text-muted fw-normal">(Disarankan untuk Kasbon)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fab fa-whatsapp text-success"></i></span>
                            <input type="tel" class="form-control" id="quick_customer_phone" name="phone" placeholder="Contoh: 081234567890" autocomplete="off">
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Sangat penting untuk tracking & follow-up penagihan kasbon.</small>
                    </div>
                    <div class="mb-2">
                        <label for="quick_customer_address" class="form-label fw-bold small mb-1">Alamat / Patokan Pengiriman</label>
                        <textarea class="form-control" id="quick_customer_address" name="address" rows="2" placeholder="Contoh: Jl. Ahmad Yani No. 12 (Dekat Indomaret)"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="btn-save-quick-customer">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="quick-customer-spinner" role="status"></span>
                        <i class="fas fa-save me-1" id="quick-customer-save-icon"></i> Simpan & Pilih Toko
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
        // ==============================================
        // 1. DATA STATE & LOGGING SYSTEM
        // ==============================================
        let orders = [
            {
                id: 1,
                customerId: '',
                customerName: '',
                items: {}
            }
        ];
        let activeOrderIndex = 0;

        function posLog(actionName, details = {}) {
            const timestamp = new Date().toLocaleTimeString('id-ID');
            console.group(`%c[POS DEBUG ${timestamp}] ${actionName}`, 'color: #0d6efd; font-weight: bold; background: #e7f1ff; padding: 2px 6px; border-radius: 4px;');
            console.log("Detail Action:", details);
            console.log("Active Order Index:", `${activeOrderIndex} (Pesanan ${activeOrderIndex + 1})`);
            console.log("Semua Pesanan Saat Ini (" + orders.length + " pesanan):", JSON.parse(JSON.stringify(orders)));
            console.groupEnd();
        }

        // Safe DOM manipulation helpers
        function safeEl(id) {
            return document.getElementById(id);
        }

        function safeSetVisible(elOrId, isVisible) {
            const el = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;
            if (!el) return;
            if (isVisible) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        }

        function safeSetRequired(elOrId, isRequired) {
            const el = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;
            if (!el) return;
            if (isRequired) {
                el.setAttribute('required', 'required');
            } else {
                el.removeAttribute('required');
            }
        }

        function safeSetText(elOrId, text) {
            const el = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;
            if (!el) return;
            el.innerText = text;
        }

        function formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID').format(angka || 0);
        }

        // ==============================================
        // 2. ORDER & CART ACTIONS
        // ==============================================
        function addToCart(productId, variantId, name, price) {
            if (!orders[activeOrderIndex]) {
                activeOrderIndex = 0;
            }
            const currentOrder = orders[activeOrderIndex];
            const key = variantId ? `${productId}-${variantId}` : `${productId}`;

            if (currentOrder.items[key]) {
                currentOrder.items[key].quantity++;
            } else {
                currentOrder.items[key] = {
                    productId: productId,
                    variantId: variantId || '',
                    name: name,
                    price: parseFloat(price),
                    quantity: 1
                };
            }

            posLog("🛒 TAMBAH PRODUK KE KERANJANG", {
                productId: productId,
                variantId: variantId || '-',
                name: name,
                price: parseFloat(price),
                targetOrder: `Pesanan ${activeOrderIndex + 1}`,
                customer: currentOrder.customerName || 'Belum dipilih (Umum)',
                totalQuantityItemIni: currentOrder.items[key].quantity
            });

            renderCart();
        }

        window.updateQuantity = function(key, change) {
            if (!orders[activeOrderIndex]) return;
            const currentOrder = orders[activeOrderIndex];

            if (currentOrder.items[key]) {
                currentOrder.items[key].quantity += change;
                const newQty = currentOrder.items[key].quantity;
                const itemName = currentOrder.items[key].name;

                if (newQty <= 0) {
                    delete currentOrder.items[key];
                    posLog("🗑️ ITEM DIHAPUS DARI KERANJANG", {
                        key: key,
                        name: itemName,
                        order: `Pesanan ${activeOrderIndex + 1}`
                    });
                } else {
                    posLog("🔢 UPDATE JUMLAH ITEM", {
                        key: key,
                        name: itemName,
                        perubahan: change > 0 ? `+${change}` : `${change}`,
                        quantityBaru: newQty,
                        order: `Pesanan ${activeOrderIndex + 1}`
                    });
                }
                renderCart();
            }
        };

        window.switchOrder = function(index) {
            if (index >= 0 && index < orders.length) {
                const prevIndex = activeOrderIndex;
                activeOrderIndex = index;

                posLog("🔄 PINDAH TAB PESANAN (SWITCH ORDER)", {
                    dari: `Pesanan ${prevIndex + 1}`,
                    ke: `Pesanan ${index + 1}`,
                    pelanggan: orders[index].customerName || 'Belum dipilih (Umum)',
                    totalItem: Object.values(orders[index].items).reduce((s, it) => s + it.quantity, 0)
                });

                renderCart();
            }
        };

        window.removeOrder = function(index) {
            if (orders.length <= 1) return;
            const removed = orders[index];
            orders.splice(index, 1);
            if (activeOrderIndex >= orders.length) {
                activeOrderIndex = orders.length - 1;
            }

            posLog("❌ PESANAN DIHAPUS DARI PENGIRIMAN", {
                indexDihapus: index,
                namaPelanggan: removed.customerName || `Pesanan ${index + 1}`,
                sisaPesanan: orders.length,
                activeOrderSekarang: `Pesanan ${activeOrderIndex + 1}`
            });

            renderCart();
        };

        window.addNewOrder = function() {
            orders.push({
                id: Date.now(),
                customerId: '',
                customerName: '',
                items: {}
            });
            activeOrderIndex = orders.length - 1;

            posLog("➕ TAMBAH TRANSAKSI / TOKO BARU KE PENGIRIMAN", {
                totalPesanan: orders.length,
                activeOrderBaru: `Pesanan ${activeOrderIndex + 1}`,
                mode: "Pengiriman Multi-Customer (Driver)"
            });

            renderCart();

            const customerSearch = safeEl('customer_search');
            if (customerSearch) {
                customerSearch.focus();
            }
        };

        // Wire header button
        const btnAddDelivery = safeEl('btn-add-delivery-order');
        if (btnAddDelivery) {
            btnAddDelivery.addEventListener('click', function() {
                addNewOrder();
            });
        }

        // ==============================================
        // 3. RENDER CART (BULLETPROOF & SAFE)
        // ==============================================
        function renderCart() {
            try {
                if (activeOrderIndex >= orders.length) {
                    activeOrderIndex = Math.max(0, orders.length - 1);
                }
                const isMultiOrder = orders.length > 1;
                const currentOrder = orders[activeOrderIndex] || { items: {} };

                // 1. Elemen UI multi-order vs single-order
                const multiBanner = safeEl('multi-order-banner');
                const tabsWrapper = safeEl('order-tabs-wrapper');
                const tabsList = safeEl('order-tabs-list');
                const driverContainer = safeEl('driver-select-container');
                const driverSelect = safeEl('driver_id');
                const paymentContainer = safeEl('payment-method-container');
                const paymentSelect = safeEl('payment_method');
                const multiPaymentBadge = safeEl('multi-order-payment-badge');
                const multiSummaryBox = safeEl('multi-order-summary-box');
                const multiSummaryList = safeEl('multi-order-summary-list');
                const btnSubmit = safeEl('btn-submit-order');
                const orderModeBadge = safeEl('order-mode-badge');

                safeSetText('active-order-label', `Pesanan ${activeOrderIndex + 1}`);

                if (isMultiOrder) {
                    safeSetVisible(multiBanner, true);
                    safeSetVisible(tabsWrapper, true);
                    safeSetVisible(driverContainer, true);
                    safeSetRequired(driverSelect, true);
                    safeSetVisible(paymentContainer, false);
                    safeSetRequired(paymentSelect, false);
                    safeSetVisible(multiPaymentBadge, true);
                    safeSetVisible(multiSummaryBox, true);

                    const driverStar = safeEl('driver-required-star');
                    if (driverStar) driverStar.classList.remove('d-none');

                    const driverBadge = safeEl('driver-badge-status');
                    if (driverBadge) {
                        driverBadge.innerText = 'Wajib Diisi';
                        driverBadge.className = 'badge bg-danger bg-opacity-10 text-danger border border-danger fw-bold';
                    }

                    const driverHelp = safeEl('driver-help-text');
                    if (driverHelp) driverHelp.innerText = 'Pilih supir yang membawa pesanan rombongan ini';

                    const badgeTitle = safeEl('delivery-badge-title');
                    if (badgeTitle) badgeTitle.innerHTML = '<i class="fas fa-truck me-1"></i> STATUS: PENGIRIMAN ROMBONGAN';

                    const badgeSubtitle = safeEl('delivery-badge-subtitle');
                    if (badgeSubtitle) badgeSubtitle.innerHTML = 'Semua pesanan diset sementara sebagai <strong>Kasbon / Pending</strong>. Kasir menginput pembayaran riil masing-masing toko di Dashboard saat Driver kembali.';

                    if (orderModeBadge) {
                        orderModeBadge.className = 'badge bg-primary ms-2 small';
                        orderModeBadge.innerHTML = `<i class="fas fa-truck me-1"></i> Rombongan Driver (${orders.length} Toko)`;
                    }

                    if (btnAddDelivery) {
                        btnAddDelivery.innerHTML = `<i class="fas fa-plus-circle me-1"></i> + Tambah Toko Lain`;
                    }

                    if (btnSubmit) {
                        btnSubmit.innerHTML = `<i class="fas fa-truck-moving me-2"></i> PROSES PENGIRIMAN (${orders.length} PESANAN)`;
                    }

                    // Render tab list
                    if (tabsList) {
                        tabsList.innerHTML = '';
                        orders.forEach((ord, idx) => {
                            const itemCount = Object.values(ord.items || {}).reduce((sum, item) => sum + item.quantity, 0);
                            const isCustEmpty = !ord.customerName || ord.customerName.toLowerCase() === 'pelanggan umum';
                            const custLabel = ord.customerName ? ord.customerName : `Toko ${idx + 1}`;
                            const warnBadge = isCustEmpty ? '<i class="fas fa-exclamation-circle text-warning ms-1" title="Nama toko wajib ditentukan!"></i>' : '';
                            const isActive = idx === activeOrderIndex;

                            const btnGroup = document.createElement('div');
                            btnGroup.className = 'btn-group';
                            btnGroup.innerHTML = `
                                <button type="button" class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} text-nowrap fw-semibold" onclick="switchOrder(${idx})">
                                    <i class="fas fa-store me-1"></i> ${escapeHtml(custLabel)} ${warnBadge} <span class="badge ${isActive ? 'bg-light text-dark' : 'bg-secondary'} ms-1">${itemCount}</span>
                                </button>
                                <button type="button" class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} text-danger" onclick="removeOrder(${idx})" title="Hapus pesanan ini">
                                    &times;
                                </button>
                            `;
                            tabsList.appendChild(btnGroup);
                        });

                        // Tambahkan tombol inline tambah toko di deretan tab
                        const addTabBtn = document.createElement('button');
                        addTabBtn.type = 'button';
                        addTabBtn.className = 'btn btn-sm btn-outline-primary text-nowrap fw-bold';
                        addTabBtn.innerHTML = '<i class="fas fa-plus"></i> Tambah Toko';
                        addTabBtn.onclick = function() { window.addNewOrder(); };
                        tabsList.appendChild(addTabBtn);
                    }
                } else {
                    safeSetVisible(multiBanner, false);
                    safeSetVisible(tabsWrapper, false);
                    safeSetVisible(multiSummaryBox, false);

                    // Driver select selalu aktif di single order mode sebagai opsi pengiriman
                    safeSetVisible(driverContainer, true);
                    safeSetRequired(driverSelect, false);

                    const driverStar = safeEl('driver-required-star');
                    if (driverStar) driverStar.classList.add('d-none');

                    const driverBadge = safeEl('driver-badge-status');
                    if (driverBadge) {
                        driverBadge.innerText = 'Opsional';
                        driverBadge.className = 'badge bg-light text-muted border fw-normal';
                    }

                    const driverHelp = safeEl('driver-help-text');
                    if (driverHelp) driverHelp.innerText = 'Pilih driver jika pesanan ingin diantar, atau biarkan kosong jika beli langsung di toko';

                    if (btnAddDelivery) {
                        btnAddDelivery.innerHTML = `<i class="fas fa-plus-circle me-1"></i> + Tambah Transaksi ke Pengiriman`;
                    }

                    const hasDriver = driverSelect && driverSelect.value !== '';

                    if (hasDriver) {
                        // 1 Pesanan DIKIRIM via Driver (Kasbon/Pending otomatis)
                        safeSetVisible(paymentContainer, false);
                        safeSetRequired(paymentSelect, false);
                        safeSetVisible(multiPaymentBadge, true);

                        const badgeTitle = safeEl('delivery-badge-title');
                        if (badgeTitle) badgeTitle.innerHTML = '<i class="fas fa-motorcycle me-1"></i> STATUS: DIKIRIM VIA DRIVER';

                        const badgeSubtitle = safeEl('delivery-badge-subtitle');
                        if (badgeSubtitle) badgeSubtitle.innerHTML = 'Pesanan diset sementara sebagai <strong>Kasbon / Pending</strong>. Kasir menginput pembayaran riil (Cash/DANA/Transfer) di Dashboard saat Driver kembali.';

                        const driverName = driverSelect.options[driverSelect.selectedIndex]?.text || 'Driver';

                        if (orderModeBadge) {
                            orderModeBadge.className = 'badge bg-primary ms-2 small';
                            orderModeBadge.innerHTML = `<i class="fas fa-motorcycle me-1"></i> Dikirim: ${driverName.split('(')[0]}`;
                        }

                        if (btnSubmit) {
                            btnSubmit.innerHTML = `<i class="fas fa-truck-moving me-2"></i> PROSES PENGIRIMAN DRIVER`;
                        }
                    } else {
                        // 1 Pesanan BELI LANGSUNG DI TEMPAT
                        safeSetVisible(paymentContainer, true);
                        safeSetRequired(paymentSelect, true);
                        safeSetVisible(multiPaymentBadge, false);

                        if (orderModeBadge) {
                            orderModeBadge.className = 'badge bg-secondary ms-2 small';
                            orderModeBadge.innerText = 'Beli Langsung di Tempat';
                        }

                        if (btnSubmit) {
                            btnSubmit.innerHTML = `<i class="fas fa-check-circle me-2"></i> PROSES PEMBAYARAN`;
                        }
                    }
                }

            // 2. Sinkronkan Customer Input dengan Order Aktif
                // 2. Sinkronkan Customer Input dengan Order Aktif
                const customerSearch = safeEl('customer_search');
                const customerIdInput = safeEl('customer_id');
                const customerNameInput = safeEl('customer_name_input');
                if (customerSearch) customerSearch.value = currentOrder.customerName || '';
                if (customerIdInput) customerIdInput.value = currentOrder.customerId || '';
                if (customerNameInput) customerNameInput.value = currentOrder.customerName || '';

                // Deteksi Kasbon & Pengiriman untuk Warning Visual
                const hasDriver = driverSelect && driverSelect.value !== '';
                const isKasbon = isMultiOrder || hasDriver || (paymentSelect && paymentSelect.value === 'kasbon');

                const customerReqBadge = safeEl('customer-required-badge');
                if (customerReqBadge) {
                    customerReqBadge.classList.toggle('d-none', !isKasbon);
                }

                const isCurrentCustEmpty = !currentOrder.customerName || currentOrder.customerName.toLowerCase() === 'pelanggan umum';
                const kasbonWarningEl = safeEl('customer-kasbon-warning');
                if (kasbonWarningEl) {
                    if (isKasbon && isCurrentCustEmpty) {
                        kasbonWarningEl.classList.remove('d-none');
                    } else {
                        kasbonWarningEl.classList.add('d-none');
                    }
                }

                const customerResults = safeEl('customer-results');
                if (customerResults) {
                    customerResults.querySelectorAll('.customer-item').forEach(item => {
                        if (item.dataset.id === (currentOrder.customerId || '')) {
                            item.classList.add('selected');
                        } else {
                            item.classList.remove('selected');
                        }
                    });
                }

                // 3. Render Items di Cart Body untuk Order Aktif
                const cartBody = safeEl('cart-items-body');
                let activeOrderSubtotal = 0;

                if (cartBody) {
                    cartBody.innerHTML = '';
                    const itemKeys = Object.keys(currentOrder.items || {});
                    if (itemKeys.length === 0) {
                        const currentLabel = currentOrder.customerName ? ` (${currentOrder.customerName})` : '';
                        cartBody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-4">Keranjang <strong>Pesanan ${activeOrderIndex + 1}${currentLabel}</strong> masih kosong.<br><small class="text-muted">Pilih produk di sebelah kiri.</small></td></tr>`;
                    } else {
                        itemKeys.forEach(key => {
                            const item = currentOrder.items[key];
                            const totalPrice = item.price * item.quantity;
                            activeOrderSubtotal += totalPrice;

                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>
                                    <span class="fw-bold small">${item.name}</span><br>
                                    <small class="text-muted">Rp ${formatRupiah(item.price)}</small>
                                </td>
                                <td class="text-center align-middle" style="width: 120px;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateQuantity('${key}', -1)">-</button>
                                    <span class="mx-2 fw-semibold">${item.quantity}</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateQuantity('${key}', 1)">+</button>
                                </td>
                                <td class="text-end align-middle fw-bold small">Rp ${formatRupiah(totalPrice)}</td>
                            `;
                            cartBody.appendChild(row);
                        });
                    }
                }

                safeSetText('cart-subtotal', `Rp ${formatRupiah(activeOrderSubtotal)}`);
                safeSetText('cart-total', `Rp ${formatRupiah(activeOrderSubtotal)}`);

                // 4. Hitung Multi-Order Grand Total & Render Summary Box
                let grandTotal = 0;
                if (isMultiOrder) {
                    if (multiSummaryList) {
                        multiSummaryList.innerHTML = '';
                        orders.forEach((ord, idx) => {
                            let ordTotal = 0;
                            let ordQty = 0;
                            Object.values(ord.items || {}).forEach(it => {
                                ordTotal += it.price * it.quantity;
                                ordQty += it.quantity;
                            });
                            grandTotal += ordTotal;

                            const custName = ord.customerName || `Pelanggan ${idx + 1}`;
                            const line = document.createElement('div');
                            line.className = 'd-flex justify-content-between text-muted py-1 border-bottom border-light';
                            line.innerHTML = `
                                <span>${idx + 1}. <strong>${custName}</strong> (${ordQty} item)</span>
                                <span class="fw-semibold text-dark">Rp ${formatRupiah(ordTotal)}</span>
                            `;
                            multiSummaryList.appendChild(line);
                        });
                    }
                    safeSetText('multi-order-grand-total', `Rp ${formatRupiah(grandTotal)}`);
                } else {
                    grandTotal = activeOrderSubtotal;
                }

                const totalHidden = safeEl('total_amount_hidden');
                if (totalHidden) totalHidden.value = grandTotal;

                // 5. Serialize ke Hidden Inputs Form
                const hiddenItemsDiv = safeEl('hidden-items');
                if (hiddenItemsDiv) {
                    hiddenItemsDiv.innerHTML = '';
                    if (isMultiOrder) {
                        orders.forEach((ord, ordIdx) => {
                            hiddenItemsDiv.innerHTML += `
                                <input type="hidden" name="orders[${ordIdx}][customer_id]" value="${ord.customerId || ''}">
                                <input type="hidden" name="orders[${ordIdx}][customer_name]" value="${escapeHtml(ord.customerName || '')}">
                            `;
                            let itemIdx = 0;
                            let ordSubtotal = 0;
                            Object.values(ord.items || {}).forEach(it => {
                                const itTotal = it.price * it.quantity;
                                ordSubtotal += itTotal;
                                hiddenItemsDiv.innerHTML += `
                                    <input type="hidden" name="orders[${ordIdx}][items][${itemIdx}][product_id]" value="${it.productId}">
                                    <input type="hidden" name="orders[${ordIdx}][items][${itemIdx}][product_variant_id]" value="${it.variantId}">
                                    <input type="hidden" name="orders[${ordIdx}][items][${itemIdx}][quantity]" value="${it.quantity}">
                                    <input type="hidden" name="orders[${ordIdx}][items][${itemIdx}][unit_price]" value="${it.price}">
                                    <input type="hidden" name="orders[${ordIdx}][items][${itemIdx}][total_price]" value="${itTotal}">
                                `;
                                itemIdx++;
                            });

                            hiddenItemsDiv.innerHTML += `
                                <input type="hidden" name="orders[${ordIdx}][total_amount]" value="${ordSubtotal}">
                            `;
                        });
                    } else {
                        hiddenItemsDiv.innerHTML += `
                            <input type="hidden" name="customer_id" value="${orders[0].customerId || ''}">
                            <input type="hidden" name="customer_name" value="${escapeHtml(orders[0].customerName || '')}">
                        `;
                        let itemIdx = 0;
                        Object.values(orders[0].items || {}).forEach(it => {
                            const itTotal = it.price * it.quantity;
                            hiddenItemsDiv.innerHTML += `
                                <input type="hidden" name="items[${itemIdx}][product_id]" value="${it.productId}">
                                <input type="hidden" name="items[${itemIdx}][product_variant_id]" value="${it.variantId}">
                                <input type="hidden" name="items[${itemIdx}][quantity]" value="${it.quantity}">
                                <input type="hidden" name="items[${itemIdx}][unit_price]" value="${it.price}">
                                <input type="hidden" name="items[${itemIdx}][total_price]" value="${itTotal}">
                            `;
                            itemIdx++;
                        });
                    }
                }
            } catch (err) {
                console.error("[POS ERROR in renderCart]", err);
            }
        }

        // ==============================================
        // EVENT LISTENER PRODUK (mendukung produk & varian)
        // ==============================================
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function() {
                const id = this.dataset.id;
                const variantId = this.dataset.variantId;
                const name = this.dataset.name;
                const price = this.dataset.price;
                addToCart(id, variantId, name, price);
            });
        });

        // ==============================================
        // SEARCH PRODUK
        // ==============================================
        document.getElementById('product-search').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.product-card').forEach(card => {
                const productName = card.dataset.name.toLowerCase();
                if (productName.includes(filter)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // ==============================================
        // SEARCH PELANGGAN
        // ==============================================
        const customerSearch = document.getElementById('customer_search');
        const customerResults = document.getElementById('customer-results');
        const customerIdInput = document.getElementById('customer_id');
        const clearCustomerBtn = document.getElementById('clear-customer');

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        // ==============================================
        // SEARCH PELANGGAN
        // ==============================================
        const customerSearch = document.getElementById('customer_search');
        const customerResults = document.getElementById('customer-results');
        const customerIdInput = document.getElementById('customer_id');
        const clearCustomerBtn = document.getElementById('clear-customer');

        customerSearch.addEventListener('focus', function() {
            customerResults.classList.add('show');
        });

        customerSearch.addEventListener('input', function() {
            const rawVal = this.value.trim();
            const filter = rawVal.toLowerCase();
            const items = customerResults.querySelectorAll('.customer-item:not(#quick-add-from-search)');

            items.forEach(item => {
                const name = (item.dataset.name || '').toLowerCase();
                if (name.includes(filter)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });

            const quickSearchBtn = document.getElementById('quick-add-from-search');
            const typedSpan = document.getElementById('typed-customer-name');
            if (quickSearchBtn && typedSpan) {
                if (rawVal.length > 0 && filter !== 'pelanggan umum') {
                    typedSpan.textContent = rawVal;
                    quickSearchBtn.classList.remove('d-none');
                } else {
                    quickSearchBtn.classList.add('d-none');
                }
            }

            orders[activeOrderIndex].customerName = rawVal;
            orders[activeOrderIndex].customerId = '';
            if (customerIdInput) customerIdInput.value = '';
            const cNameInput = safeEl('customer_name_input');
            if (cNameInput) cNameInput.value = rawVal;

            customerResults.classList.add('show');
            renderCart();
        });

        // Klik shortcut tambah toko dari pencarian
        const quickSearchBtn = document.getElementById('quick-add-from-search');
        if (quickSearchBtn) {
            quickSearchBtn.addEventListener('click', function() {
                const typedName = (customerSearch.value || '').trim();
                const quickNameInput = document.getElementById('quick_customer_name');
                if (quickNameInput) {
                    quickNameInput.value = typedName;
                }
                customerResults.classList.remove('show');
                const modalEl = document.getElementById('quickAddCustomerModal');
                if (modalEl) {
                    const modalInstance = new bootstrap.Modal(modalEl);
                    modalInstance.show();
                    setTimeout(() => {
                        const phoneInput = document.getElementById('quick_customer_phone');
                        if (phoneInput) phoneInput.focus();
                    }, 400);
                }
            });
        }

        if (customerResults) {
            customerResults.addEventListener('click', function(e) {
                if (e.target.closest('#quick-add-from-search')) {
                    return;
                }
                const item = e.target.closest('.customer-item');
                if (item) {
                    const customerId = item.dataset.id;
                    const customerName = item.dataset.name;

                    orders[activeOrderIndex].customerId = customerId;
                    orders[activeOrderIndex].customerName = customerName;

                    if (customerSearch) customerSearch.value = customerName;
                    if (customerIdInput) customerIdInput.value = customerId;
                    const cNameInput = safeEl('customer_name_input');
                    if (cNameInput) cNameInput.value = customerName;

                    customerResults.querySelectorAll('.customer-item').forEach(i => i.classList.remove('selected'));
                    item.classList.add('selected');
                    customerResults.classList.remove('show');

                    posLog("👤 PELANGGAN DIPILIH", {
                        order: `Pesanan ${activeOrderIndex + 1}`,
                        customerId: customerId || 'Umum',
                        customerName: customerName
                    });

                    renderCart();
                }
            });
        }

        if (clearCustomerBtn) {
            clearCustomerBtn.addEventListener('click', function() {
                orders[activeOrderIndex].customerId = '';
                orders[activeOrderIndex].customerName = '';
                if (customerSearch) customerSearch.value = '';
                if (customerIdInput) customerIdInput.value = '';
                const cNameInput = safeEl('customer_name_input');
                if (cNameInput) cNameInput.value = '';
                if (customerResults) {
                    customerResults.querySelectorAll('.customer-item').forEach(item => {
                        item.classList.remove('selected');
                        item.style.display = '';
                    });
                    const qSearch = document.getElementById('quick-add-from-search');
                    if (qSearch) qSearch.classList.add('d-none');
                    customerResults.classList.remove('show');
                }
                if (customerSearch) customerSearch.focus();

                posLog("👤 PELANGGAN DI-RESET (KOSONG)", {
                    order: `Pesanan ${activeOrderIndex + 1}`
                });

                renderCart();
            });
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.customer-dropdown') && customerResults) {
                customerResults.classList.remove('show');
            }
        });

        // ==============================================
        // QUICK ADD CUSTOMER MODAL (AJAX HANDLER)
        // ==============================================
        const quickModal = document.getElementById('quickAddCustomerModal');
        if (quickModal) {
            quickModal.addEventListener('show.bs.modal', function() {
                const targetLabel = document.getElementById('quick-add-order-target');
                if (targetLabel) {
                    targetLabel.textContent = `Pesanan ${activeOrderIndex + 1}`;
                }
                const typedName = customerSearch ? customerSearch.value.trim() : '';
                const nameInput = document.getElementById('quick_customer_name');
                if (nameInput && typedName && typedName.toLowerCase() !== 'pelanggan umum') {
                    nameInput.value = typedName;
                }
            });
        }

        const quickCustForm = document.getElementById('quickAddCustomerForm');
        if (quickCustForm) {
            quickCustForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const nameInput = document.getElementById('quick_customer_name');
                const phoneInput = document.getElementById('quick_customer_phone');
                const addressInput = document.getElementById('quick_customer_address');
                const alertBox = document.getElementById('quick-customer-alert');
                const spinner = document.getElementById('quick-customer-spinner');
                const saveIcon = document.getElementById('quick-customer-save-icon');
                const saveBtn = document.getElementById('btn-save-quick-customer');

                const nameVal = nameInput ? nameInput.value.trim() : '';
                const phoneVal = phoneInput ? phoneInput.value.trim() : '';
                const addressVal = addressInput ? addressInput.value.trim() : '';

                if (!nameVal || nameVal.toLowerCase() === 'pelanggan umum') {
                    if (alertBox) {
                        alertBox.textContent = 'Nama toko/pelanggan tidak boleh kosong atau "Pelanggan Umum".';
                        alertBox.classList.remove('d-none');
                    }
                    return;
                }

                if (alertBox) alertBox.classList.add('d-none');
                if (spinner) spinner.classList.remove('d-none');
                if (saveIcon) saveIcon.classList.add('d-none');
                if (saveBtn) saveBtn.disabled = true;

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                                      document.querySelector('input[name="_token"]')?.value;

                    const res = await fetch("{{ route('kasir.customers.store') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            name: nameVal,
                            phone: phoneVal || null,
                            address: addressVal || null
                        })
                    });

                    const data = await res.json();

                    if (res.ok && data.success && data.customer) {
                        const newCustomer = data.customer;

                        // Tambahkan item toko ke dropdown pencarian
                        if (customerResults) {
                            const newDiv = document.createElement('div');
                            newDiv.className = 'customer-item';
                            newDiv.dataset.id = newCustomer.id;
                            newDiv.dataset.name = newCustomer.name;
                            newDiv.dataset.phone = newCustomer.phone || '';
                            newDiv.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>${escapeHtml(newCustomer.name)}</span>
                                    ${newCustomer.phone ? `<small class="text-muted"><i class="fas fa-phone small me-1"></i>${escapeHtml(newCustomer.phone)}</small>` : ''}
                                </div>
                            `;
                            const optUmum = document.getElementById('opt-pelanggan-umum');
                            if (optUmum && optUmum.nextSibling) {
                                customerResults.insertBefore(newDiv, optUmum.nextSibling);
                            } else {
                                customerResults.appendChild(newDiv);
                            }
                        }

                        // Langsung pilih untuk pesanan aktif
                        orders[activeOrderIndex].customerId = newCustomer.id;
                        orders[activeOrderIndex].customerName = newCustomer.name;

                        if (customerSearch) customerSearch.value = newCustomer.name;
                        if (customerIdInput) customerIdInput.value = newCustomer.id;
                        const cNameInput = safeEl('customer_name_input');
                        if (cNameInput) cNameInput.value = newCustomer.name;

                        quickCustForm.reset();
                        const modalEl = document.getElementById('quickAddCustomerModal');
                        if (modalEl) {
                            const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                            modalInstance.hide();
                        }

                        renderCart();
                    } else {
                        let errorMsg = data.message || 'Gagal menyimpan pelanggan baru.';
                        if (data.errors) {
                            errorMsg = Object.values(data.errors).flat().join('<br>');
                        }
                        if (alertBox) {
                            alertBox.innerHTML = errorMsg;
                            alertBox.classList.remove('d-none');
                        }
                    }
                } catch (err) {
                    console.error("Error saving quick customer:", err);
                    if (alertBox) {
                        alertBox.textContent = 'Terjadi kesalahan saat menyimpan pelanggan baru.';
                        alertBox.classList.remove('d-none');
                    }
                } finally {
                    if (spinner) spinner.classList.add('d-none');
                    if (saveIcon) saveIcon.classList.remove('d-none');
                    if (saveBtn) saveBtn.disabled = false;
                }
            });
        }

        // D. Driver & Payment change logging
        const driverSelect = safeEl('driver_id');
        if (driverSelect) {
            driverSelect.addEventListener('change', function() {
                const optText = this.options[this.selectedIndex]?.text || '';
                posLog("🛵 DRIVER PENGANTAR DIPILIH", {
                    driverId: this.value,
                    driverName: optText
                });
                renderCart();
            });
        }

        const paymentSelect = safeEl('payment_method');
        if (paymentSelect) {
            paymentSelect.addEventListener('change', function() {
                const optText = this.options[this.selectedIndex]?.text || '';
                posLog("💳 METODE PEMBAYARAN DIPILIH", {
                    paymentMethod: this.value,
                    label: optText
                });
                renderCart();
            });
        }

        // E. Form Submit Validation
        const posForm = safeEl('pos-form');
        if (posForm) {
            posForm.addEventListener('submit', function(e) {
                const isMulti = orders.length > 1;
                const dSelect = safeEl('driver_id');
                const hasDriver = dSelect && dSelect.value !== '';
                const pmSel = safeEl('payment_method');
                const isKasbon = hasDriver || (pmSel && pmSel.value === 'kasbon');

                if (isMulti) {
                    // Mode Multi-Order: Cek driver
                    if (!hasDriver) {
                        e.preventDefault();
                        alert('Silakan pilih Driver Pengantar yang bertugas terlebih dahulu!');
                        if (dSelect) dSelect.focus();
                        return false;
                    }

                    // Cek apakah setiap pesanan memiliki produk & nama pelanggan
                    for (let i = 0; i < orders.length; i++) {
                        if (Object.keys(orders[i].items || {}).length === 0) {
                            e.preventDefault();
                            const cName = orders[i].customerName || `Pesanan ${i + 1}`;
                            alert(`${cName} masih kosong! Silakan pilih minimal 1 produk.`);
                            switchOrder(i);
                            return false;
                        }

                        // Cek nama customer (TIDAK BOLEH Pelanggan Umum atau kosong)
                        const ordCustName = (orders[i].customerName || '').trim();
                        const isPelangganUmum = !ordCustName || ordCustName.toLowerCase() === 'pelanggan umum';

                        if (isPelangganUmum) {
                            e.preventDefault();
                            alert(`⚠️ PERHATIAN: Pesanan ${i + 1} masih berstatus 'Pelanggan Umum'!\n\nSemua pesanan pengiriman driver wajib mencantumkan Nama Toko / Pelanggan yang jelas agar surat jalan dan kasbon dapat di-tracking.\n\nSilakan tentukan nama toko untuk Pesanan ${i + 1}.`);
                            switchOrder(i);
                            if (customerSearch) {
                                customerSearch.focus();
                                customerSearch.select();
                            }
                            return false;
                        }
                    }
                } else {
                    // Mode Single Order
                    if (Object.keys(orders[0].items || {}).length === 0) {
                        e.preventDefault();
                        alert('Keranjang masih kosong!');
                        return false;
                    }

                    if (!hasDriver) {
                        if (!pmSel || !pmSel.value) {
                            e.preventDefault();
                            alert('Silakan pilih Metode Pembayaran!');
                            if (pmSel) pmSel.focus();
                            return false;
                        }
                    }

                    // Jika kasbon atau pengiriman driver, WAJIB pelanggan spesifik
                    if (isKasbon) {
                        const custName = (orders[0].customerName || (customerSearch ? customerSearch.value : '')).trim();
                        const isPelangganUmum = !custName || custName.toLowerCase() === 'pelanggan umum';

                        if (isPelangganUmum) {
                            e.preventDefault();
                            alert('⚠️ PERHATIAN: Transaksi Kasbon / Pengiriman Driver WAJIB mencantumkan Nama Toko atau Pelanggan asli (tidak boleh Pelanggan Umum)!\n\nHal ini agar kasbon tidak lupa ditagih dan dapat di-tracking dengan jelas.\n\nSilakan pilih toko atau klik tombol "+ Toko Baru".');
                            if (customerSearch) {
                                customerSearch.focus();
                                customerSearch.select();
                            }
                            return false;
                        }
                    }
                }

                posLog("🚀 FORM DISUBMIT KE SERVER", {
                    mode: isMulti ? "Multi-Order Pengiriman Driver" : "Single-Order Reguler",
                    totalPesanan: orders.length,
                    grandTotal: safeEl('total_amount_hidden')?.value,
                    driverId: safeEl('driver_id')?.value || '-'
                });
            });
        }

        // ==============================================
        // 5. INITIALIZE
        // ==============================================
        posLog("🚀 POS INITIALIZED", {
            totalOrders: orders.length,
            activeOrder: activeOrderIndex + 1
        });
        renderCart();
    });

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
@endpush