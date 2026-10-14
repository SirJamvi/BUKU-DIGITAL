<?php $__env->startSection('title', 'Edit Transaksi #' . $transaction->id); ?>
<?php $__env->startSection('breadcrumb'); ?>
<li class="breadcrumb-item"><a href="<?php echo e(route('admin.transactions.index')); ?>">Transaksi</a></li>
<li class="breadcrumb-item active">Edit</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<?php echo $__env->make('kasir.transactions.edit', [
'transaction' => $transaction,
'products' => $products,
'customers' => $customers,
// [BARU] Kirimkan rute update khusus untuk Admin
'update_route' => route('admin.transactions.update', $transaction->id)
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/admin/transactions/edit.blade.php ENDPATH**/ ?>