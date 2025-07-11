<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Mendapatkan semua produk dengan relasi dan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllProductsWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return Product::with('category')->latest()->paginate($perPage);
    }

    /**
     * Mendapatkan semua kategori untuk dropdown.
     *
     * @return Collection
     */
    public function getAllCategories(): Collection
    {
        return ProductCategory::where('is_active', true)->get();
    }

    /**
     * Membuat produk baru beserta inventory-nya.
     *
     * @param array $data
     * @return Product
     */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create($data);

            // Buat inventory awal untuk produk
            $product->inventory()->create([
                'current_stock' => $data['initial_stock'] ?? 0,
                'min_stock' => $data['min_stock'] ?? 10,
            ]);

            return $product;
        });
    }

    /**
     * Memperbarui data produk.
     *
     * @param Product $product
     * @param array $data
     * @return Product
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);
        return $product;
    }

    /**
     * Menghapus produk.
     *
     * @param Product $product
     * @return void
     */
    public function deleteProduct(Product $product): void
    {
        // Tambahkan validasi jika produk sudah pernah ada transaksi
        if ($product->transactionDetails()->exists()) {
            // Sebaiknya jangan dihapus, tapi di-nonaktifkan
            $product->update(['is_active' => false]);
            throw new \Exception("Produk tidak dapat dihapus karena memiliki riwayat transaksi. Produk telah diarsipkan.");
        }

        $product->delete();
    }
}