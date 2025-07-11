<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Kasir\ProductService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Menampilkan daftar produk yang tersedia untuk kasir.
     */
    public function index(): View
    {
        $products = $this->productService->getAvailableProducts();
        return view('kasir.products.index', compact('products'));
    }

    /**
     * Menampilkan detail satu produk.
     */
    public function show(Product $product): View
    {
        // Memastikan produk yang ditampilkan adalah produk yang aktif
        if (!$product->is_active) {
            abort(404);
        }
        return view('kasir.products.show', compact('product'));
    }

    /**
     * Mencari produk untuk POS (via AJAX/API).
     */
    public function search(Request $request): JsonResponse
    {
        $searchTerm = $request->input('q', '');
        
        if (strlen($searchTerm) < 2) {
            return response()->json([]);
        }

        $products = $this->productService->searchProducts($searchTerm);

        return response()->json($products);
    }
}