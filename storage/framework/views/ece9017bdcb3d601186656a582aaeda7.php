<?php $__env->startSection('title', 'Tutup Shift - Stock Opname'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="font-weight-bold text-dark">Laporan Tutup Shift (Stock Opname)</h2>
                <p class="text-muted">Hitung dan masukkan sisa fisik es di kulkas. Sistem akan menyesuaikan selisihnya secara otomatis.</p>
            </div>
            <div class="text-end">
                <span class="badge bg-primary fs-6 p-2"><i class="far fa-clock me-1"></i> <?php echo e(now()->format('d M Y, H:i')); ?></span>
            </div>
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
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo e(session('error')); ?>

        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="<?php echo e(route('kasir.inventory.process_stock_opname')); ?>" method="POST" id="opnameForm">
                <?php echo csrf_field(); ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Produk</th>
                                <th class="text-center">Stok di Sistem</th>
                                <th class="text-center" width="200">Fisik di Kulkas Saat Ini</th>
                                <th>Catatan / Alasan Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td>
                                    <h6 class="mb-0 fw-bold"><?php echo e($product->name); ?></h6>
                                    <small class="text-muted"><?php echo e($product->unit); ?></small>
                                    <input type="hidden" name="items[<?php echo e($index); ?>][inventory_id]" value="<?php echo e($product->inventory->id); ?>">
                                    <input type="hidden" name="items[<?php echo e($index); ?>][sys_stock]" value="<?php echo e($product->inventory->current_stock); ?>">
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-secondary fs-6"><?php echo e($product->inventory->current_stock); ?></span>
                                    <input type="hidden" id="sys_stock_<?php echo e($index); ?>" value="<?php echo e($product->inventory->current_stock); ?>">
                                </td>

                                <td>
                                    <div class="input-group">
                                        <input type="number"
                                            class="form-control form-control-lg text-center fw-bold actual-stock-input"
                                            name="items[<?php echo e($index); ?>][actual_stock]"
                                            value="<?php echo e($product->inventory->current_stock); ?>"
                                            min="0" required
                                            data-index="<?php echo e($index); ?>">
                                    </div>
                                </td>

                                <td>
                                    <input type="text" id="notes_<?php echo e($index); ?>" class="form-control" name="items[<?php echo e($index); ?>][notes]" placeholder="Opsional (Misal: 1 ball cair)">
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info mt-3 border-0">
                    <i class="fas fa-info-circle me-2"></i> <strong>SOP Kasir:</strong> Jika angka di sistem dan fisik berbeda, pastikan Anda menuliskan catatan (misalnya "cair", "pecah", atau "lupa input terima barang").
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5 rounded-3 fw-bold" onclick="return confirm('Apakah Anda yakin data fisik yang dimasukkan sudah benar?')">
                        <i class="fas fa-save me-2"></i> Simpan Laporan Tutup Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.actual-stock-input');

        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const index = this.getAttribute('data-index');
                const sysStock = parseInt(document.getElementById(`sys_stock_${index}`).value);
                const actualStock = parseInt(this.value);
                const notesInput = document.getElementById(`notes_${index}`);
                const tr = this.closest('tr');

                if (sysStock !== actualStock && !isNaN(actualStock)) {
                    // Jika ada selisih, baris jadi kuning dan catatan WAJIB diisi
                    tr.classList.add('table-warning');
                    notesInput.required = true;
                    notesInput.placeholder = "WAJIB DIISI! (Misal: minus 3 ball belum tercatat)";
                    notesInput.classList.add('is-invalid'); // Tambah efek merah bootstrap
                } else {
                    // Jika sama, kembali normal
                    tr.classList.remove('table-warning');
                    notesInput.required = false;
                    notesInput.placeholder = "Opsional (Misal: 1 ball cair)";
                    notesInput.classList.remove('is-invalid');
                }
            });
        });
    });
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('kasir.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/kasir/inventory/stock_opname.blade.php ENDPATH**/ ?>