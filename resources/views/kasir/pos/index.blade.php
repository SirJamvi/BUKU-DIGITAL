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
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
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
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
                        <div class="card product-card" 
                             data-id="{{ $product->id }}" 
                             data-name="{{ $product->name }}" 
                             data-price="{{ $product->base_price }}">
                            <div class="card-body text-center p-2">
                                <h6 class="card-title small fw-bold mb-1">{{ $product->name }}</h6>
                                <p class="card-text small fw-bold" style="color: var(--kasir-accent);">Rp {{ number_format($product->base_price, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">Tidak ada produk yang tersedia.</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        {{-- Kolom Kanan: Keranjang & Pembayaran --}}
        <div class="col-lg-5">
            <x-card class="pos-cart">
                <h5 class="card-title">Keranjang</h5>
                <hr>
                
                {{-- ============================================== --}}
                {{-- PERUBAHAN BARU: Custom search untuk pelanggan --}}
                {{-- ============================================== --}}
                <div class="mb-3">
                    <label for="customer_search" class="form-label">Pelanggan</label>
                    <div class="customer-dropdown">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" 
                                   id="customer_search" 
                                   class="form-control" 
                                   placeholder="Cari pelanggan berdasarkan nama..."
                                   autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="clear-customer">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="hidden" name="customer_id" id="customer_id" value="">
                        <div class="customer-search-results" id="customer-results">
                            <div class="customer-item" data-id="" data-name="Pelanggan Umum">
                                <strong>Pelanggan Umum</strong>
                            </div>
                            @foreach ($customers as $customer)
                                <div class="customer-item" data-id="{{ $customer->id }}" data-name="{{ $customer->name }}">
                                    {{ $customer->name }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <small class="text-muted">Pilih pelanggan atau kosongkan untuk umum</small>
                </div>
                {{-- ============================================== --}}
                
                <div class="cart-items mb-3 table-responsive">
                    <table class="table table-sm">
                        <tbody id="cart-items-body">
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-auto">
                   <hr>
                    <div>
                        <div class="d-flex justify-content-between">
                            <h6 class="text-muted">Subtotal</h6>
                            <h6 id="cart-subtotal">Rp 0</h6>
                        </div>
                        <div class="d-flex justify-content-between">
                            <h5 class="fw-bold">Total</h5>
                            <h5 class="fw-bold" id="cart-total" style="color: var(--kasir-accent);">Rp 0</h5>
                        </div>
                    </div>

                    <div id="hidden-items"></div>
                    <input type="hidden" name="total_amount" id="total_amount_hidden" value="0">
                    
                    <hr>
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Metode Pembayaran</label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="Cash">Tunai (Cash)</option>
                            <option value="QRIS">QRIS</option>
                            <option value="Debit">Debit</option>
                            <option value="Credit Card">Kartu Kredit</option>
                        </select>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-checkout">
                            <i class="fas fa-check-circle me-2"></i> PROSES PEMBAYARAN
                        </button>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ==============================================
        // KERANJANG BELANJA
        // ==============================================
        let cart = {};

        function addToCart(productId, name, price) {
            if (cart[productId]) {
                cart[productId].quantity++;
            } else {
                cart[productId] = { name: name, price: parseFloat(price), quantity: 1 };
            }
            renderCart();
        }

        function renderCart() {
            const cartBody = document.getElementById('cart-items-body');
            const hiddenItemsDiv = document.getElementById('hidden-items');
            cartBody.innerHTML = '';
            hiddenItemsDiv.innerHTML = '';
            let subtotal = 0;
            let itemIndex = 0;

            if (Object.keys(cart).length === 0) {
                cartBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Keranjang kosong</td></tr>';
            }

            for (const productId in cart) {
                const item = cart[productId];
                const totalPrice = item.price * item.quantity;
                subtotal += totalPrice;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <span class="fw-bold small">${item.name}</span><br>
                        <small class="text-muted">Rp ${formatRupiah(item.price)}</small>
                    </td>
                    <td class="text-center align-middle" style="width: 120px;">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${productId}, -1)">-</button>
                        <span class="mx-2">${item.quantity}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${productId}, 1)">+</button>
                    </td>
                    <td class="text-end align-middle fw-bold small">Rp ${formatRupiah(totalPrice)}</td>
                `;
                cartBody.appendChild(row);

                hiddenItemsDiv.innerHTML += `
                    <input type="hidden" name="items[${itemIndex}][product_id]" value="${productId}">
                    <input type="hidden" name="items[${itemIndex}][quantity]" value="${item.quantity}">
                    <input type="hidden" name="items[${itemIndex}][unit_price]" value="${item.price}">
                    <input type="hidden" name="items[${itemIndex}][total_price]" value="${totalPrice}">
                `;
                itemIndex++;
            }

            document.getElementById('cart-subtotal').innerText = `Rp ${formatRupiah(subtotal)}`;
            document.getElementById('cart-total').innerText = `Rp ${formatRupiah(subtotal)}`;
            document.getElementById('total_amount_hidden').value = subtotal;
        }

        window.updateQuantity = function(productId, change) {
            if (cart[productId]) {
                cart[productId].quantity += change;
                if (cart[productId].quantity <= 0) {
                    delete cart[productId];
                }
                renderCart();
            }
        }

        function formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID').format(angka);
        }

        // ==============================================
        // EVENT LISTENER PRODUK (PERBAIKAN: menggunakan vanilla JS)
        // ==============================================
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function() {
                const id = this.dataset.id;
                const name = this.dataset.name;
                const price = this.dataset.price;
                addToCart(id, name, price);
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
        // SEARCH PELANGGAN (BARU: Custom Implementation)
        // ==============================================
        const customerSearch = document.getElementById('customer_search');
        const customerResults = document.getElementById('customer-results');
        const customerIdInput = document.getElementById('customer_id');
        const clearCustomerBtn = document.getElementById('clear-customer');

        // Show results when input is focused or typed
        customerSearch.addEventListener('focus', function() {
            customerResults.classList.add('show');
        });

        customerSearch.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const items = customerResults.querySelectorAll('.customer-item');
            let hasResults = false;

            items.forEach(item => {
                const name = item.dataset.name.toLowerCase();
                if (name.includes(filter)) {
                    item.style.display = '';
                    hasResults = true;
                } else {
                    item.style.display = 'none';
                }
            });

            customerResults.classList.add('show');
        });

        // Select customer
        customerResults.addEventListener('click', function(e) {
            const item = e.target.closest('.customer-item');
            if (item) {
                const customerId = item.dataset.id;
                const customerName = item.dataset.name;
                
                customerSearch.value = customerName;
                customerIdInput.value = customerId;
                
                // Remove previous selected
                customerResults.querySelectorAll('.customer-item').forEach(i => {
                    i.classList.remove('selected');
                });
                item.classList.add('selected');
                
                customerResults.classList.remove('show');
            }
        });

        // Clear customer selection
        clearCustomerBtn.addEventListener('click', function() {
            customerSearch.value = '';
            customerIdInput.value = '';
            customerResults.querySelectorAll('.customer-item').forEach(item => {
                item.classList.remove('selected');
                item.style.display = '';
            });
            customerResults.classList.remove('show');
            customerSearch.focus();
        });

        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.customer-dropdown')) {
                customerResults.classList.remove('show');
            }
        });

        // ==============================================
        // INITIALIZE
        // ==============================================
        renderCart();
    });

    // ==============================================
    // FIX: Reload page after back navigation
    // ==============================================
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
@endpush