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
                <div class="mb-3">
                    <label for="customer_id" class="form-label">Pelanggan</label>
                    <select name="customer_id" id="customer_id" class="form-select">
                        <option value="">-- Pelanggan Umum --</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cart-items mb-3 table-responsive">
                    <table class="table table-sm">
                        <tbody id="cart-items-body">
                            {{-- Item di keranjang akan muncul di sini via JavaScript --}}
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

        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function() {
                const id = this.dataset.id;
                const name = this.dataset.name;
                const price = this.dataset.price;
                addToCart(id, name, price);
            });
        });
        
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
        
        renderCart(); // Panggil sekali saat load untuk menampilkan "Keranjang kosong"
    });
</script>
@endpush