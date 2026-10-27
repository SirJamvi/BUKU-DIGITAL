<?php

namespace App\Services\Admin;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InventoryService
{
    /**
     * Mendapatkan data inventory dengan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getInventoryWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return Inventory::with('product.category')->latest('updated_at')->paginate($perPage);
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
            $product = Product::findOrFail($data['product_id']);

            // Buat inventory dulu jika belum ada, lalu kunci (A2)
            $inventory = Inventory::firstOrCreate(
                ['product_id' => $product->id, 'business_id' => $businessId],
                ['current_stock' => 0]
            );
            $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();

            $stockBefore = (float) $inventory->current_stock;
            $cogsBefore = (float) ($product->cost_price ?? 0);
            $addedQty = (float) $data['quantity'];

            // Jika membawa purchase_price, hitung moving average; jika tidak, jangan ubah HPP (1.3)
            $hasPrice = isset($data['purchase_price']) && is_numeric($data['purchase_price']) && (float) $data['purchase_price'] > 0;
            if ($hasPrice) {
                $purchasePrice = (float) $data['purchase_price'];
                $newCogs = $stockBefore <= 0
                    ? $purchasePrice
                    : (($stockBefore * $cogsBefore) + ($addedQty * $purchasePrice)) / ($stockBefore + $addedQty);
                $newCogs = round($newCogs, 2);
                $product->update(['cost_price' => $newCogs]);
                $auditNote = "Restock Admin | Beli " . number_format($purchasePrice, 2, '.', '') . " | HPP {$cogsBefore} -> {$newCogs} | Stok {$stockBefore} -> " . ($stockBefore + $addedQty);
            } else {
                $newCogs = $cogsBefore;
                $auditNote = "Restock Admin (Tanpa Harga Beli - HPP Tetap) | HPP {$cogsBefore} | Stok {$stockBefore} -> " . ($stockBefore + $addedQty);
            }

            if (!empty($data['notes'])) {
                $auditNote .= " | " . $data['notes'];
            }

            $inventory->increment('current_stock', $data['quantity']);

            StockMovement::create([
                'business_id' => $businessId,
                'product_id'  => $data['product_id'],
                'type'        => 'in',
                'quantity'    => $data['quantity'],
                'notes'       => $auditNote,
                'created_by'  => Auth::id(),
            ]);
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
                    $inventory->update(['current_stock' => $item['actual_stock']]);

                    StockMovement::create([
                        'business_id' => $inventory->business_id,
                        'product_id' => $inventory->product_id,
                        'type' => 'adjustment',
                        'quantity' => $difference,
                        'notes' => 'Stock Opname: ' . ($item['notes'] ?? 'Tutup Shift / Penyesuaian fisik.'),
                        'created_by' => $currentUserId,
                    ]);

                    $differenceCount++;
                }
            }

            // Jejak sesi opname (dicatat juga saat tidak ada selisih) - dibaca ACS untuk checksheet.
            if ($itemsCount > 0 && \Illuminate\Support\Facades\Schema::hasTable('opname_sessions')) {
                DB::table('opname_sessions')->insert([
                    'business_id' => Auth::user()->business_id,
                    'created_by' => $currentUserId,
                    'items_count' => $itemsCount,
                    'difference_count' => $differenceCount,
                    'total_system' => $totalSystem,
                    'total_actual' => $totalActual,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
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
            $fromInventory = $this->getInventoryByProductId($fromProductId);
            $toInventory = $this->getInventoryByProductId($toProductId);

            if (!$fromInventory || $fromInventory->current_stock < $quantityToBreak) {
                throw new \Exception("Stok karung tidak mencukupi untuk dipecah.");
            }
            if (!$toInventory) {
                throw new \Exception("Inventory untuk produk eceran belum dibuat.");
            }

            // Kunci baris inventory
            $fromInventory = Inventory::where('id', $fromInventory->id)->lockForUpdate()->first();
            $toInventory = Inventory::where('id', $toInventory->id)->lockForUpdate()->first();

            $fromProduct = $fromInventory->product;
            $toProduct = $toInventory->product;

            $fromStockBefore = (float) $fromInventory->current_stock;
            $toStockBefore = (float) $toInventory->current_stock;

            // Hitung modal per unit pecahan baru
            $sourceCostPrice = (float) ($fromProduct->cost_price ?? 0);
            $modalPerUnitPecahanBaru = ($yieldAmount > 0) ? (($quantityToBreak * $sourceCostPrice) / $yieldAmount) : 0;

            // Rumus Moving Average HPP produk pecahan:
            $currentChildCostPrice = (float) ($toProduct->cost_price ?? 0);

            if ($toStockBefore > 0 && $currentChildCostPrice > 0) {
                $newChildCostPrice = (($toStockBefore * $currentChildCostPrice) + ($yieldAmount * $modalPerUnitPecahanBaru)) / ($toStockBefore + $yieldAmount);
            } else {
                $newChildCostPrice = $modalPerUnitPecahanBaru;
            }

            $newChildCostPrice = round($newChildCostPrice, 2);
            if ($toProduct) {
                $toProduct->update(['cost_price' => $newChildCostPrice]);
            }

            $fromStockAfter = $fromStockBefore - $quantityToBreak;
            $toStockAfter = $toStockBefore + $yieldAmount;

            // 1. Kurangi stok karung besar terstruktur (1.4)
            $this->updateStock(
                $fromInventory->id,
                $quantityToBreak,
                'out',
                "Pecah ball ke eceran | Dari: {$fromProduct->name} ke {$toProduct->name} | Qty: {$quantityToBreak} | Stok: {$fromStockBefore} -> {$fromStockAfter}"
            );

            // 2. Tambah stok eceran terstruktur (1.4)
            $this->updateStock(
                $toInventory->id,
                $yieldAmount,
                'in',
                "Hasil pecah ball dari {$fromProduct->name} ke {$toProduct->name} | Qty: {$yieldAmount} | HPP: {$currentChildCostPrice} -> {$newChildCostPrice} | Stok: {$toStockBefore} -> {$toStockAfter}"
            );
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
            $inventory->update(['current_stock' => $newStock]);

            // Catat pergerakan stok
            StockMovement::create([
                'business_id' => $inventory->business_id, // <--- TAMBAHKAN BARIS INI
                'product_id' => $inventory->product_id,
                'type' => $type,
                'quantity' => $type === 'adjustment' ? ($quantity - $inventory->current_stock) : $quantity,
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);
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
