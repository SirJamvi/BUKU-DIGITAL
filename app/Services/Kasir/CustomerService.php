<?php

namespace App\Services\Kasir;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CustomerService
{
    /**
     * Mendapatkan semua pelanggan dengan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllCustomersWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::latest('join_date')->paginate($perPage);
    }

    /**
     * Membuat pelanggan baru.
     *
     * @param array $data
     * @return Customer
     */
    public function createCustomer(array $data): Customer
    {
        $data['created_by'] = Auth::id();
        $data['join_date'] = now();
        return Customer::create($data);
    }

    /**
     * Mendapatkan detail pelanggan beserta riwayat transaksinya.
     *
     * @param Customer $customer
     * @return array
     */
    public function getCustomerDetails(Customer $customer): array
    {
        $transactions = $customer->transactions()->with('details.product')->latest()->paginate(10);

        return [
            'customer' => $customer,
            'transactions' => $transactions,
        ];
    }
}