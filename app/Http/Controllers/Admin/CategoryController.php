<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\ProductCategory;
use App\Services\Admin\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(): View
    {
        $categories = $this->categoryService->getAllCategoriesWithPagination();
        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        $parentCategories = $this->categoryService->getAllCategories();
        return view('admin.categories.create', compact('parentCategories'));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        try {
            $this->categoryService->createCategory($request->validated());
            return redirect()->route('admin.categories.index')->with('success', 'Kategori berhasil dibuat.');
        } catch (\Exception $e) {
            logger()->error('Error creating category: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat kategori. Silakan coba lagi.');
        }
    }
    
    public function show(ProductCategory $category): View
    {
        return view('admin.categories.show', compact('category'));
    }

    public function edit(ProductCategory $category): View
    {
        $parentCategories = $this->categoryService->getAllCategories();
        return view('admin.categories.edit', compact('category', 'parentCategories'));
    }

    public function update(UpdateCategoryRequest $request, ProductCategory $category): RedirectResponse
    {
        try {
            $this->categoryService->updateCategory($category, $request->validated());
            return redirect()->route('admin.categories.index')->with('success', 'Kategori berhasil diperbarui.');
        } catch (\Exception $e) {
            logger()->error('Error updating category: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui kategori. Silakan coba lagi.');
        }
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        try {
            $this->categoryService->deleteCategory($category);
            return redirect()->route('admin.categories.index')->with('success', 'Kategori berhasil dihapus.');
        } catch (\Exception $e) {
            logger()->error('Error deleting category: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus kategori. Silakan coba lagi.');
        }
    }
}