<?php

namespace App\Services\Kasir;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CustomerService
{
    /**
     * Mengambil semua pelanggan dengan paginasi.
     * Global Scope sudah otomatis memfilter.
     */
    public function getAllCustomersWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::latest('join_date')->paginate($perPage);
    }

    /**
     * Membuat pelanggan baru untuk bisnis saat ini.
     */
    public function createCustomer(array $data): Customer
    {
        // INI PERBAIKANNYA:
        $data['business_id'] = Auth::user()->business_id;
        $data['created_by'] = Auth::id();
        $data['join_date'] = now();
        return Customer::create($data);
    }

    /**
     * Mendapatkan detail pelanggan beserta riwayat transaksinya.
     */
    public function getCustomerDetails(Customer $customer): array
    {
        // Global scope sudah memastikan kita hanya bisa melihat pelanggan dari bisnis kita.
        // Keamanan tambahan untuk memastikan kasir tidak melihat data pelanggan lain.
        if ($customer->business_id !== Auth::user()->business_id) {
            abort(404);
        }

        $transactions = $customer->transactions()->with('details.product')->latest()->paginate(10);

        return [
            'customer' => $customer,
            'transactions' => $transactions,
        ];
    }
}