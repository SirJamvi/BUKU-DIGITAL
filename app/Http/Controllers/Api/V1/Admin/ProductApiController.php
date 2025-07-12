<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ProductService;
use App\Http\Resources\Admin\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductApiController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Mengembalikan daftar produk dengan paginasi.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $products = $this->productService->getAllProductsWithPagination($request->get('per_page', 15));
        return ProductResource::collection($products);
    }

    /**
     * Mencari produk berdasarkan query.
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function search(Request $request)
    {
        $searchTerm = $request->query('q', '');

        if (empty($searchTerm)) {
            return ProductResource::collection([]);
        }

        $products = $this->productService->searchProducts($searchTerm);
        return ProductResource::collection($products);
    }
}