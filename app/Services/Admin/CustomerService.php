<?php

namespace App\Services\Admin;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
     * Memperbarui data pelanggan yang sudah ada.
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

    /**
     * [BARU] Mengubah status pelanggan secara instan (toggle).
     */
    public function toggleStatus(Customer $customer): bool
    {
        $user = Auth::user();
        
        // Keamanan: Pastikan customer milik business yang sama
        if ($customer->business_id !== $user->business_id) {
            abort(403, 'Akses ditolak.');
        }

        // Jika aktif jadi inactive, jika inactive jadi active
        $newStatus = $customer->status === 'active' ? 'inactive' : 'active';
        
        return $customer->update(['status' => $newStatus]);
    }

    /**
     * [BARU] Mendapatkan statistik pelanggan baru per bulan (12 bulan terakhir).
     */
    public function getMonthlyCustomerStats(): array
    {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();

        $stats = Customer::select(
                DB::raw('YEAR(join_date) as year'),
                DB::raw('MONTH(join_date) as month'),
                DB::raw('COUNT(*) as total')
            )
            ->whereBetween('join_date', [$startDate, $endDate])
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        // Buat array untuk 12 bulan terakhir dengan nilai default 0
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $key = $date->format('Y-m');
            $monthlyData[$key] = [
                'month_name' => $date->isoFormat('MMMM YYYY'),
                'month_short' => $date->isoFormat('MMM'),
                'year' => $date->year,
                'month' => $date->month,
                'total' => 0
            ];
        }

        // Isi data aktual dari database
        foreach ($stats as $stat) {
            $key = sprintf('%d-%02d', $stat->year, $stat->month);
            if (isset($monthlyData[$key])) {
                $monthlyData[$key]['total'] = $stat->total;
            }
        }

        return array_values($monthlyData);
    }

    /**
     * [BARU] Mendapatkan total pelanggan baru bulan ini.
     */
    public function getCurrentMonthNewCustomers(): int
    {
        return Customer::whereYear('join_date', Carbon::now()->year)
            ->whereMonth('join_date', Carbon::now()->month)
            ->count();
    }

    /**
     * [BARU] Mendapatkan persentase perubahan dibanding bulan lalu.
     */
    public function getMonthlyGrowthPercentage(): float
    {
        $currentMonth = $this->getCurrentMonthNewCustomers();
        
        $lastMonth = Customer::whereYear('join_date', Carbon::now()->subMonth()->year)
            ->whereMonth('join_date', Carbon::now()->subMonth()->month)
            ->count();

        if ($lastMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    /**
     * [BARU] Mendapatkan total pelanggan berdasarkan status.
     */
    public function getCustomerStatusCounts(): array
    {
        // Menggunakan selectRaw agar lebih efisien (hanya 1 query)
        $counts = Customer::selectRaw("
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_count
            ")
            ->first();

        return [
            'active' => $counts->active_count ?? 0,
            'inactive' => $counts->inactive_count ?? 0,
        ];
    }
}