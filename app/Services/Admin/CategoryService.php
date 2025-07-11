<?php

namespace App\Services\Admin;

use App\Models\ProductCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CategoryService
{
    public function getAllCategoriesWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return ProductCategory::with('parent')->latest()->paginate($perPage);
    }

    public function getAllCategories(): Collection
    {
        return ProductCategory::where('is_active', true)->get();
    }

    public function createCategory(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    public function updateCategory(ProductCategory $category, array $data): ProductCategory
    {
        $category->update($data);
        return $category;
    }

    public function deleteCategory(ProductCategory $category): void
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            throw new \Exception("Kategori tidak dapat dihapus karena memiliki produk atau sub-kategori.");
        }
        $category->delete();
    }
}