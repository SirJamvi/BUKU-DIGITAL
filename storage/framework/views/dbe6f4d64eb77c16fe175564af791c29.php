<!DOCTYPE html>
<html>
<head>
    <title>Laporan Keuangan</title>
    <style>
        body { font-family: 'sans-serif'; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #dddddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1, h4 { text-align: center; }
        .summary { display: table; width: 100%; margin-bottom: 20px; }
        .summary-item { display: table-cell; width: 25%; text-align: center; border: 1px solid #eee; padding: 10px; }
        .text-end { text-align: right; }
        .text-success { color: green; }
        .text-danger { color: red; }
    </style>
</head>
<body>
    <h1>Laporan Keuangan</h1>

    
    <?php if(isset($reportData['filters']['start_date']) && !empty($reportData['filters']['start_date'])): ?>
        <h4>Periode: <?php echo e(\Carbon\Carbon::parse($reportData['filters']['start_date'])->isoFormat('D MMM YYYY')); ?> - <?php echo e(\Carbon\Carbon::parse($reportData['filters']['end_date'])->isoFormat('D MMM YYYY')); ?></h4>
    <?php else: ?>
        <h4>Periode: Semua Waktu</h4>
    <?php endif; ?>
    

    <div class="summary">
        <div class="summary-item">
            <p>Total Pemasukan</p>
            <strong>Rp <?php echo e(number_format($reportData['total_income'] ?? 0, 0, ',', '.')); ?></strong>
        </div>
        <div class="summary-item">
            <p>Keuntungan Kotor</p>
            <strong>Rp <?php echo e(number_format($reportData['total_gross_profit'] ?? 0, 0, ',', '.')); ?></strong>
        </div>
        <div class="summary-item">
            <p>Total Pengeluaran</p>
            <strong>Rp <?php echo e(number_format($reportData['total_expense'] ?? 0, 0, ',', '.')); ?></strong>
        </div>
        <div class="summary-item">
            <p>Keuntungan Bersih</p>
            <strong>Rp <?php echo e(number_format($reportData['net_profit'] ?? 0, 0, ',', '.')); ?></strong>
        </div>
    </div>

    <h4>Rincian Arus Kas</h4>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Deskripsi</th>
                <th>Kategori</th>
                <th class="text-end">Pemasukan</th>
                <th class="text-end">Pengeluaran</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $reportData['cash_flows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $flow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e(\Carbon\Carbon::parse($flow->date)->isoFormat('D MMM YYYY')); ?></td>
                    <td><?php echo e($flow->description); ?></td>
                    <td><?php echo e($flow->category->name ?? 'N/A'); ?></td>
                    <td class="text-end text-success">
                        <?php echo e($flow->type == 'income' ? 'Rp ' . number_format($flow->amount, 0, ',', '.') : '-'); ?>

                    </td>
                    <td class="text-end text-danger">
                        <?php echo e($flow->type == 'expense' ? 'Rp ' . number_format($flow->amount, 0, ',', '.') : '-'); ?>

                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="5" style="text-align: center;">Tidak ada data.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/admin/reports/financial_pdf.blade.php ENDPATH**/ ?>