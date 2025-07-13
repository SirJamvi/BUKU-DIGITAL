<?php

namespace App\Services\Admin;

use App\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class SupplierService
{
    public function getAllSuppliersWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return Supplier::where('business_id', Auth::user()->business_id)->latest()->paginate($perPage);
    }

    public function createSupplier(array $data): Supplier
    {
        $data['business_id'] = Auth::user()->business_id;
        $data['created_by'] = Auth::id();
        return Supplier::create($data);
    }
    
    public function updateSupplier(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier;
    }

    public function deleteSupplier(Supplier $supplier): void
    {
        // Tambahkan validasi di sini jika supplier terhubung dengan data lain
        // if ($supplier->purchaseOrders()->exists()) {
        //     throw new \Exception("Supplier tidak dapat dihapus karena memiliki riwayat pesanan.");
        // }
        $supplier->delete();
    }
}