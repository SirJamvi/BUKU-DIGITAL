<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\Product;
use App\Services\Admin\SupplierService;
use App\Http\Requests\Admin\StoreSupplierRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    protected SupplierService $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }

    public function index(): View
    {
        $suppliers = $this->supplierService->getAllSuppliersWithPagination();
        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        // Ambil semua produk aktif untuk dropdown di form
        $products = Product::where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->get();
            
        return view('admin.suppliers.create', compact('products'));
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        
        // Buat supplier melalui service
        $supplier = $this->supplierService->createSupplier($validated);
        
        // Fallback jika createSupplier tidak me-return object Supplier
        if (!$supplier) {
            $supplier = Supplier::where('business_id', Auth::user()->business_id)
                ->where('name', $request->name)
                ->latest()->first();
        }

        // Sinkronisasi data produk & harganya
        $this->syncSupplierProducts($supplier, $request->input('products', []));

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        // Ambil produk aktif & Load data pivot dari supplier ini
        $products = Product::where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->get();
            
        $supplier->load('products');

        return view('admin.suppliers.edit', compact('supplier', 'products'));
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validated();
        
        // Update melalui service
        $this->supplierService->updateSupplier($supplier, $validated);

        // Sinkronisasi data produk & harganya
        $this->syncSupplierProducts($supplier, $request->input('products', []));

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        try {
            $this->supplierService->deleteSupplier($supplier);
            return redirect()->route('admin.suppliers.index')->with('success', 'Supplier berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Fungsi Helper untuk Sync data Pivot Product-Supplier
     */
    private function syncSupplierProducts($supplier, $productsData)
    {
        if (!$supplier) return;

        $syncData = [];
        if (is_array($productsData)) {
            foreach ($productsData as $prod) {
                if (!empty($prod['id']) && isset($prod['price'])) {
                    $syncData[$prod['id']] = ['price' => $prod['price']];
                }
            }
        }
        
        // Simpan ke tabel pivot product_supplier
        $supplier->products()->sync($syncData);
    }
}