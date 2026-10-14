<?php $__env->startSection('title', 'Point of Sale (POS)'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="breadcrumb-item active" aria-current="page">POS</li>
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
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<form action="<?php echo e(route('kasir.pos.store')); ?>" method="POST" id="pos-form">
    <?php echo csrf_field(); ?>
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
                    <input type="text" id="product-search" class="form-control" placeholder="Cari produk berdasarkan nama atau SKU...">
                </div>
                <div class="pos-product-grid">
                    <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    
                    <div class="card product-card mb-3 cursor-pointer shadow-sm"
                        data-id="<?php echo e($product->id); ?>"
                        data-variant-id=""
                        data-name="<?php echo e($product->name); ?>"
                        data-price="<?php echo e($product->base_price); ?>">
                        <div class="card-body p-3 text-center">
                            <h6 class="fw-bold mb-1"><?php echo e($product->name); ?></h6>
                            <div class="text-primary fw-bold">Rp <?php echo e(number_format($product->base_price, 0, ',', '.')); ?></div>
                            <small class="text-muted">Stok: <?php echo e($product->inventory->current_stock); ?> <?php echo e($product->unit); ?></small>
                        </div>
                    </div>

                    
                    <?php if($product->variants && $product->variants->count() > 0): ?>
                    <?php $__currentLoopData = $product->variants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $variant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                    $customPrice = $product->base_price + $variant->price_adjustment;
                    ?>
                    <div class="card product-card mb-3 cursor-pointer shadow-sm bg-light"
                        data-id="<?php echo e($product->id); ?>"
                        data-variant-id="<?php echo e($variant->id); ?>"
                        data-name="<?php echo e($product->name); ?> (<?php echo e($variant->variant_name); ?>)"
                        data-price="<?php echo e($customPrice); ?>">
                        <div class="card-body p-3 text-center">
                            <h6 class="fw-bold mb-1"><?php echo e($product->name); ?> <span class="badge bg-warning text-dark"><?php echo e($variant->variant_name); ?></span></h6>
                            <div class="text-danger fw-bold">Rp <?php echo e(number_format($customPrice, 0, ',', '.')); ?></div>
                            <small class="text-muted">Stok Master: <?php echo e($product->inventory->current_stock); ?></small>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['class' => 'pos-cart shadow-sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'pos-cart shadow-sm']); ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h5 class="card-title mb-0 fw-bold d-inline-block">Keranjang</h5>
                        <span id="order-mode-badge" class="badge bg-secondary ms-2 small">Pesanan Langsung</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="btn-add-delivery-order" title="Klik untuk menambah pesanan pelanggan lain dalam 1 pengiriman driver">
                        <i class="fas fa-plus-circle me-1"></i> + Tambah Transaksi ke Pengiriman
                    </button>
                </div>

                
                <div id="multi-order-banner" class="alert alert-info py-2 px-3 small d-none mb-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-truck-moving fs-5 me-2 text-primary"></i>
                        <div>
                            <strong>Mode Pengiriman Multi-Customer:</strong>
                            <div class="small">Pesanan otomatis dicatat sebagai <strong>Kasbon / Pending</strong> hingga driver kembali.</div>
                        </div>
                    </div>
                </div>

                
                <div id="order-tabs-wrapper" class="d-none mb-2">
                    <label class="form-label small text-muted mb-1 fw-bold">Pilih Pesanan Toko yang Sedang Diedit:</label>
                    <div class="d-flex gap-1 align-items-center flex-nowrap" id="order-tabs-list" style="overflow-x: auto; padding-bottom: 4px;">
                        <!-- Tab tombol pesanan & tombol tambah akan dirender oleh JavaScript -->
                    </div>
                </div>

                <hr class="mt-1 mb-3">

                
                <div class="mb-3">
                    <label for="customer_search" class="form-label fw-semibold small mb-1">
                        <i class="fas fa-user text-primary me-1"></i>Pelanggan (<span id="active-order-label">Pesanan 1</span>)
                    </label>
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
                            <?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="customer-item" data-id="<?php echo e($customer->id); ?>" data-name="<?php echo e($customer->name); ?>">
                                <?php echo e($customer->name); ?>

                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <small class="text-muted">Pilih pelanggan untuk pesanan yang sedang aktif</small>
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

                    
                    <div class="mb-3 d-none" id="driver-select-container">
                        <label for="driver_id" class="form-label fw-bold">
                            <i class="fas fa-motorcycle text-primary me-1"></i> Driver Pengantar <span class="text-danger">*</span>
                        </label>
                        <select name="driver_id" id="driver_id" class="form-select border-primary">
                            <option value="" disabled selected>-- Pilih Driver yang Mengantar --</option>
                            <?php if(isset($drivers) && $drivers->isNotEmpty()): ?>
                                <?php $__currentLoopData = $drivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $driver): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($driver->id); ?>"><?php echo e($driver->name); ?> (<?php echo e($driver->phone ?? 'Driver'); ?>)</option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php else: ?>
                                <option value="" disabled>Belum ada user role Driver</option>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">Pilih supir yang membawa pesanan ini</small>
                    </div>

                    
                    <div class="mb-3" id="payment-method-container">
                        <label for="payment_method" class="form-label fw-bold">Metode Pembayaran</label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="" disabled selected>-- Pilih Metode --</option>
                            <option value="kasbon" class="text-danger fw-bold">⚠️ BELUM LUNAS (KASBON)</option>
                            <?php $__currentLoopData = $paymentMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($method->slug); ?>">
                                <?php echo e($method->name); ?>

                            </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    
                    <div id="multi-order-payment-badge" class="d-none mb-3 text-center">
                        <div class="badge bg-danger bg-opacity-10 text-danger border border-danger w-100 py-2 fs-6">
                            <i class="fas fa-clock me-1"></i> METODE: KASBON / TAGIHAN PENDING
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-checkout" id="btn-submit-order">
                            <i class="fas fa-check-circle me-2"></i> PROSES PEMBAYARAN
                        </button>
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
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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
                            const custLabel = ord.customerName ? ord.customerName : `Pesanan ${idx + 1}`;
                            const isActive = idx === activeOrderIndex;

                            const btnGroup = document.createElement('div');
                            btnGroup.className = 'btn-group';
                            btnGroup.innerHTML = `
                                <button type="button" class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} text-nowrap fw-semibold" onclick="switchOrder(${idx})">
                                    <i class="fas fa-store me-1"></i> ${custLabel} <span class="badge ${isActive ? 'bg-light text-dark' : 'bg-secondary'} ms-1">${itemCount}</span>
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
                    safeSetVisible(driverContainer, false);
                    safeSetRequired(driverSelect, false);
                    safeSetVisible(paymentContainer, true);
                    safeSetRequired(paymentSelect, true);
                    safeSetVisible(multiPaymentBadge, false);
                    safeSetVisible(multiSummaryBox, false);

                    if (orderModeBadge) {
                        orderModeBadge.className = 'badge bg-secondary ms-2 small';
                        orderModeBadge.innerText = 'Pesanan Langsung';
                    }

                    if (btnAddDelivery) {
                        btnAddDelivery.innerHTML = `<i class="fas fa-plus-circle me-1"></i> + Tambah Transaksi ke Pengiriman`;
                    }

                    if (btnSubmit) {
                        btnSubmit.innerHTML = `<i class="fas fa-check-circle me-2"></i> PROSES PEMBAYARAN`;
                    }
                }

            // 2. Sinkronkan Customer Input dengan Order Aktif
                // 2. Sinkronkan Customer Input dengan Order Aktif
                const customerSearch = safeEl('customer_search');
                const customerIdInput = safeEl('customer_id');
                if (customerSearch) customerSearch.value = currentOrder.customerName || '';
                if (customerIdInput) customerIdInput.value = currentOrder.customerId || '';

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

        customerSearch.addEventListener('focus', function() {
            customerResults.classList.add('show');
        });

        customerSearch.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const items = customerResults.querySelectorAll('.customer-item');

            items.forEach(item => {
                const name = item.dataset.name.toLowerCase();
                if (name.includes(filter)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });

            customerResults.classList.add('show');
        });

        if (customerResults) {
            customerResults.addEventListener('click', function(e) {
                const item = e.target.closest('.customer-item');
                if (item) {
                    const customerId = item.dataset.id;
                    const customerName = item.dataset.name;

                    orders[activeOrderIndex].customerId = customerId;
                    orders[activeOrderIndex].customerName = customerName;

                    if (customerSearch) customerSearch.value = customerName;
                    if (customerIdInput) customerIdInput.value = customerId;

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
                if (customerResults) {
                    customerResults.querySelectorAll('.customer-item').forEach(item => {
                        item.classList.remove('selected');
                        item.style.display = '';
                    });
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

        // D. Driver & Payment change logging
        const driverSelect = safeEl('driver_id');
        if (driverSelect) {
            driverSelect.addEventListener('change', function() {
                const optText = this.options[this.selectedIndex]?.text || '';
                posLog("🛵 DRIVER PENGANTAR DIPILIH", {
                    driverId: this.value,
                    driverName: optText
                });
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
            });
        }

        // E. Form Submit Validation
        const posForm = safeEl('pos-form');
        if (posForm) {
            posForm.addEventListener('submit', function(e) {
                if (orders.length > 1) {
                    // Mode Multi-Order: Cek driver
                    const dSelect = safeEl('driver_id');
                    if (!dSelect || !dSelect.value) {
                        e.preventDefault();
                        alert('Silakan pilih Driver Pengantar yang bertugas terlebih dahulu!');
                        if (dSelect) dSelect.focus();
                        return false;
                    }

                    // Cek apakah setiap pesanan memiliki produk
                    for (let i = 0; i < orders.length; i++) {
                        if (Object.keys(orders[i].items || {}).length === 0) {
                            e.preventDefault();
                            const cName = orders[i].customerName || `Pesanan ${i + 1}`;
                            alert(`${cName} masih kosong! Silakan pilih minimal 1 produk.`);
                            switchOrder(i);
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

                    const pmSel = safeEl('payment_method');
                    if (!pmSel || !pmSel.value) {
                        e.preventDefault();
                        alert('Silakan pilih Metode Pembayaran!');
                        if (pmSel) pmSel.focus();
                        return false;
                    }
                }

                posLog("🚀 FORM DISUBMIT KE SERVER", {
                    mode: orders.length > 1 ? "Multi-Order Pengiriman Driver" : "Single-Order Reguler",
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
<?php $__env->stopPush(); ?>
<?php echo $__env->make('kasir.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/kasir/pos/index.blade.php ENDPATH**/ ?>