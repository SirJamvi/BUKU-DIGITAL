<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * Mendapatkan semua user DARI BISNIS SAAT INI dengan paginasi.
     */
    public function getAllUsersWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        $businessId = Auth::user()->business_id;

        return User::where('business_id', $businessId)
                   ->latest()
                   ->paginate($perPage);
    }

    /**
     * Membuat user baru.
     */
    public function createUser(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        $data['business_id'] = Auth::user()->business_id;

        if (!in_array($data['role'], ['admin', 'kasir', 'driver', 'ko'])) {
            $data['role'] = 'kasir';
        }

        return User::create($data);
    }

    /**
     * Memperbarui user.
     */
    public function updateUser(User $user, array $data): User
    {
        if ($user->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        return $user;
    }

    public function deleteUser(User $user): void
    {
        if ($user->id === Auth::id()) {
            throw new \Exception("Anda tidak dapat menghapus akun Anda sendiri.");
        }

        if ($user->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }

        $user->delete();
    }

    /**
     * Membalikkan (toggle) status aktif/nonaktif user.
     */
    public function toggleStatus(User $user): User
    {
        // Pastikan admin hanya bisa mengubah user dari bisnisnya sendiri
        if ($user->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }

        // Mencegah user menonaktifkan dirinya sendiri (double protection, selain di view)
        if ($user->id === Auth::id()) {
            abort(403, 'Anda tidak dapat mengubah status akun Anda sendiri.');
        }

        $user->update(['is_active' => !$user->is_active]);

        return $user;
    }

    /**
     * Mendapatkan daftar semua driver dalam bisnis saat ini.
     */
    public function getDriversList($businessId)
    {
        return User::where('business_id', $businessId)
            ->where(function ($q) use ($businessId) {
                $q->where('role', 'driver')
                  ->orWhereIn('id', function ($sub) use ($businessId) {
                      $sub->select('driver_id')
                          ->from('transactions')
                          ->where('business_id', $businessId)
                          ->whereNotNull('driver_id');
                  })
                  ->orWhereIn('id', function ($sub) use ($businessId) {
                      $sub->select('claimed_by_driver_id')
                          ->from('transactions')
                          ->where('business_id', $businessId)
                          ->whereNotNull('claimed_by_driver_id');
                  });
            })
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'phone']);
    }

    /**
     * Rekap Pengiriman Driver berdasarkan rentang tanggal & opsi filter driver tertentu.
     */
    public function getDriverDeliveryRecap($businessId, ?string $startDate = null, ?string $endDate = null, ?string $driverId = null): array
    {
        $startDate = $startDate ?: Carbon::now()->startOfMonth()->toDateString();
        $endDate = $endDate ?: Carbon::now()->toDateString();

        $query = Transaction::with([
                'customer',
                'driver',
                'details.product'
            ])
            ->where('business_id', $businessId)
            ->where('type', 'sale')
            ->where(function ($q) {
                $q->whereNotNull('driver_id')
                  ->orWhereNotNull('claimed_by_driver_id');
            })
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate);

        if (!empty($driverId) && $driverId !== 'all') {
            $query->where(function ($q) use ($driverId) {
                $q->where('driver_id', $driverId)
                  ->orWhere('claimed_by_driver_id', $driverId);
            });
        }

        $driverTransactions = $query->orderBy('transaction_date', 'desc')->get();

        $driverRecap = [];
        $groupedByDriver = $driverTransactions->groupBy(function ($item) {
            return $item->driver_id ?: ($item->claimed_by_driver_id ?: 'unknown');
        });

        foreach ($groupedByDriver as $dId => $trxs) {
            $driver = $trxs->first()->driver ?? User::find($dId);
            $driverName = $driver ? $driver->name : 'Driver #' . $dId;
            $driverPhone = $driver ? $driver->phone : '-';

            // Kelompokkan per Surat Jalan / Ritase (Trip)
            $trips = $trxs->groupBy(function ($t) {
                return $t->delivery_trip_code ?: ('TRX-' . str_pad($t->id, 6, '0', STR_PAD_LEFT));
            });

            $totalQty = 0;
            $productQuantities = [];

            foreach ($trxs as $trx) {
                foreach ($trx->details as $detail) {
                    $pName = $detail->product->name ?? 'Produk';
                    if ($detail->product && $detail->product->isService()) {
                        continue; // Abaikan produk jasa/ongkir dari hitungan muatan fisik ball
                    }
                    $qty = (int) $detail->quantity;
                    $totalQty += $qty;
                    $productQuantities[$pName] = ($productQuantities[$pName] ?? 0) + $qty;
                }
            }

            $totalAmount = $trxs->sum('total_amount');
            $totalPaid = $trxs->where('payment_status', 'paid')->sum('total_amount');
            $totalPending = $trxs->where('payment_status', 'pending')->sum('total_amount');

            // Breakdown rincian per trip / surat jalan
            $tripList = [];
            foreach ($trips as $tripCode => $tripItems) {
                $tripQty = 0;
                $tripProductBreakdown = [];
                foreach ($tripItems as $tItem) {
                    foreach ($tItem->details as $d) {
                        $pName = $d->product->name ?? 'Produk';
                        if ($d->product && $d->product->isService()) {
                            continue;
                        }
                        $qty = (int) $d->quantity;
                        $tripQty += $qty;
                        $tripProductBreakdown[$pName] = ($tripProductBreakdown[$pName] ?? 0) + $qty;
                    }
                }

                $tripList[] = [
                    'trip_code' => $tripCode,
                    'is_batch' => str_starts_with($tripCode, 'TRIP-'),
                    'time' => $tripItems->first()->transaction_date,
                    'total_orders' => $tripItems->count(),
                    'total_qty' => $tripQty,
                    'product_breakdown' => $tripProductBreakdown,
                    'total_amount' => $tripItems->sum('total_amount'),
                    'transactions' => $tripItems,
                ];
            }

            $driverRecap[] = [
                'driver_id' => $dId,
                'driver_name' => $driverName,
                'driver_phone' => $driverPhone,
                'total_trips' => $trips->count(),
                'total_orders' => $trxs->count(),
                'total_qty' => $totalQty,
                'product_quantities' => $productQuantities,
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'total_pending' => $totalPending,
                'trips' => $tripList,
            ];
        }

        return $driverRecap;
    }
}