 

<?php $__env->startSection('title', 'Terima Stok Supplier'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="font-weight-bold text-dark">Penerimaan Stok dari Supplier</h2>
            <p class="text-muted">Gunakan form ini untuk menambah stok harian.</p>
        </div>
    </div>

    <?php if(session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo e(session('success')); ?>

        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if(session('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo e(session('error')); ?>

        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="<?php echo e(route('kasir.inventory.store_stock')); ?>" method="POST">
                <?php echo csrf_field(); ?>
                
                <!-- Toggle Record Expense -->
                <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 border">
                    <input class="form-check-input ms-0 me-3" type="checkbox" id="record_expense" name="record_expense" value="1" style="transform: scale(1.5);" <?php echo e(old('record_expense', '1') == '1' ? 'checked' : ''); ?>>
                    <label class="form-check-label font-weight-bold pt-1" for="record_expense">Catat Otomatis ke Pengeluaran (Cash Flow)</label>
                    <small class="d-block text-muted ms-5">Matikan sakelar ini jika Anda <b>sudah/lupa</b> mencatat pengeluaran di menu Expenses secara manual.</small>
                </div>

                <h5 class="font-weight-bold mb-3 border-bottom pb-2">Informasi Pembelian</h5>
                <div class="row">
                    <!-- Dropdown Supplier -->
                    <div class="col-md-6 mb-3">
                        <label for="supplier_id" class="form-label font-weight-bold">Pilih Supplier / Pabrik</label>
                        <select class="form-select form-select-lg <?php $__errorArgs = ['supplier_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="supplier_id" name="supplier_id" required>
                            <option value="" disabled selected>-- Pilih Supplier --</option>
                            <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($supplier->id); ?>" <?php echo e(old('supplier_id') == $supplier->id ? 'selected' : ''); ?>>
                                    <?php echo e($supplier->name); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <?php $__errorArgs = ['supplier_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <div class="invalid-feedback"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <!-- Dropdown Produk Dinamis -->
                    <div class="col-md-6 mb-3">
                        <label for="product_id" class="form-label font-weight-bold">Pilih Produk Es Kristal</label>
                        <select class="form-select form-select-lg <?php $__errorArgs = ['product_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="product_id" name="product_id" required disabled>
                            <option value="" disabled selected>-- Pilih Supplier Terlebih Dahulu --</option>
                        </select>
                        <?php $__errorArgs = ['product_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <div class="invalid-feedback"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="quantity" class="form-label font-weight-bold">Jumlah Masuk (Ball/Pcs)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="fas fa-boxes"></i></span>
                            <input type="number" class="form-control <?php $__errorArgs = ['quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="quantity" name="quantity" value="<?php echo e(old('quantity')); ?>" min="1" required placeholder="Contoh: 30">
                        </div>
                        <?php $__errorArgs = ['quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                </div>

                <!-- Bagian Pembayaran (Bisa Disembunyikan oleh Toggle) -->
                <div id="payment_section" class="mt-4">
                    <h5 class="font-weight-bold mb-3 border-bottom pb-2">Informasi Pembayaran (Opsional)</h5>
                    <div class="row align-items-end">
                        <div class="col-md-6 mb-3">
                            <label for="payment_method" class="form-label font-weight-bold">Metode Pembayaran</label>
                            <select class="form-select form-select-lg <?php $__errorArgs = ['payment_method'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="payment_method" name="payment_method">
                                <option value="" disabled selected>-- Pilih Pembayaran --</option>
                                <?php $__currentLoopData = $paymentMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($pm->slug); ?>" <?php echo e(old('payment_method') == $pm->slug ? 'selected' : ''); ?>>
                                        <?php echo e($pm->name); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <?php $__errorArgs = ['payment_method'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="invalid-feedback"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-danger">Total Pengeluaran</label>
                            <input type="text" class="form-control form-control-lg bg-light fw-bold text-danger" id="grand_total_display" readonly value="Rp 0">
                        </div>
                    </div>
                </div>

                <div class="mb-4 mt-3">
                    <label for="notes" class="form-label font-weight-bold">Catatan (Opsional)</label>
                    <textarea class="form-control <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="notes" name="notes" rows="2" placeholder="Catatan pengiriman/stok..."><?php echo e(old('notes')); ?></textarea>
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
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const suppliers = <?php echo json_encode($suppliers, 15, 512) ?>;
        
        const supplierSelect = document.getElementById('supplier_id');
        const productSelect = document.getElementById('product_id');
        const qtyInput = document.getElementById('quantity');
        const totalDisplay = document.getElementById('grand_total_display');
        const paymentSection = document.getElementById('payment_section');
        const paymentMethodSelect = document.getElementById('payment_method');
        const recordExpenseToggle = document.getElementById('record_expense');

        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
        }

        function calculateTotal() {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const price = selectedOption && selectedOption.value ? parseFloat(selectedOption.getAttribute('data-price')) : 0;
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

        productSelect.addEventListener('change', calculateTotal);
        qtyInput.addEventListener('input', calculateTotal);

        // Inisialisasi awal
        togglePaymentSection();
        
        // Retain old value
        if(supplierSelect.value) {
            supplierSelect.dispatchEvent(new Event('change'));
            const oldProductId = '<?php echo e(old('product_id')); ?>';
            if(oldProductId) {
                setTimeout(() => {
                    productSelect.value = oldProductId;
                    calculateTotal();
                }, 100);
            }
        }
    });
</script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('kasir.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/kasir/inventory/add_stock.blade.php ENDPATH**/ ?>