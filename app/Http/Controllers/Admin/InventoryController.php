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

    /**
     * Menampilkan form untuk menambah stok.
     */
    public function addStock(): View
    {
        $products = $this->inventoryService->getActiveProducts();
        return view('admin.inventory.add_stock', compact('products'));
    }

    /**
     * Menyimpan data penambahan stok.
     */
    public function storeStock(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->inventoryService->addStock($request->all());
            return redirect()->route('admin.inventory.index')->with('success', 'Stok berhasil ditambahkan.');
        } catch (\Exception $e) {
            logger()->error('Error adding stock: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan stok. Silakan coba lagi.');
        }
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