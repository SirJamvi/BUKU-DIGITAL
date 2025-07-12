<?php

namespace App\Services\Admin;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

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
}