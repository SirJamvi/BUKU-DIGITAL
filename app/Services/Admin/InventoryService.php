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
            $product = Product::with('inventory')->findOrFail($data['product_id']);

            // Tambah stok di inventory
            $product->inventory->increment('current_stock', $data['quantity']);

            // Catat pergerakan stok sebagai 'in' (masuk)
            StockMovement::create([
                'business_id' => Auth::user()->business_id,
                'product_id' => $data['product_id'],
                'type' => 'in',
                'quantity' => $data['quantity'],
                'notes' => $data['notes'] ?? 'Stok masuk manual oleh admin.',
                'created_by' => Auth::id(),
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
            
            foreach ($data['items'] as $item) {
                $inventory = Inventory::find($item['inventory_id']);
                if (!$inventory) continue;

                $difference = $item['actual_stock'] - $inventory->current_stock;

                if ($difference !== 0) {
                    $inventory->update(['current_stock' => $item['actual_stock']]);

                    StockMovement::create([
                        'business_id' => $inventory->business_id, // <--- TAMBAHKAN BARIS INI
                        'product_id' => $inventory->product_id,
                        'type' => 'adjustment',
                        'quantity' => $difference,
                        'notes' => 'Stock Opname: ' . ($item['notes'] ?? 'Tutup Shift / Penyesuaian fisik.'),
                        'created_by' => $currentUserId,
                    ]);
                }
            }
        });
    }


    /**
     * Memecah produk besar (Ball) menjadi produk eceran.
     * Khusus untuk bisnis Es Kristal.
     */
    public function breakUnit(int $fromProductId, int $toProductId, int $quantityToBreak, int $yieldAmount): void
    {
        DB::transaction(function () use ($fromProductId, $toProductId, $quantityToBreak, $yieldAmount) {
            $fromInventory = $this->getInventoryByProductId($fromProductId);
            $toInventory = $this->getInventoryByProductId($toProductId);

            if (!$fromInventory || $fromInventory->current_stock < $quantityToBreak) {
                throw new \Exception("Stok karung tidak mencukupi untuk dipecah.");
            }
            if (!$toInventory) {
                throw new \Exception("Inventory untuk produk eceran belum dibuat.");
            }

            // 1. Kurangi stok karung besar (Gunakan type 'out' agar sesuai dengan enum database Anda)
            $this->updateStock(
                $fromInventory->id,
                $quantityToBreak,
                'out',
                "Pecah ball ke eceran (menjadi {$yieldAmount} pcs)"
            );

            // 2. Tambah stok eceran (Gunakan type 'in')
            $this->updateStock(
                $toInventory->id,
                $yieldAmount,
                'in',
                "Hasil pecah ball dari karung " . $fromInventory->product->name
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
