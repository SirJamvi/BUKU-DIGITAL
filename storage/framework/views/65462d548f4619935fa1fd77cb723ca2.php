<?php $__env->startSection('title', 'Dashboard Alokasi Dana'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active" aria-current="page">Alokasi Dana</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div id="allocation-data-container" data-allocation='<?php echo json_encode($allocationData, 15, 512) ?>'></div>

    <div class="container-fluid">
        <?php echo $__env->make('components.alert', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        
        <?php if($allocationData['view_type'] === 'pending'): ?>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="fas fa-exclamation-triangle me-2"></i>Keuntungan Bersih Siap Alokasi</h5>
                            <h1 class="display-5 fw-bold">Rp <?php echo e(number_format($allocationData['net_profit_to_allocate'] ?? $allocationData['net_profit'] ?? 0, 0, ',', '.')); ?></h1>
                            <p class="mb-2">Total keuntungan bersih yang siap untuk dialokasikan dari periode yang belum dialokasikan.</p>
                            <form action="<?php echo e(route('admin.fund-allocation.process')); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <?php $__currentLoopData = $allocationData['pending_profits']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $profit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <input type="hidden" name="owner_profit_ids[]" value="<?php echo e($profit->id); ?>">
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle me-2"></i> Proses Alokasi Dana Sekarang
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Visualisasi Estimasi Alokasi Dana']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Visualisasi Estimasi Alokasi Dana']); ?>
                        <?php if($allocationData['settings']->isNotEmpty()): ?>
                            <canvas id="allocationPieChart"></canvas>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Pengaturan alokasi belum dibuat. Silakan buat pengaturan alokasi terlebih dahulu.
                            </div>
                        <?php endif; ?>
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

            <div class="row mt-4">
                <div class="col-12">
                    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Estimasi Rincian Alokasi Dana yang Akan Diproses']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Estimasi Rincian Alokasi Dana yang Akan Diproses']); ?>
                        
                        <?php $profitForDisplay = $allocationData['net_profit_to_allocate'] ?? $allocationData['net_profit'] ?? 0; ?>
                        <?php if (isset($component)) { $__componentOriginal163c8ba6efb795223894d5ffef5034f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal163c8ba6efb795223894d5ffef5034f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.table','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
                            <?php $__env->slot('thead'); ?>
                                <tr>
                                    <th>Kategori Alokasi</th>
                                    <th>Persentase</th>
                                    <th>Jumlah Alokasi</th>
                                </tr>
                            <?php $__env->endSlot(); ?>
                            
                            <?php $__empty_1 = true; $__currentLoopData = $allocationData['settings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setting): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td><?php echo e($setting->allocation_name); ?></td>
                                    <td><?php echo e($setting->percentage); ?>%</td>
                                    <td class="fw-bold">Rp <?php echo e(number_format(($profitForDisplay * $setting->percentage) / 100, 0, ',', '.')); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="3" class="text-center">Pengaturan alokasi belum dibuat.</td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php if($allocationData['settings']->isNotEmpty()): ?>
                                <?php $__env->slot('tfoot'); ?>
                                    <tr class="table-primary">
                                        <td colspan="2" class="text-end fw-bold">Total Alokasi</td>
                                        <td class="fw-bold">Rp <?php echo e(number_format($profitForDisplay, 0, ',', '.')); ?></td>
                                    </tr>
                                <?php $__env->endSlot(); ?>
                            <?php endif; ?>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $attributes = $__attributesOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $component = $__componentOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__componentOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
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

        
        <?php elseif($allocationData['view_type'] === 'summary'): ?>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title"><i class="fas fa-check-circle me-2"></i>Keuntungan Terakhir Dialokasikan</h5>
                            <h1 class="display-5 fw-bold">Rp <?php echo e(number_format($allocationData['last_allocated_profit'] ?? $allocationData['net_profit'] ?? 0, 0, ',', '.')); ?></h1>
                            <p class="mb-0">Ringkasan dari alokasi dana terakhir yang diproses.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Visualisasi Alokasi Dana Terakhir']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Visualisasi Alokasi Dana Terakhir']); ?>
                        <?php if($allocationData['history']->isNotEmpty()): ?>
                            <canvas id="allocationPieChart"></canvas>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Belum ada data historis alokasi dana.
                            </div>
                        <?php endif; ?>
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

            <div class="row mt-4">
                <div class="col-12">
                    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => 'Rincian Alokasi Dana Terakhir']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Rincian Alokasi Dana Terakhir']); ?>
                        <?php if (isset($component)) { $__componentOriginal163c8ba6efb795223894d5ffef5034f5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal163c8ba6efb795223894d5ffef5034f5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.table','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
                            <?php $__env->slot('thead'); ?>
                                <tr>
                                    <th>Kategori Alokasi</th>
                                    <th>Persentase</th>
                                    <th>Jumlah Alokasi</th>
                                    <th>Tanggal Alokasi</th>
                                </tr>
                            <?php $__env->endSlot(); ?>
                            
                            <?php $__empty_1 = true; $__currentLoopData = $allocationData['history']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $historyItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td><?php echo e($historyItem->allocation_name); ?></td>
                                    <td><?php echo e($historyItem->allocation_percentage); ?>%</td>
                                    <td class="fw-bold">Rp <?php echo e(number_format($historyItem->allocated_amount, 0, ',', '.')); ?></td>
                                    <td><?php echo e($historyItem->allocated_at->format('d M Y H:i')); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="4" class="text-center">Belum ada data historis alokasi.</td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php if($allocationData['history']->isNotEmpty()): ?>
                                <?php $__env->slot('tfoot'); ?>
                                    <tr class="table-primary">
                                        <td colspan="2" class="text-end fw-bold">Total Alokasi</td>
                                        <td colspan="2" class="fw-bold">Rp <?php echo e(number_format($allocationData['history']->sum('allocated_amount'), 0, ',', '.')); ?></td>
                                    </tr>
                                <?php $__env->endSlot(); ?>
                            <?php endif; ?>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $attributes = $__attributesOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__attributesOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal163c8ba6efb795223894d5ffef5034f5)): ?>
