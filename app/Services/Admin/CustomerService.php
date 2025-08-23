<?php

namespace App\Services\Admin;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CustomerService
{
    /**
     * Mendapatkan semua pelanggan dengan paginasi untuk admin.
     */
    public function getAllCustomersWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::latest('join_date')->paginate($perPage);
    }

    /**
     * Mendapatkan riwayat transaksi seorang pelanggan.
     */
    public function getCustomerTransactions(Customer $customer, int $perPage = 10): LengthAwarePaginator
    {
        return $customer->transactions()->with('createdBy')->latest()->paginate($perPage);
    }

    /**
     * [BARU] Memperbarui data pelanggan yang sudah ada.
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        // Keamanan: Pastikan customer milik business yang sama dengan user login
        $user = Auth::user();

        if ($customer->business_id !== $user->business_id) {
            abort(403, 'Akses ditolak.');
        }

        $customer->update($data);

        return $customer;
    }
}
