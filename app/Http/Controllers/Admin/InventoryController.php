<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\InventoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(): View
    {
        $inventory = $this->inventoryService->getInventoryWithPagination();
        return view('admin.inventory.index', compact('inventory'));
    }

    public function stockMovements(): View
    {
        $movements = $this->inventoryService->getStockMovementsWithPagination();
        return view('admin.inventory.stock-movements', compact('movements'));
    }
    
    public function stockOpname(): View
    {
        return view('admin.inventory.stock-opname');
    }
    
    public function processStockOpname(Request $request): RedirectResponse
    {
        try {
            $this->inventoryService->processStockOpname($request->all());
            return redirect()->route('admin.inventory.index')->with('success', 'Stock opname berhasil diproses.');
        } catch (\Exception $e) {
            logger()->error('Error processing stock opname: ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses stock opname. Silakan coba lagi.');
        }
    }
}