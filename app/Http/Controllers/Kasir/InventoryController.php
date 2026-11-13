<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Admin\InventoryService;
use App\Services\Inventory\InventoryCostService;
use App\Support\BranchContext;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\CashFlow;
use App\Models\PaymentMethod;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // --- 1. FITUR INPUT STOK DARI SUPPLIER ---

    public function addStockForm(): View
    {
        $businessId = Auth::user()->business_id;

        // Ambil supplier beserta produk yang disuplai dan harga khusus (pivot)
        $suppliers = Supplier::where('business_id', $businessId)->with('products')->get();

        // Ambil metode pembayaran yang aktif
        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->get();

        return view('kasir.inventory.add_stock', compact('suppliers', 'paymentMethods'));
    }

    public function storeStock(Request $request): RedirectResponse
    {
        // Validasi input: payment_method hanya wajib jika record_expense dicentang
        $request->validate([
            'supplier_id'    => 'required|exists:suppliers,id',
            'product_id'     => 'required|exists:products,id',
            'quantity'       => 'required|integer|min:1',
            'purchase_price' => 'nullable|numeric|min:0',
            'record_expense' => 'nullable|boolean',
            'payment_method' => 'required_if:record_expense,1|nullable|string',
            'notes'          => 'nullable|string|max:500',
            'confirm'        => 'nullable|boolean',
        ]);

        try {
            $businessId = Auth::user()->business_id;
            $userId = Auth::id();
            $branchId = app(BranchContext::class)->requireBranchId();

            // 1. Dapatkan relasi produk & supplier untuk mengecek harga pivot
            $supplier = Supplier::where('id', $request->supplier_id)
                ->where('business_id', $businessId)
                ->firstOrFail();

            $productPivot = $supplier->products()->where('product_id', $request->product_id)->first();
            if (!$productPivot) {
                throw new \Exception("Produk ini belum diatur harganya untuk supplier yang dipilih.");
            }

            // 2. Ambil input harga beli per unit & jumlah masuk (Didefinisikan sebelum closure)
            $purchasePricePerUnit = (float) ($request->filled('purchase_price') && $request->purchase_price > 0 
                ? $request->purchase_price 
                : $productPivot->pivot->price);
            $addedQty = (float) $request->quantity;
            $grandTotalExpense = $purchasePricePerUnit * $addedQty;

            $product = Product::where('id', $request->product_id)
                ->where('business_id', $businessId)
                ->firstOrFail();

            $existingInv = Inventory::firstOrCreate(
                ['product_id' => $request->product_id, 'branch_id' => $branchId, 'business_id' => $businessId],
                ['current_stock' => 0, 'cost_price' => (float) ($product->cost_price ?? 0)]
            );
            $currentCogs = (float) ($existingInv->cost_price > 0 ? $existingInv->cost_price : ($product->cost_price ?? 0));
            $lastSupplierPrice = (float) ($productPivot->pivot->price ?? 0);

            // 3. Evaluasi Peringatan Deviasi >40% & Stok <= 0 Sebelum Transaksi (A4)
            $warnings = [];
            $cogsDeviation = $currentCogs > 0 ? abs($purchasePricePerUnit - $currentCogs) / $currentCogs : 0;
            if ($currentCogs > 0 && $cogsDeviation > 0.40) {
                $warnings[] = "Harga beli (Rp " . number_format($purchasePricePerUnit, 0, ',', '.') . ") menyimpang " . round($cogsDeviation * 100) . "% dari HPP berjalan (Rp " . number_format($currentCogs, 0, ',', '.') . ").";
            }
            if ($lastSupplierPrice > 0 && abs($purchasePricePerUnit - $lastSupplierPrice) / $lastSupplierPrice > 0.40) {
                $warnings[] = "Harga beli menyimpang >40% dari harga kontrak master supplier (Rp " . number_format($lastSupplierPrice, 0, ',', '.') . ").";
            }

            $preStock = (float) $existingInv->current_stock;
            if ($preStock <= 0) {
                $warnings[] = "Stok sebelum restock berada pada posisi 0 atau minus ({$preStock}). HPP baru akan langsung mereset ke harga beli baru.";
            }

            // Jika ada deviasi dan kasir belum menekan tombol konfirmasi, kembalikan banner peringatan (A4)
            if (!empty($warnings) && !$request->boolean('confirm')) {
                return back()->with('warning_confirmation', [
                    'messages' => $warnings,
                    'input'    => $request->all(),
                ])->withInput();
            }

            // 4. Jalankan Transaksi Database Tunggal (Satu Pola Closure DB::transaction)
            return DB::transaction(function () use (
                $request, $businessId, $userId, $branchId, $product, $supplier, 
                $purchasePricePerUnit, $addedQty, $grandTotalExpense, 
                $currentCogs, $existingInv
            ) {
                $stockBefore = (float) $existingInv->current_stock;
                $stockAfter = $stockBefore + $addedQty;
                $isConfirmed = $request->boolean('confirm');

                $auditNote = "Restock {$supplier->name} | Beli " . number_format($purchasePricePerUnit, 2, '.', '') .
                    " | Stok {$stockBefore} -> {$stockAfter}" .
                    ($isConfirmed ? " [KONFIRMASI DITERIMA]" : "") .
                    ($request->notes ? " | " . $request->notes : "");

                $costService = app(InventoryCostService::class);
                $updatedInv = $costService->receive(
                    $existingInv,
                    $addedQty,
                    $purchasePricePerUnit,
                    'in',
                    $supplier,
                    $auditNote
                );
                $newCogs = (float) $updatedInv->cost_price;

                $message = 'Stok berhasil ditambahkan dan HPP cabang disesuaikan menjadi Rp ' . number_format($newCogs, 0, ',', '.') . ' (Tanpa mencatat pengeluaran).';

                // Catat ke Cash Flow jika dicentang
                if ($request->has('record_expense') && $request->record_expense == 1) {
                    $expenseCategory = ExpenseCategory::firstOrCreate(
                        ['business_id' => $businessId, 'name' => 'Refill Es Batu'],
                        ['type' => 'Operasional', 'is_cogs' => 1, 'is_active' => 1]
                    );

                    if (!$expenseCategory->is_cogs) {
                        $expenseCategory->update(['is_cogs' => 1]);
                    }

                    $fundingSource = $request->input('funding_source', 'drawer');
                    $affectsDrawer = ($fundingSource === 'drawer');

                    CashFlow::create([
                        'business_id'    => $businessId,
                        'branch_id'      => $branchId,
                        'type'           => 'expense',
                        'category_id'    => $expenseCategory->id, 
                        'amount'         => $grandTotalExpense,
                        'payment_method' => $request->payment_method,
                        'funding_source' => $fundingSource,
                        'affects_drawer' => $affectsDrawer,
                        'description'    => "Pembelian " . $request->quantity . " unit " . $product->name . " dari " . $supplier->name . " (@ Rp " . number_format($purchasePricePerUnit, 0, ',', '.') . ") [" . ($affectsDrawer ? 'Laci Kasir' : 'Dana Bos / Non-Laci') . "]",
                        'date'           => now()->toDateString(),
                        'created_by'     => $userId,
                    ]);

                    $message = 'Stok berhasil ditambahkan, HPP diperbarui menjadi Rp ' . number_format($newCogs, 0, ',', '.') . ', dan biaya otomatis tercatat di Pengeluaran (' . ($affectsDrawer ? 'Memotong Saldo Laci' : 'Dana Bos/Luar Laci - Tidak Memotong Laci') . ').';
                }

                \App\Support\BranchCache::flush($businessId);

                return redirect()->back()->with('success', $message);
            });

        } catch (\Throwable $e) {
            logger()->error('Kasir error adding stock & expenses: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan stok: ' . $e->getMessage())->withInput();
        }
    }

    // --- 2. FITUR PECAH BALL (DINAMIS) ---

    public function breakUnitForm(): View
    {
        // Ambil semua produk yang aktif untuk dijadikan pilihan dinamis
        $products = Product::where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->get();

        return view('kasir.inventory.break_unit', compact('products'));
    }

    public function processBreakUnit(Request $request): RedirectResponse
    {
        // Validasi input dinamis (A1: different:source_product_id)
        $request->validate([
            'source_product_id' => 'required|exists:products,id',
            'source_qty' => 'required|integer|min:1',
            'targets' => 'required|array|min:1',
            'targets.*.product_id' => 'required|exists:products,id|different:source_product_id',
            'targets.*.qty' => 'required|integer|min:1',
            'confirm' => 'nullable|boolean',
        ]);

        // Cek manual source != target setelah cast int di luar transaksi (A1)
        foreach ($request->targets as $target) {
            if ((int) $target['product_id'] === (int) $request->source_product_id) {
                return back()->with('error', 'Produk asal dan produk tujuan pecahan tidak boleh sama.')->withInput();
            }
        }

        try {
            $businessId = Auth::user()->business_id;
            $userId = Auth::id();
            $branchId = app(BranchContext::class)->requireBranchId();

            // 1. Ambil data stok & master produk asal
            $sourceProduct = Product::where('id', $request->source_product_id)
                ->where('business_id', $businessId)
                ->firstOrFail();

            $sourceInventory = Inventory::where('product_id', $request->source_product_id)
                ->where('branch_id', $branchId)
                ->where('business_id', $businessId)
                ->first();

            if (!$sourceInventory) {
                throw new \Exception("Data inventaris untuk produk ini belum ada di cabang aktif.");
            }

            $currentStock = (float) ($sourceInventory->current_stock ?? 0);
            if ($currentStock < $request->source_qty) {
                throw new \Exception("Stok produk asal tidak mencukupi di cabang ini. Sisa stok saat ini: " . $currentStock . ". Anda mencoba memecah: " . $request->source_qty);
            }

            // 2. Hitung Modal Induk yang Dipecah & Alokasikan ke Hasil Pecahan
            $sourceCostPrice = (float) ($sourceInventory->cost_price > 0 ? $sourceInventory->cost_price : ($sourceProduct->cost_price ?? 0));
            $totalSourceCost = (float) $request->source_qty * $sourceCostPrice;

            $extractWeight = function ($productName) {
                if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(kg|kilo|gram|gr)\b/i', $productName, $matches)) {
                    $val = (float) str_replace(',', '.', $matches[1]);
                    $unit = strtolower($matches[2]);
                    if ($unit === 'gram' || $unit === 'gr') {
                        return $val / 1000.0;
                    }
                    return $val;
                }
                return null;
            };

            $targetProducts = [];
            $totalRelativeShare = 0;
            $allHaveWeight = true;

            foreach ($request->targets as $target) {
                $p = Product::where('id', $target['product_id'])->where('business_id', $businessId)->first();
                $weight = $p ? $extractWeight($p->name) : null;
                if ($weight === null) {
                    $allHaveWeight = false;
                }
                $targetProducts[$target['product_id']] = [
                    'product' => $p,
                    'qty' => (float) $target['qty'],
                    'weight' => $weight,
                ];
            }

            foreach ($targetProducts as $tInfo) {
                if ($allHaveWeight) {
                    $totalRelativeShare += $tInfo['qty'] * ($tInfo['weight'] ?? 1);
                } else {
                    $pPrice = (float) (optional($tInfo['product'])->base_price ?? 1);
                    $totalRelativeShare += $tInfo['qty'] * ($pPrice > 0 ? $pPrice : 1);
                }
            }

            // Pra-kalkulasi untuk mendeteksi lonjakan HPP anak >40% (A4 / 1.4)
            $warnings = [];
            foreach ($request->targets as $target) {
                $tId = $target['product_id'];
                $yieldQty = (float) $target['qty'];
                $targetProduct = $targetProducts[$tId]['product'] ?? Product::find($tId);
                if (!$targetProduct) continue;

                if (count($request->targets) === 1) {
                    $modalUnit = ($yieldQty > 0) ? ($totalSourceCost / $yieldQty) : 0;
                } else {
                    $proportion = ($totalRelativeShare > 0) ? (($yieldQty * ($targetProducts[$tId]['weight'] ?? 1)) / $totalRelativeShare) : (1 / count($request->targets));
                    $modalUnit = ($yieldQty > 0) ? (($totalSourceCost * $proportion) / $yieldQty) : 0;
                }

                $tInv = Inventory::where('product_id', $tId)->where('branch_id', $branchId)->where('business_id', $businessId)->first();
                $cStock = $tInv ? (float) $tInv->current_stock : 0;
                $cCost = (float) ($tInv && $tInv->cost_price > 0 ? $tInv->cost_price : ($targetProduct->cost_price ?? 0));
                $predCogs = ($cStock > 0 && $cCost > 0)
                    ? (($cStock * $cCost) + ($yieldQty * $modalUnit)) / ($cStock + $yieldQty)
                    : $modalUnit;

                if ($cCost > 0 && abs($predCogs - $cCost) / $cCost > 0.40) {
                    $warnings[] = "HPP produk '{$targetProduct->name}' menyimpang >40% (dari Rp " . number_format($cCost, 0, ',', '.') . " menjadi Rp " . number_format($predCogs, 0, ',', '.') . ").";
                }
            }

            if (!empty($warnings) && !$request->boolean('confirm')) {
                return back()->with('warning_confirmation', [
                    'messages' => $warnings,
                    'input'    => $request->all(),
                ])->withInput();
            }

            // Jalankan transaksi
            return DB::transaction(function () use ($request, $businessId, $userId, $branchId, $sourceProduct, $sourceInventory, $totalSourceCost, $targetProducts, $totalRelativeShare, $allHaveWeight) {
                $costService = app(InventoryCostService::class);

                // 1. Kurangi stok karung asal via InventoryCostService::issue
                $sourceStockBefore = (float) $sourceInventory->current_stock;
                $sourceStockAfter = $sourceStockBefore - (float) $request->source_qty;
                $issueNote = "Pecah Ball: {$sourceProduct->name} | Qty: {$request->source_qty} | Stok: {$sourceStockBefore} -> {$sourceStockAfter}";

                $costService->issue(
                    $sourceInventory,
                    $request->source_qty,
                    'out',
                    $sourceProduct,
                    $issueNote
                );

                $totalHasilPecahan = 0;
                foreach ($request->targets as $target) {
                    $tId = $target['product_id'];
                    $yieldQty = (float) $target['qty'];
                    $targetProduct = $targetProducts[$tId]['product'] ?? Product::find($tId);

                    if (!$targetProduct) continue;

                    if (count($request->targets) === 1) {
                        $modalPerUnitPecahanBaru = ($yieldQty > 0) ? ($totalSourceCost / $yieldQty) : 0;
                    } else {
                        if ($allHaveWeight) {
                            $targetWeightShare = $yieldQty * ($targetProducts[$tId]['weight'] ?? 1);
                        } else {
                            $pPrice = (float) ($targetProduct->base_price ?? 1);
                            $targetWeightShare = $yieldQty * ($pPrice > 0 ? $pPrice : 1);
                        }
                        $proportion = ($totalRelativeShare > 0) ? ($targetWeightShare / $totalRelativeShare) : (1 / count($request->targets));
                        $targetTotalCost = $totalSourceCost * $proportion;
                        $modalPerUnitPecahanBaru = ($yieldQty > 0) ? ($targetTotalCost / $yieldQty) : 0;
                    }

                    $targetInventory = Inventory::firstOrCreate(
                        ['product_id' => $tId, 'branch_id' => $branchId, 'business_id' => $businessId],
                        ['current_stock' => 0, 'cost_price' => $modalPerUnitPecahanBaru]
                    );

                    $targetStockBefore = (float) $targetInventory->current_stock;
                    $targetStockAfter = $targetStockBefore + $yieldQty;
                    $isConfirmed = $request->boolean('confirm');
                    $childNote = "Hasil Pecahan dari {$sourceProduct->name} ke {$targetProduct->name} | Qty: {$yieldQty} | Stok: {$targetStockBefore} -> {$targetStockAfter}" .
                        ($isConfirmed ? " [KONFIRMASI DITERIMA]" : "");

                    $costService->receive(
                        $targetInventory,
                        $yieldQty,
                        $modalPerUnitPecahanBaru,
                        'in',
                        $sourceProduct,
                        $childNote
                    );

                    $totalHasilPecahan += $target['qty'];
                }

                \App\Support\BranchCache::flush($businessId);

                return redirect()->back()->with('success', "Berhasil memecah {$request->source_qty} unit menjadi total {$totalHasilPecahan} kemasan baru.");
            });

        } catch (\Throwable $e) {
            logger()->error('Kasir error breaking unit dinamis: ' . $e->getMessage());
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    // --- 3. FITUR STOCK OPNAME ---

    public function stockOpnameForm(): View
    {
        // Ambil produk aktif beserta relasi inventory-nya
        $products = $this->inventoryService->getActiveProducts()->filter(function ($product) {
            return $product->inventory !== null;
        });

        return view('kasir.inventory.stock_opname', compact('products'));
    }

    public function processStockOpname(Request $request): RedirectResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.inventory_id' => 'required|exists:inventory,id',
            'items.*.actual_stock' => 'required|integer|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        try {
            $this->inventoryService->processStockOpname($request->all());
            return redirect()->back()->with('success', 'Stock Opname berhasil disimpan. Data stok telah disesuaikan dengan fisik.');
        } catch (\Exception $e) {
            logger()->error('Kasir error stock opname: ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses stock opname: ' . $e->getMessage());
        }
    }
}