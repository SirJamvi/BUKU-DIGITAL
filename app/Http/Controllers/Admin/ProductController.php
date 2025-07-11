<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Services\Admin\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(): View
    {
        $products = $this->productService->getAllProductsWithPagination();
        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        $categories = $this->productService->getAllCategories();
        return view('admin.products.create', compact('categories'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        try {
            $this->productService->createProduct($request->validated());
            return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dibuat.');
        } catch (\Exception $e) {
            logger()->error('Error creating product: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat produk. Silakan coba lagi.');
        }
    }
    
    public function show(Product $product): View
    {
        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $categories = $this->productService->getAllCategories();
        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        try {
            $this->productService->updateProduct($product, $request->validated());
            return redirect()->route('admin.products.index')->with('success', 'Produk berhasil diperbarui.');
        } catch (\Exception $e) {
            logger()->error('Error updating product: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui produk. Silakan coba lagi.');
        }
    }

    public function destroy(Product $product): RedirectResponse
    {
        try {
            $this->productService->deleteProduct($product);
            return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dihapus.');
        } catch (\Exception $e) {
            logger()->error('Error deleting product: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus produk. Silakan coba lagi.');
        }
    }
}
