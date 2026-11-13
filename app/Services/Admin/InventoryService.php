<?php

namespace App\Services\Admin;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryCostService;
use App\Support\BranchContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InventoryService
{
    /**
     * Mendapatkan data inventory dengan paginasi.
     * - Mode Cabang: Menampilkan inventori spesifik cabang aktif.
     * - Mode Pusat (Konsolidasi): Menampilkan total akumulasi stok, nilai inventori, dan weighted average HPP per produk.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getInventoryWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        $context = app(BranchContext::class);
        $businessId = Auth::user()->business_id;

        if ($context->isBranch() && $context->getBranchId() !== null) {
            return Inventory::with('product.category')
                ->where('branch_id', $context->getBranchId())
                ->latest('updated_at')
                ->paginate($perPage);
        }

        // Mode CONSOLIDATED (Pusat): Agregasi per produk dari seluruh cabang aktif
        $productsPaginator = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->with(['category', 'inventories' => function ($q) {
                $q->withoutGlobalScope('branch')
                  ->whereHas('branch', fn($b) => $b->where('is_active', true));
            }])
            ->orderBy('name')
            ->paginate($perPage);

        $items = $productsPaginator->getCollection()->map(function ($product) {
            $inventories = $product->inventories;
            $totalStock = (float) $inventories->sum('current_stock');
            $totalValue = (float) $inventories->sum(fn($inv) => (float) $inv->current_stock * (float) $inv->cost_price);

            if ($totalStock > 0 && $totalValue > 0) {
                $weightedHpp = round($totalValue / $totalStock, 2);
            } else {
                $weightedHpp = (float) ($inventories->avg('cost_price') ?: ($product->cost_price ?? 0));
            }

            $minStock = (int) ($inventories->max('min_stock') ?: 10);
            $latestUpdated = $inventories->max('updated_at') ?: $product->updated_at ?: now();
            if (!$latestUpdated instanceof \Carbon\Carbon) {
                $latestUpdated = \Carbon\Carbon::parse($latestUpdated);
            }

            $obj = new Inventory();
            $obj->exists = true;
            $obj->setRelation('product', $product);
            $obj->current_stock = $totalStock;
            $obj->min_stock = $minStock;
            $obj->cost_price = $weightedHpp;
            $obj->stock_value = $totalValue;
            $obj->updated_at = $latestUpdated;

            return $obj;
        });

        $productsPaginator->setCollection($items);

        return $productsPaginator;
    }

    /**
     * Mendapatkan riwayat pergerakan stok dengan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getStockMovementsWithPagination(int $perPage = 25): LengthAwarePaginator
    {
        return StockMovement::with('product', 'createdBy')->latest()->paginate($perPage);
    }

    /**
     * Mengambil semua produk aktif untuk dropdown.
     */
    public function getActiveProducts()
    {
        return Product::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Memproses penambahan stok baru.
     */
    public function addStock(array $data): void
    {
        DB::transaction(function () use ($data) {
            $businessId = Auth::user()->business_id;
            $branchId = app(BranchContext::class)->requireBranchId();
            $product = Product::findOrFail($data['product_id']);

            // Buat inventory dulu jika belum ada, lalu kunci (A2)
            $inventory = Inventory::firstOrCreate(
                ['product_id' => $product->id, 'branch_id' => $branchId, 'business_id' => $businessId],
                ['current_stock' => 0, 'cost_price' => (float) ($product->cost_price ?? 0)]
            );

            $stockBefore = (float) $inventory->current_stock;
            $cogsBefore = (float) ($inventory->cost_price > 0 ? $inventory->cost_price : ($product->cost_price ?? 0));
            $addedQty = (float) $data['quantity'];

            $hasPrice = isset($data['purchase_price']) && is_numeric($data['purchase_price']) && (float) $data['purchase_price'] > 0;
            $purchasePrice = $hasPrice ? (float) $data['purchase_price'] : $cogsBefore;

            if ($hasPrice) {
                $auditNote = "Restock Admin | Beli " . number_format($purchasePrice, 2, '.', '') . " | Stok {$stockBefore} -> " . ($stockBefore + $addedQty);
            } else {
                $auditNote = "Restock Admin (Tanpa Harga Beli - HPP Tetap) | HPP {$cogsBefore} | Stok {$stockBefore} -> " . ($stockBefore + $addedQty);
            }

            if (!empty($data['notes'])) {
                $auditNote .= " | " . $data['notes'];
            }

            $costService = app(InventoryCostService::class);
            $costService->receive(
                $inventory,
                $addedQty,
                $purchasePrice,
                'in',
                $product,
                $auditNote
            );

            \App\Support\BranchCache::flush($businessId);
        });
    }

    /**
     * Memproses data dari stock opname.
     *
     * @param array $data
     * @return void
     */
    public function processStockOpname(array $data): void
    {
        DB::transaction(function () use ($data) {
            $branchId = app(BranchContext::class)->requireBranchId();
            $currentUserId = Auth::id();
            $itemsCount = 0;
            $differenceCount = 0;
            $totalSystem = 0;
            $totalActual = 0;

            foreach ($data['items'] as $item) {
                $inventory = Inventory::find($item['inventory_id']);
                if (!$inventory) continue;

                $difference = $item['actual_stock'] - $inventory->current_stock;

                $itemsCount++;
                $totalSystem += (int) ($item['sys_stock'] ?? $inventory->current_stock);
                $totalActual += (int) $item['actual_stock'];

                if ($difference !== 0) {
                    $inventory->update([
                        'current_stock' => $item['actual_stock'],
                        'last_updated'  => now(),
                        'updated_by'    => $currentUserId,
                    ]);

                    StockMovement::create([
                        'business_id'    => $inventory->business_id,
                        'branch_id'      => $inventory->branch_id ?? $branchId,
                        'product_id'     => $inventory->product_id,
                        'type'           => 'adjustment',
                        'quantity'       => $difference,
                        'unit_cost'      => (float) ($inventory->cost_price ?? 0),
                        'notes'          => 'Stock Opname: ' . ($item['notes'] ?? 'Tutup Shift / Penyesuaian fisik.'),
                        'created_by'     => $currentUserId,
                    ]);

                    $differenceCount++;
                }
            }

            // Jejak sesi opname (dicatat juga saat tidak ada selisih) - dibaca ACS untuk checksheet.
            if ($itemsCount > 0 && \Illuminate\Support\Facades\Schema::hasTable('opname_sessions')) {
                $opnameData = [
                    'business_id'      => Auth::user()->business_id,
                    'created_by'       => $currentUserId,
                    'items_count'      => $itemsCount,
                    'difference_count' => $differenceCount,
                    'total_system'     => $totalSystem,
                    'total_actual'     => $totalActual,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('opname_sessions', 'branch_id')) {
                    $opnameData['branch_id'] = $branchId;
                }

                DB::table('opname_sessions')->insert($opnameData);
            }

            \App\Support\BranchCache::flush(Auth::user()->business_id);
        });
    }

    /**
     * Memecah produk besar (Ball) menjadi produk eceran.
     * Khusus untuk bisnis Es Kristal.
     */
    public function breakUnit(int $fromProductId, int $toProductId, int $quantityToBreak, int $yieldAmount): void
    {
        // Validasi source != target setelah cast (int) di luar transaksi (1.1)
        if ((int) $fromProductId === (int) $toProductId) {
            throw new \InvalidArgumentException("Produk asal dan produk tujuan pecahan tidak boleh sama.");
        }

        DB::transaction(function () use ($fromProductId, $toProductId, $quantityToBreak, $yieldAmount) {
            $branchId = app(BranchContext::class)->requireBranchId();
            $businessId = Auth::user()->business_id;

            $fromInventory = Inventory::where('product_id', $fromProductId)
                ->where('branch_id', $branchId)
                ->first();
            $toInventory = Inventory::firstOrCreate(
                ['product_id' => $toProductId, 'branch_id' => $branchId, 'business_id' => $businessId],
                ['current_stock' => 0]
            );

            if (!$fromInventory || $fromInventory->current_stock < $quantityToBreak) {
                throw new \Exception("Stok karung tidak mencukupi untuk dipecah di cabang aktif.");
            }

            $fromProduct = $fromInventory->product;
            $toProduct = $toInventory->product;

            $fromStockBefore = (float) $fromInventory->current_stock;
            $toStockBefore = (float) $toInventory->current_stock;

            // Hitung modal per unit pecahan baru
            $sourceCostPrice = (float) ($fromInventory->cost_price > 0 ? $fromInventory->cost_price : ($fromProduct->cost_price ?? 0));
            $modalPerUnitPecahanBaru = ($yieldAmount > 0) ? (($quantityToBreak * $sourceCostPrice) / $yieldAmount) : 0;

            $fromStockAfter = $fromStockBefore - $quantityToBreak;
            $toStockAfter = $toStockBefore + $yieldAmount;

            $costService = app(InventoryCostService::class);

            // 1. Kurangi stok karung besar
            $costService->issue(
                $fromInventory,
                $quantityToBreak,
                'out',
                $fromProduct,
                "Pecah ball ke eceran | Dari: {$fromProduct->name} ke {$toProduct->name} | Qty: {$quantityToBreak} | Stok: {$fromStockBefore} -> {$fromStockAfter}"
            );

            // 2. Tambah stok eceran
            $costService->receive(
                $toInventory,
                $yieldAmount,
                $modalPerUnitPecahanBaru,
                'in',
                $fromProduct,
                "Hasil pecah ball dari {$fromProduct->name} ke {$toProduct->name} | Qty: {$yieldAmount} | Stok: {$toStockBefore} -> {$toStockAfter}"
            );

            \App\Support\BranchCache::flush($businessId);
        });
    }

    /**
     * Mendapatkan inventory berdasarkan product ID.
     *
     * @param int $productId
     * @return Inventory|null
     */
    public function getInventoryByProductId(int $productId): ?Inventory
    {
        return Inventory::where('product_id', $productId)->with('product')->first();
    }

    /**
     * Memperbarui stok inventory.
     *
     * @param int $inventoryId
     * @param int $quantity
     * @param string $type
     * @param string|null $notes
     * @return void
     */
    public function updateStock(int $inventoryId, int $quantity, string $type, ?string $notes = null): void
    {
        DB::transaction(function () use ($inventoryId, $quantity, $type, $notes) {
            $inventory = Inventory::findOrFail($inventoryId);

            // Hitung stok baru berdasarkan tipe
            $newStock = match ($type) {
                'in', 'purchase', 'return' => $inventory->current_stock + $quantity,
                'out', 'sale', 'damaged' => $inventory->current_stock - $quantity,
                'adjustment' => $quantity,
                default => $inventory->current_stock
            };

            // Update stok
            $inventory->update([
                'current_stock' => $newStock,
                'last_updated'  => now(),
                'updated_by'    => Auth::id(),
            ]);

            // Catat pergerakan stok
            StockMovement::create([
                'business_id' => $inventory->business_id,
                'branch_id'   => $inventory->branch_id,
                'product_id'  => $inventory->product_id,
                'type'        => $type,
                'quantity'    => $type === 'adjustment' ? ($quantity - $inventory->current_stock) : $quantity,
                'unit_cost'   => (float) ($inventory->cost_price ?? 0),
                'notes'       => $notes,
                'created_by'  => Auth::id(),
            ]);

            \App\Support\BranchCache::flush($inventory->business_id);
        });
    }

    /**
     * Mendapatkan produk dengan stok rendah.
     *
     * @param int $threshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLowStockProducts(int $threshold = 10)
    {
        return Inventory::with('product.category')
            ->where('current_stock', '<=', $threshold)
            ->get();
    }

    /**
     * Mendapatkan statistik inventory.
     *
     * @return array
     */
    public function getInventoryStats(): array
    {
        $totalProducts = Inventory::count();
        $totalStock = Inventory::sum('current_stock');
        $lowStockCount = Inventory::where('current_stock', '<=', 10)->count();
        $outOfStockCount = Inventory::where('current_stock', '=', 0)->count();

        return [
            'total_products' => $totalProducts,
            'total_stock' => $totalStock,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
        ];
    }

    /**
     * Mencari inventory berdasarkan kata kunci.
     *
     * @param string $keyword
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchInventory(string $keyword, int $perPage = 15): LengthAwarePaginator
    {
        return Inventory::with('product.category')
            ->whereHas('product', function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%");
            })
            ->latest('updated_at')
            ->paginate($perPage);
    }
}
