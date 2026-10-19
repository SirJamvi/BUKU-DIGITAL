<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Admin\InventoryService;
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
        ]);

        try {
            DB::beginTransaction();

            $businessId = Auth::user()->business_id;
            $userId = Auth::id();

            // 1. Dapatkan relasi produk & supplier untuk mengecek harga pivot
            $supplier = Supplier::findOrFail($request->supplier_id);
            $productPivot = $supplier->products()->where('product_id', $request->product_id)->first();

            if (!$productPivot) {
                throw new \Exception("Produk ini belum diatur harganya untuk supplier yang dipilih.");
            }

            // 2. Ambil input harga beli per unit & jumlah masuk
            $purchasePricePerUnit = (float) ($request->filled('purchase_price') && $request->purchase_price > 0 
                ? $request->purchase_price 
                : $productPivot->pivot->price);
            $addedQty = (float) $request->quantity;
            $grandTotalExpense = $purchasePricePerUnit * $addedQty;

            // 3. Ambil data produk & inventory saat ini SEBELUM stok ditambahkan
            $product = Product::findOrFail($request->product_id);
            $inventory = Inventory::firstOrCreate(
                ['product_id' => $request->product_id, 'business_id' => $businessId],
                ['current_stock' => 0]
            );

            $currentStock = (float) $inventory->current_stock;
            $currentCogs = (float) ($product->cost_price ?? 0);

            // 4. Hitung Moving Average Cost (HPP Rata-Rata Bergerak)
            if ($currentStock <= 0) {
                // Edge case: Stok habis atau minus, HPP baru langsung menggunakan harga beli terbaru
                $newCogs = $purchasePricePerUnit;
            } else {
                // Rumus Moving Average Cost
                $newCogs = (($currentStock * $currentCogs) + ($addedQty * $purchasePricePerUnit)) / ($currentStock + $addedQty);
            }

            $newCogs = round($newCogs, 2);
            $product->update(['cost_price' => $newCogs]);

            // 5. Update Inventory & Catat Stock Movement
            $inventory->increment('current_stock', $request->quantity);

            StockMovement::create([
                'business_id' => $businessId,
                'product_id'  => $request->product_id,
                'type'        => 'in',
                'quantity'    => $request->quantity,
                'reference_id' => 'supplier:' . $supplier->id,
                'notes'       => 'Restock dari ' . $supplier->name . " @ Rp " . number_format($purchasePricePerUnit, 0, ',', '.') . " (HPP Moving Avg: Rp " . number_format($newCogs, 0, ',', '.') . "). " . $request->notes,
                'created_by'  => $userId,
            ]);

            $message = 'Stok berhasil ditambahkan dan HPP disesuaikan menjadi Rp ' . number_format($newCogs, 0, ',', '.') . ' (Tanpa mencatat pengeluaran).';

            // 6. Catat ke Expenses (Cash Flow) JIKA toggle dicentang
            if ($request->has('record_expense') && $request->record_expense == 1) {
                // Cari atau buat kategori "Refill Es Batu" khusus untuk bisnis ini dengan flag is_cogs = 1
                $expenseCategory = ExpenseCategory::firstOrCreate(
                    [
                        'business_id' => $businessId,
                        'name'        => 'Refill Es Batu',
                    ],
                    [
                        'type'        => 'Operasional',
                        'is_cogs'     => 1,
                        'is_active'   => 1,
                    ]
                );

                // Pastikan flag is_cogs aktif jika kategori sudah ada sebelumnya
                if (!$expenseCategory->is_cogs) {
                    $expenseCategory->update(['is_cogs' => 1]);
                }

                $fundingSource = $request->input('funding_source', 'drawer');
                $affectsDrawer = ($fundingSource === 'drawer');

                CashFlow::create([
                    'business_id'    => $businessId,
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

            DB::commit();
            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
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
        // Validasi input dinamis
        $request->validate([
            'source_product_id' => 'required|exists:products,id',
            'source_qty' => 'required|integer|min:1',
            'targets' => 'required|array|min:1',
            'targets.*.product_id' => 'required|exists:products,id',
            'targets.*.qty' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $businessId = Auth::user()->business_id;
            $userId = Auth::id();

            // 1. Ambil data stok & master produk asal
            $sourceProduct = Product::where('id', $request->source_product_id)
                ->where('business_id', $businessId)
                ->firstOrFail();

            $sourceInventory = Inventory::where('product_id', $request->source_product_id)
                ->where('business_id', $businessId)
                ->first();

            if (!$sourceInventory) {
                throw new \Exception("Data inventaris untuk produk ini belum ada di sistem.");
            }

            // Ambil stok saat ini
            $currentStock = $sourceInventory->current_stock ?? 0;

            // Proteksi: Cek apakah stok mencukupi
            if ($currentStock < $request->source_qty) {
                throw new \Exception("Stok produk asal tidak mencukupi. Sisa stok saat ini: " . $currentStock . ". Anda mencoba memecah: " . $request->source_qty);
            }

            // Kurangi stok utama
            $sourceInventory->decrement('current_stock', $request->source_qty);

            // Catat pergerakan stok keluar
            StockMovement::create([
                'business_id' => $businessId,
                'product_id' => $request->source_product_id,
                'type' => 'out',
                'quantity' => $request->source_qty,
                'notes' => 'Pecah Ball (Bahan Baku): ' . $sourceProduct->name,
                'created_by' => $userId,
            ]);

            // 2. Hitung Modal Induk yang Dipecah & Alokasikan ke Hasil Pecahan
            $sourceCostPrice = (float) ($sourceProduct->cost_price ?? 0);
            $totalSourceCost = (float) $request->source_qty * $sourceCostPrice;

            // Helper untuk mengekstrak bobot produk (misal: "Es Kristal 5 Kg" -> 5)
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

            // Hitung bobot atau nilai relatif jika target lebih dari 1
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

            // 3. Tambahkan stok & update Moving Average HPP untuk setiap produk pecahan
            $totalHasilPecahan = 0;
            foreach ($request->targets as $target) {
                $tId = $target['product_id'];
                $yieldQty = (float) $target['qty'];
                $targetProduct = $targetProducts[$tId]['product'] ?? Product::find($tId);

                if (!$targetProduct) {
                    continue;
                }

                // Hitung modal per unit pecahan baru
                if (count($request->targets) === 1) {
                    // Rumus langsung untuk 1 target: (Qty Induk * cost_price Induk) / Yield Qty
                    $modalPerUnitPecahanBaru = ($yieldQty > 0) ? ($totalSourceCost / $yieldQty) : 0;
                } else {
                    // Proporsional berdasarkan bobot atau nilai relatif
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

                // Cari target inventory
                $targetInventory = Inventory::firstOrCreate(
                    ['product_id' => $tId, 'business_id' => $businessId],
                    ['current_stock' => 0]
                );

                // Rumus Moving Average HPP:
                // ((Stok Sisa * HPP Lama) + (Yield Qty * Modal Pecahan Baru)) / (Stok Sisa + Yield Qty)
                $currentChildStock = (float) $targetInventory->current_stock;
                $currentChildCostPrice = (float) ($targetProduct->cost_price ?? 0);

                if ($currentChildStock > 0 && $currentChildCostPrice > 0) {
                    $newChildCostPrice = (($currentChildStock * $currentChildCostPrice) + ($yieldQty * $modalPerUnitPecahanBaru)) / ($currentChildStock + $yieldQty);
                } else {
                    $newChildCostPrice = $modalPerUnitPecahanBaru;
                }

                // Update cost_price produk pecahan agar HPP selalu tersinkronisasi
                $targetProduct->update([
                    'cost_price' => round($newChildCostPrice, 2)
                ]);

                // Tambahkan stok hasil
                $targetInventory->increment('current_stock', $target['qty']);

                // Catat pergerakan stok masuk
                StockMovement::create([
                    'business_id' => $businessId,
                    'product_id'  => $tId,
                    'type'        => 'in',
                    'quantity'    => $target['qty'],
                    'notes'       => 'Hasil Pecahan dari ' . $sourceProduct->name . ' (HPP Moving Avg: Rp ' . number_format($newChildCostPrice, 0, ',', '.') . ')',
                    'created_by'  => $userId,
                ]);

                $totalHasilPecahan += $target['qty'];
            }

            DB::commit();
            return redirect()->back()->with('success', "Berhasil memecah {$request->source_qty} unit menjadi total {$totalHasilPecahan} kemasan baru.");
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Kasir error breaking unit dinamis: ' . $e->getMessage());
            return back()->with('error', $e->getMessage());
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