<?php $component = $__componentOriginal163c8ba6efb795223894d5ffef5034f5; ?>
<?php unset($__componentOriginal163c8ba6efb795223894d5ffef5034f5); ?>
<?php endif; ?>
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

        
        <?php else: ?>
            <div class="text-center p-5 bg-light rounded">
                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">Belum Ada Data Keuntungan</h4>
                <p>Silakan lakukan proses "Tutup Buku" di menu Finansial untuk menghitung keuntungan bersih bulanan Anda.</p>
                <a href="<?php echo e(route('admin.financial.index')); ?>" class="btn btn-primary mt-3">
                    <i class="fas fa-calculator me-2"></i>Ke Halaman Finansial
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const chartCanvas = document.getElementById('allocationPieChart');
        const dataContainer = document.getElementById('allocation-data-container');
        
        if (chartCanvas && dataContainer) {
            const allocationData = JSON.parse(dataContainer.dataset.allocation);
            let labels, data;

            if (allocationData.view_type === 'pending' && allocationData.settings) {
                // Untuk tampilan pending, gunakan net_profit_to_allocate atau fallback ke net_profit
                const profitAmount = allocationData.net_profit_to_allocate || allocationData.net_profit || 0;
                labels = allocationData.settings.map(s => s.allocation_name);
                data = allocationData.settings.map(s => (profitAmount * s.percentage) / 100);
            } else if (allocationData.view_type === 'summary' && allocationData.history) {
                labels = allocationData.history.map(h => h.allocation_name);
                data = allocationData.history.map(h => parseFloat(h.allocated_amount));
            }

            if (labels && data && labels.length > 0 && data.length > 0) {
                const ctx = chartCanvas.getContext('2d');
                const backgroundColors = [
                    '#0d6efd', '#198754', '#ffc107', '#dc3545', 
                    '#6c757d', '#0dcaf0', '#6f42c1', '#fd7e14'
                ];
                
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: backgroundColors.slice(0, data.length),
                            borderColor: '#fff',
                            borderWidth: 2,
                            hoverBorderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'top',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const percentage = ((context.raw / data.reduce((a, b) => a + b, 0)) * 100).toFixed(1);
                                        return `${context.label}: Rp ${context.raw.toLocaleString('id-ID')} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    });
</script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/resdigaz/pb.resdigaza.my.id/resources/views/admin/fund-allocation/index.blade.php ENDPATH**/ ?>