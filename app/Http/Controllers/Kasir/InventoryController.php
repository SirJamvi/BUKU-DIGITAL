<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Admin\InventoryService;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    // --- 2. FITUR PECAH BALL (DINAMIS) ---

    public function breakUnitForm(): View
    {
        // Ambil semua produk yang aktif untuk dijadikan pilihan dinamis
        $products = Product::where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->get();

        return view('kasir.inventory.break_unit', compact('products'));
    }

    public function processBreakUnit(Request $request): RedirectResponse
    {
        // Validasi input dinamis
        $request->validate([
            'source_product_id' => 'required|exists:products,id',
            'source_qty' => 'required|integer|min:1',
            'targets' => 'required|array|min:1',
            'targets.*.product_id' => 'required|exists:products,id',
            'targets.*.qty' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $businessId = Auth::user()->business_id;
            $userId = Auth::id();

            // 1. Ambil data stok produk asal
            $sourceInventory = Inventory::where('product_id', $request->source_product_id)
                ->where('business_id', $businessId)
                ->first();

            if (!$sourceInventory) {
                throw new \Exception("Data inventaris untuk produk ini belum ada di sistem.");
            }

            // Ambil stok saat ini
            $currentStock = $sourceInventory->current_stock ?? 0;

            // Proteksi: Cek apakah stok mencukupi
            if ($currentStock < $request->source_qty) {
                throw new \Exception("Stok produk asal tidak mencukupi. Sisa stok saat ini: " . $currentStock . ". Anda mencoba memecah: " . $request->source_qty);
            }

            // Kurangi stok utama
            $sourceInventory->decrement('current_stock', $request->source_qty);

            // Catat pergerakan stok keluar (TANPA occurred_at)
            StockMovement::create([
                'business_id' => $businessId,
                'product_id' => $request->source_product_id,
                'type' => 'out',
                'quantity' => $request->source_qty,
                'notes' => 'Pecah Ball (Bahan Baku)',
                'created_by' => $userId,
            ]);

            // 2. Tambahkan stok untuk setiap produk hasil pecahan
            $totalHasilPecahan = 0;
            foreach ($request->targets as $target) {
                // Cari target, jika belum ada buat baris inventaris baru dengan nilai 0
                $targetInventory = Inventory::firstOrCreate(
                    ['product_id' => $target['product_id'], 'business_id' => $businessId],
                    ['current_stock' => 0]
                );

                // Tambahkan stok hasil
                $targetInventory->increment('current_stock', $target['qty']);

                // Catat pergerakan stok masuk (TANPA occurred_at)
                StockMovement::create([
                    'business_id' => $businessId,
                    'product_id' => $target['product_id'],
                    'type' => 'in',
                    'quantity' => $target['qty'],
                    'notes' => 'Hasil Pecahan dari Produk ID: ' . $request->source_product_id,
                    'created_by' => $userId,
                ]);

                $totalHasilPecahan += $target['qty'];
            }

            DB::commit();
            return redirect()->back()->with('success', "Berhasil memecah {$request->source_qty} unit menjadi total {$totalHasilPecahan} kemasan baru.");
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Kasir error breaking unit dinamis: ' . $e->getMessage());
            return back()->with('error', $e->getMessage());
        }
    }

    // --- 3. FITUR STOCK OPNAME ---

    public function stockOpnameForm(): View
    {
        // Ambil produk aktif beserta relasi inventory-nya
        $products = $this->inventoryService->getActiveProducts()->filter(function ($product) {
            return $product->inventory !== null;
        });

        return view('kasir.inventory.stock_opname', compact('products'));
    }

    public function processStockOpname(Request $request): RedirectResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.inventory_id' => 'required|exists:inventory,id',
            'items.*.actual_stock' => 'required|integer|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        try {
            $this->inventoryService->processStockOpname($request->all());
            return redirect()->back()->with('success', 'Stock Opname berhasil disimpan. Data stok telah disesuaikan dengan fisik.');
        } catch (\Exception $e) {
            logger()->error('Kasir error stock opname: ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses stock opname: ' . $e->getMessage());
        }
    }
}
