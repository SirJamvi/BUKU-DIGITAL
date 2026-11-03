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
     * Mendukung pencarian berdasarkan nama, telepon utama, telepon alternatif, atau email.
     */
    public function getAllCustomersWithPagination(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Customer::query()
            ->with('phones')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('phones', function ($qp) use ($search) {
                            $qp->where('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('join_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Membuat pelanggan baru untuk bisnis saat ini.
     */
    public function createCustomer(array $data): Customer
    {
        $phones = $data['phones'] ?? [];
        unset($data['phones']);

        $data['business_id'] = Auth::user()->business_id;
        $data['created_by'] = Auth::id();
        $data['join_date'] = now();
        $customer = Customer::create($data);

        // Simpan nomor telepon utama ke customer_phones jika diisi
        if (!empty($customer->phone)) {
            $customer->phones()->create([
                'phone'      => $customer->phone,
                'label'      => 'Utama',
                'is_primary' => true,
            ]);
        }

        // Simpan nomor telepon tambahan
        if (!empty($phones) && is_array($phones)) {
            foreach ($phones as $p) {
                if (!empty($p['phone'])) {
                    $customer->phones()->create([
                        'phone'      => trim($p['phone']),
                        'label'      => !empty($p['label']) ? trim($p['label']) : 'Tambahan',
                        'is_primary' => false,
                    ]);
                }
            }
        }

        return $customer;
    }

    /**
     * Mendapatkan detail pelanggan beserta riwayat transaksinya.
     */
    public function getCustomerDetails(Customer $customer): array
    {
        if ($customer->business_id !== Auth::user()->business_id) {
            abort(404);
        }

        $customer->load('phones');
        $transactions = $customer->transactions()->with('details.product')->latest()->paginate(10);

        return [
            'customer' => $customer,
            'transactions' => $transactions,
        ];
    }

    /**
     * Memperbarui data pelanggan beserta nomor telepon tambahannya.
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        if ($customer->business_id !== Auth::user()->business_id) {
            abort(404);
        }

        $phones = $data['phones'] ?? null;
        unset($data['phones']);

        $customer->update($data);

        // Jika nomor utama berubah/diisi, sinkronkan record primary di customer_phones
        if (!empty($customer->phone)) {
            $primaryPhone = $customer->phones()->where('is_primary', true)->first();
            if ($primaryPhone) {
                $primaryPhone->update(['phone' => $customer->phone]);
            } else {
                $customer->phones()->create([
                    'phone'      => $customer->phone,
                    'label'      => 'Utama',
                    'is_primary' => true,
                ]);
            }
        }

        // Sinkronisasi nomor telepon tambahan jika dikirim
        if ($phones !== null && is_array($phones)) {
            $customer->phones()->where('is_primary', false)->delete();

            foreach ($phones as $p) {
                if (!empty($p['phone'])) {
                    $customer->phones()->create([
                        'phone'      => trim($p['phone']),
                        'label'      => !empty($p['label']) ? trim($p['label']) : 'Tambahan',
                        'is_primary' => false,
                    ]);
                }
            }
        }

        return $customer;
    }
}
