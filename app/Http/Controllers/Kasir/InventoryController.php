<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Admin\InventoryService;
use App\Models\ProductConversion; // PASTIKAN BARIS INI ADA
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // --- 1. FITUR INPUT STOK DARI SUPPLIER ---

    public function addStockForm(): View
    {
        $products = $this->inventoryService->getActiveProducts();
        return view('kasir.inventory.add_stock', compact('products'));
    }

    public function storeStock(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->inventoryService->addStock($request->all());
            return redirect()->back()->with('success', 'Stok dari supplier berhasil ditambahkan.');
        } catch (\Exception $e) {
            logger()->error('Kasir error adding stock: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan stok.')->withInput();
        }
    }

    // --- 2. FITUR PECAH BALL ---

    public function breakUnitForm(): View
    {
        // Ambil aturan konversi yang berlaku untuk bisnis ini
        $conversions = ProductConversion::with(['fromProduct', 'toProduct'])
            ->where('business_id', Auth::user()->business_id)
            ->get();

        return view('kasir.inventory.break_unit', compact('conversions'));
    }

    public function processBreakUnit(Request $request): RedirectResponse
    {
        $request->validate([
            'conversion_id' => 'required|exists:product_conversions,id',
            'multiplier' => 'required|integer|min:1', // Berapa ball yang mau dipecah sekaligus
        ]);

        try {
            $conversion = ProductConversion::findOrFail($request->conversion_id);

            $quantityToBreak = $conversion->quantity_to_break * $request->multiplier;
            $totalYield = $conversion->yield_amount * $request->multiplier;

            $this->inventoryService->breakUnit(
                $conversion->from_product_id,
                $conversion->to_product_id,
                $quantityToBreak,
                $totalYield
            );

            return redirect()->back()->with('success', "Berhasil memecah {$quantityToBreak} ball menjadi {$totalYield} eceran.");
        } catch (\Exception $e) {
            logger()->error('Kasir error breaking unit: ' . $e->getMessage());
            return back()->with('error', $e->getMessage());
        }
    }
}
