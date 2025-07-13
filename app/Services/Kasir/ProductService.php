<?php

namespace App\Services\Kasir;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Mendapatkan semua produk dengan paginasi UNTUK BISNIS SAAT INI.
     */
    public function getAllProductsWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        $businessId = Auth::user()->business_id;
        return Product::where('business_id', $businessId)
                      ->with('category')
                      ->latest()
                      ->paginate($perPage);
    }

    /**
     * Membuat produk baru beserta inventory-nya UNTUK BISNIS SAAT INI.
     */
    public function createProduct(array $data): Product
    {
        // Secara otomatis tambahkan business_id saat membuat produk
        $data['business_id'] = Auth::user()->business_id;
        return DB::transaction(function () use ($data) {
            $product = Product::create($data);
            $product->inventory()->create([
                'current_stock' => $data['initial_stock'] ?? 0,
                'min_stock' => $data['min_stock'] ?? 10,
                'business_id' => $data['business_id']
            ]);
            return $product;
        });
    }

    /**
     * Mendapatkan produk yang bisa dijual (aktif dan ada stok) dengan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAvailableProducts(int $perPage = 20): LengthAwarePaginator
    {
        $businessId = Auth::user()->business_id;
        return Product::where('business_id', $businessId)
            ->where('is_active', true)
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
        $businessId = Auth::user()->business_id;
        return Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('sku', 'like', "%{$searchTerm}%");
            })
            ->with('inventory')
            ->limit(10)
            ->get();
    }
}