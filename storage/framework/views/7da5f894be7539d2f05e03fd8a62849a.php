<?php $__env->startSection('title', 'Edit Transaksi #' . $transaction->id); ?>
<?php $__env->startSection('breadcrumb'); ?>
<li class="breadcrumb-item"><a href="<?php echo e(route('kasir.transactions.index')); ?>">Riwayat Transaksi</a></li>
<li class="breadcrumb-item active">Edit</li>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
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
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<form action="<?php echo e($update_route ?? route('kasir.transactions.update', $transaction->id)); ?>" method="POST" id="pos-form">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PUT'); ?>
    <div class="row g-4">
        
        <div class="col-lg-7">
            <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="product-search" class="form-control" placeholder="Cari produk untuk ditambahkan...">
                </div>
                <div class="pos-product-grid">
                    <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="card product-card" data-id="<?php echo e($product->id); ?>" data-name="<?php echo e($product->name); ?>" data-price="<?php echo e($product->base_price); ?>">
                        <div class="card-body text-center p-2">
                            <h6 class="card-title small fw-bold mb-1"><?php echo e($product->name); ?></h6>
                            <p class="card-text small fw-bold" style="color: var(--kasir-accent);">Rp <?php echo e(number_format($product->base_price, 0, ',', '.')); ?></p>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-muted">Tidak ada produk yang tersedia.</p>
                    <?php endif; ?>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $attributes = $__attributesOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__attributesOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $component = $__componentOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__componentOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
        </div>

        
        <div class="col-lg-5">
            <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['class' => 'pos-cart']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'pos-cart']); ?>
                <h5 class="card-title">Edit Keranjang</h5>
                <hr>
                <div class="mb-3">
                    <label for="customer_id" class="form-label">Pelanggan</label>
                    <select name="customer_id" id="customer_id" class="form-select">
                        <option value="">-- Pelanggan Umum --</option>
                        <?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($customer->id); ?>" <?php echo e($transaction->customer_id == $customer->id ? 'selected' : ''); ?>><?php echo e($customer->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="cart-items mb-3 table-responsive">
                    <table class="table table-sm">
                        <tbody id="cart-items-body"></tbody>
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
                            <option value="" disabled>-- Pilih Metode --</option>

                            <?php $__currentLoopData = $paymentMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($method->slug); ?>"
                                <?php echo e((old('payment_method', $transaction->payment_method) == $method->slug) ? 'selected' : ''); ?>>
                                <?php echo e($method->name); ?>

                            </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                            
                            <?php if(! $paymentMethods->contains('slug', $transaction->payment_method)): ?>
                            <option value="<?php echo e($transaction->payment_method); ?>" selected>
                                <?php echo e(ucfirst($transaction->payment_method)); ?> (Arsip)
                            </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-checkout"><i class="fas fa-save me-2"></i> SIMPAN PERUBAHAN</button>
                    </div>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $attributes = $__attributesOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__attributesOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $component = $__componentOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__componentOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
        </div>
    </div>
</form>


<div id="initial-transaction-data" data-items="<?php echo e(json_encode($transaction->details)); ?>" class="d-none"></div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        let cart = {};

        function addToCart(productId, name, price) {
            if (cart[productId]) {
                cart[productId].quantity = parseInt(cart[productId].quantity) + 1;
            } else {
                cart[productId] = {
                    name: name,
                    price: parseFloat(price),
                    quantity: 1
                };
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
                    <td class="align-middle" style="width: 140px;">
                        <div class="d-flex align-items-center justify-content-center">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2" onclick="updateQuantity(${productId}, -1)">-</button>
                            <span class="mx-3 fw-bold" style="min-width: 20px; text-align: center;">${item.quantity}</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2" onclick="updateQuantity(${productId}, 1)">+</button>
                        </div>
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
                // [PERBAIKAN LOGIKA] Pastikan quantity dibaca sebagai Integer sebelum ditambah/dikurang
                cart[productId].quantity = parseInt(cart[productId].quantity) + parseInt(change);

                if (cart[productId].quantity <= 0) {
                    delete cart[productId];
                }
                renderCart();
            }
        }

        function formatRupiah(angka) {
            // Mengatasi NaN, pastikan selalu berupa angka yang valid
            if (isNaN(angka)) return "0";
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
                productName.includes(filter) ? card.style.display = '' : card.style.display = 'none';
            });
        });

        // Memuat data awal dari elemen HTML ke keranjang
        const initialDataEl = document.getElementById('initial-transaction-data');
        if (initialDataEl && initialDataEl.dataset.items) {
            const initialItems = JSON.parse(initialDataEl.dataset.items);
            initialItems.forEach(item => {
                cart[item.product_id] = {
                    name: item.product.name,
                    price: parseFloat(item.unit_price),
                    // [PERBAIKAN LOGIKA] Pastikan data dari database dikonversi ke Integer (bukan String)
                    quantity: parseInt(item.quantity)
                };
            });
        }

        renderCart(); // Render keranjang dengan data awal
    });
</script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('kasir.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/kasir/transactions/edit.blade.php ENDPATH**/ ?>