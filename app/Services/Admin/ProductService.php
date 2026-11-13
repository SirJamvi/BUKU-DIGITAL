<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProductService
{
    /**
     * Logika getAllProductsWithPagination sekarang otomatis terfilter oleh Global Scope.
     */
    public function getAllProductsWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return Product::with('category')->latest()->paginate($perPage);
    }

    /**
     * Mendapatkan semua kategori untuk dropdown.
     */
    public function getAllCategories(): Collection
    {
        return ProductCategory::where('is_active', true)->get();
    }

    /**
     * Membuat produk baru beserta inventory-nya UNTUK BISNIS SAAT INI.
     */
    public function createProduct(array $data): Product
    {
        $data['business_id'] = Auth::user()->business_id;

        return DB::transaction(function () use ($data) {
            $product = Product::create($data);

            $branchContext = app(\App\Support\BranchContext::class);
            $minStock = isset($data['min_stock']) ? (int) $data['min_stock'] : 10;
            $initialStock = isset($data['initial_stock']) ? (int) $data['initial_stock'] : 0;

            // Terapkan min_stock ke SEMUA baris inventory cabang aktif hasil sync
            $branchContext->runUnscoped(function () use ($product, $minStock) {
                \App\Models\Inventory::where('product_id', $product->id)
                    ->where('business_id', $product->business_id)
                    ->update(['min_stock' => $minStock]);
            });

            // Terapkan initial_stock sesuai mode
            if ($branchContext->isBranch()) {
                $activeBranchId = $branchContext->getBranchId();
                if ($activeBranchId && $initialStock > 0) {
                    $branchContext->runUnscoped(function () use ($product, $activeBranchId, $initialStock) {
                        \App\Models\Inventory::where('product_id', $product->id)
                            ->where('branch_id', $activeBranchId)
                            ->update(['current_stock' => $initialStock]);
                    });
                }
            } else {
                if ($initialStock > 0) {
                    session()->flash('info', 'Stok awal diisi lewat Terima Stok Supplier di cabang.');
                }
            }

            return $product;
        });
    }

    /**
     * Memperbarui data produk.
     * Otomatis terfilter oleh Global Scope saat mengambil data.
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);
        return $product;
    }

    /**
     * Menghapus produk.
     * Otomatis terfilter oleh Global Scope saat mengambil data.
     */
    public function deleteProduct(Product $product): void
    {
        if ($product->transactionDetails()->exists()) {
            $product->update(['is_active' => false]);
            throw new \Exception("Produk tidak dapat dihapus karena memiliki riwayat transaksi. Produk telah diarsipkan.");
        }

        $product->delete();
    }
}
