<?php

namespace App\Services\Admin;

use App\Models\ProductCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth; // <-- Tambahkan ini

class CategoryService
{
    /**
     * Mengambil kategori HANYA untuk bisnis saat ini.
     * Global Scope sudah menangani ini secara otomatis.
     */
    public function getAllCategoriesWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return ProductCategory::with('parent')->withCount('products')->latest()->paginate($perPage);
    }

    /**
     * Mengambil kategori HANYA untuk bisnis saat ini untuk dropdown.
     */
    public function getAllCategories(): Collection
    {
        // Pastikan dropdown juga hanya menampilkan kategori dari bisnis yang sama
        return ProductCategory::where('is_active', true)
                              ->where('business_id', Auth::user()->business_id)
                              ->get();
    }

    /**
     * Membuat kategori baru dan secara otomatis mengikatnya ke bisnis saat ini.
     */
    public function createCategory(array $data): ProductCategory
    {
        // INI PERBAIKANNYA:
        $data['business_id'] = Auth::user()->business_id;
        $data['created_by'] = Auth::id();

        return ProductCategory::create($data);
    }

    /**
     * Memperbarui kategori.
     */
    public function updateCategory(ProductCategory $category, array $data): ProductCategory
    {
        // Keamanan tambahan: pastikan admin hanya bisa edit kategori bisnisnya
        if ($category->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }
        $category->update($data);
        return $category;
    }

    /**
     * Menghapus kategori.
     */
    public function deleteCategory(ProductCategory $category): void
    {
        // Keamanan tambahan: pastikan admin hanya bisa hapus kategori bisnisnya
        if ($category->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }

        if ($category->products()->exists() || $category->children()->exists()) {
            throw new \Exception("Kategori tidak dapat dihapus karena memiliki produk atau sub-kategori terkait.");
        }
        $category->delete();
    }
}