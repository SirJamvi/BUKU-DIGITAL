<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\Admin\SupplierService;
use App\Http\Requests\Admin\StoreSupplierRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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
        return view('admin.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->supplierService->createSupplier($request->validated());
        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->supplierService->updateSupplier($supplier, $request->validated());
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
}