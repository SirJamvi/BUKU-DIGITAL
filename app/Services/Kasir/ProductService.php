<?php

namespace App\Services\Kasir;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Mendapatkan produk yang bisa dijual (aktif dan ada stok) dengan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAvailableProducts(int $perPage = 20): LengthAwarePaginator
    {
        return Product::where('is_active', true)
            ->whereHas('inventory', function ($query) {
                $query->where('current_stock', '>', 0);
            })
            ->with('category', 'inventory')
            ->paginate($perPage);
    }

    /**
     * Mencari produk berdasarkan nama atau SKU.
     *
     * @param string $searchTerm
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchProducts(string $searchTerm)
    {
        return Product::where('is_active', true)
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('sku', 'like', "%{$searchTerm}%");
            })
            ->with('inventory')
            ->limit(10)
            ->get();
    }
}