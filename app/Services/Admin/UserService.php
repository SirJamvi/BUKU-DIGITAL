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
     * Normalisasi & bersihkan array secondary_roles:
     * - Lowercase & trim
     * - Hanya izinkan opsi valid pada User::SECONDARY_ROLES
     * - Hapus role yang sama dengan Role Utama
     * - Kembalikan null jika kosong agar perilaku default tetap bersih
     */
    protected function sanitizeSecondaryRoles(mixed $secondaryRoles, string $primaryRole): ?array
    {
        if (!is_array($secondaryRoles)) {
            return null;
        }

        $allowed = array_keys(User::SECONDARY_ROLES);
        $normalizedPrimary = strtolower(trim($primaryRole));

        $cleaned = [];
        foreach ($secondaryRoles as $role) {
            if (!is_string($role)) {
                continue;
            }
            $r = strtolower(trim($role));
            if ($r !== '' && $r !== $normalizedPrimary && in_array($r, $allowed, true)) {
                $cleaned[] = $r;
            }
        }

        $cleaned = array_values(array_unique($cleaned));

        return empty($cleaned) ? null : $cleaned;
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

        $data['secondary_roles'] = $this->sanitizeSecondaryRoles(
            $data['secondary_roles'] ?? null,
            $data['role']
        );
        unset($data['secondary_roles_submitted']);

        $branchIdsSubmitted = !empty($data['branch_ids_submitted']);
        $branchIds = $data['branch_ids'] ?? (!empty($data['branch_id']) ? [$data['branch_id']] : []);
        $defaultBranchId = $data['default_branch_id'] ?? (!empty($data['branch_id']) ? (int) $data['branch_id'] : null);
        unset($data['branch_ids'], $data['default_branch_id'], $data['branch_ids_submitted']);

        $hasAdminRole = $data['role'] === 'admin'
            || (is_array($data['secondary_roles']) && in_array('admin', $data['secondary_roles'], true));

        if ($hasAdminRole) {
            $data['branch_id'] = null;
        }

        $user = User::create($data);

        if (!$user->isAdmin()) {
            if ($branchIdsSubmitted || !empty($branchIds)) {
                if (empty($branchIds) && ($activeBranchId = Auth::user()->getActiveBranchId())) {
                    $branchIds = [$activeBranchId];
                }
                $user->syncBranches($branchIds, $defaultBranchId);
            }
        }

        return $user;
    }

    /**
     * Memperbarui user.
     */
    public function updateUser(User $user, array $data): User
    {
        if ($user->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }

        $secondaryRolesSubmitted = !empty($data['secondary_roles_submitted']);
        $branchIdsSubmitted = !empty($data['branch_ids_submitted']);
        $branchIds = $data['branch_ids'] ?? [];
        $defaultBranchId = $data['default_branch_id'] ?? (!empty($data['branch_id']) ? (int) $data['branch_id'] : null);
        unset($data['branch_ids'], $data['default_branch_id'], $data['branch_ids_submitted'], $data['secondary_roles_submitted']);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $targetRole = $data['role'] ?? $user->role;

        if ($secondaryRolesSubmitted || array_key_exists('secondary_roles', $data)) {
            $data['secondary_roles'] = $this->sanitizeSecondaryRoles(
                $data['secondary_roles'] ?? [],
                $targetRole
            );
        } elseif (is_array($user->secondary_roles)) {
            $data['secondary_roles'] = $this->sanitizeSecondaryRoles(
                $user->secondary_roles,
                $targetRole
            );
        }

        $effectiveSecondary = array_key_exists('secondary_roles', $data)
            ? $data['secondary_roles']
            : $user->secondary_roles;
        $hasAdminRole = $targetRole === 'admin'
            || (is_array($effectiveSecondary) && in_array('admin', $effectiveSecondary, true));

        if ($hasAdminRole) {
            $data['branch_id'] = null;
        }

        $user->update($data);

        if ($user->isAdmin()) {
            $user->syncBranches([]);
        } elseif ($branchIdsSubmitted) {
            $user->syncBranches($branchIds, $defaultBranchId);
        }

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
                  ->orWhereJsonContains('secondary_roles', 'driver')
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