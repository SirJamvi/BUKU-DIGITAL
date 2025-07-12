<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\InventoryService;
use App\Http\Resources\Admin\InventoryResource;
use Illuminate\Http\Request;

class InventoryApiController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Mengembalikan status inventaris saat ini dengan paginasi.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $inventory = $this->inventoryService->getInventoryWithPagination($request->get('per_page', 15));
        return InventoryResource::collection($inventory);
    }

    /**
     * Mengembalikan data pergerakan stok (stock movements).
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function stockMovements(Request $request)
    {
        $movements = $this->inventoryService->getStockMovementsWithPagination($request->get('per_page', 25));
        // Anda bisa membuat Resource khusus untuk stock movement jika perlu
        return response()->json($movements);
    }
}