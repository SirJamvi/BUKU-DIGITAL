<?php

namespace App\Services\Admin;

use App\Models\Inventory;
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
     * Memproses data dari stock opname.
     *
     * @param array $data
     * @return void
     */
    public function processStockOpname(array $data): void
    {
        DB::transaction(function () use ($data) {
            $currentUserId = Auth::id();
            
            foreach ($data['items'] as $item) {
                $inventory = Inventory::find($item['inventory_id']);
                if (!$inventory) continue;

                $difference = $item['actual_stock'] - $inventory->current_stock;

                if ($difference !== 0) {
                    // Update stok saat ini
                    $inventory->update(['current_stock' => $item['actual_stock']]);

                    // Catat pergerakan stok sebagai 'adjustment'
                    StockMovement::create([
                        'product_id' => $inventory->product_id,
                        'type' => 'adjustment',
                        'quantity' => $difference,
                        'notes' => 'Stock Opname: ' . ($item['notes'] ?? 'Penyesuaian stok fisik.'),
                        'created_by' => $currentUserId,
                    ]);
                }
            }
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
            $newStock = match($type) {
                'in', 'purchase', 'return' => $inventory->current_stock + $quantity,
                'out', 'sale', 'damaged' => $inventory->current_stock - $quantity,
                'adjustment' => $quantity, // Untuk adjustment, quantity adalah stok final
                default => $inventory->current_stock
            };

            // Update stok
            $inventory->update(['current_stock' => $newStock]);

            // Catat pergerakan stok
            StockMovement::create([
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