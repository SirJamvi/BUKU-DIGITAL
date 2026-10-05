<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Admin\InventoryService;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\CashFlow;
use App\Models\PaymentMethod;
use App\Models\ExpenseCategory;
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
        $businessId = Auth::user()->business_id;

        // Ambil supplier beserta produk yang disuplai dan harga khusus (pivot)
        $suppliers = Supplier::where('business_id', $businessId)->with('products')->get();

        // Ambil metode pembayaran yang aktif
        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->get();

        return view('kasir.inventory.add_stock', compact('suppliers', 'paymentMethods'));
    }

    public function storeStock(Request $request): RedirectResponse
    {
        // Validasi input: payment_method hanya wajib jika record_expense dicentang
        $request->validate([
            'supplier_id'    => 'required|exists:suppliers,id',
            'product_id'     => 'required|exists:products,id',
            'quantity'       => 'required|integer|min:1',
            'record_expense' => 'nullable|boolean',
            'payment_method' => 'required_if:record_expense,1|nullable|string',
            'notes'          => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $businessId = Auth::user()->business_id;
            $userId = Auth::id();

            // 1. Dapatkan relasi produk & supplier untuk mengecek harga pivot
            $supplier = Supplier::findOrFail($request->supplier_id);
            $productPivot = $supplier->products()->where('product_id', $request->product_id)->first();

            if (!$productPivot) {
                throw new \Exception("Produk ini belum diatur harganya untuk supplier yang dipilih.");
            }

            // 2. Update Inventory & Catat Stock Movement
            $inventory = Inventory::firstOrCreate(
                ['product_id' => $request->product_id, 'business_id' => $businessId],
                ['current_stock' => 0]
            );
            $inventory->increment('current_stock', $request->quantity);

            StockMovement::create([
                'business_id' => $businessId,
                'product_id'  => $request->product_id,
                'type'        => 'in',
                'quantity'    => $request->quantity,
                'notes'       => 'Restock dari ' . $supplier->name . '. ' . $request->notes,
                'created_by'  => $userId,
            ]);

            $message = 'Stok berhasil ditambahkan (Tanpa mencatat pengeluaran).';

            // 3. Catat ke Expenses (Cash Flow) JIKA toggle dicentang
            if ($request->has('record_expense') && $request->record_expense == 1) {
                $unitPrice = $productPivot->pivot->price;
                $grandTotalExpense = $unitPrice * $request->quantity;

                // Cari atau buat kategori "Refill Es Batu" khusus untuk bisnis ini
                $expenseCategory = ExpenseCategory::firstOrCreate(
                    [
                        'business_id' => $businessId,
                        'name'        => 'Refill Es Batu',
                    ],
                    [
                        'type'        => 'Operasional',
                        'is_cogs'     => 1,
                        'is_active'   => 1,
                    ]
                );

                CashFlow::create([
                    'business_id'    => $businessId,
                    'type'           => 'expense',
                    'category_id'    => $expenseCategory->id, 
                    'amount'         => $grandTotalExpense,
                    'payment_method' => $request->payment_method,
                    'description'    => "Pembelian " . $request->quantity . " unit " . $productPivot->name . " dari " . $supplier->name,
                    'date'           => now()->toDateString(),
                    'created_by'     => $userId,
                ]);

                $message = 'Stok berhasil ditambahkan dan biaya otomatis tercatat di Pengeluaran.';
            }

            DB::commit();
            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Kasir error adding stock & expenses: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan stok: ' . $e->getMessage())->withInput();
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