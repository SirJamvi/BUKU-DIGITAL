<?php

namespace App\Services\Admin;

use App\Models\Transaction;
use App\Models\CashFlow;
use App\Models\Inventory;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\FundAllocationSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Mengambil semua data yang diperlukan untuk dashboard admin.
     */
    public function getDashboardData(): array
    {
        $businessId = Auth::user()->business_id;
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // 1. Metrik Penjualan Hari Ini
        $salesToday = Transaction::where('business_id', $businessId)
            ->whereDate('transaction_date', $today)
            ->sum('total_amount');

        // 2. Metrik Laba Bersih Bulan Ini
        $grossProfitThisMonth = DB::table('transactions')
            ->join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->where('transactions.business_id', $businessId)
            ->whereBetween('transactions.transaction_date', [$startOfMonth, $endOfMonth])
            ->sum(DB::raw('transaction_details.quantity * (products.base_price - products.cost_price)'));

        $expensesThisMonth = CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $netProfitThisMonth = $grossProfitThisMonth - $expensesThisMonth;

        // 3. Metrik Inventaris & User
        $lowStockItems = Inventory::where('business_id', $businessId)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->count();

        $totalUsers = User::where('business_id', $businessId)->count();
        $activeUsers = 1; // Placeholder; ganti jika ada tabel session

        // 4. Aktivitas Terbaru
        $recentActivities = UserActivityLog::with('user')
            ->where('business_id', $businessId)
            ->latest()
            ->limit(5)
            ->get();

        // 5. Data Alokasi Dana
        $fundAllocationSettings = FundAllocationSetting::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // 6. Data Performa Bulanan (6 bulan terakhir)
        $monthlyPerformance = $this->getMonthlyPerformanceData($businessId, 6);

        // 7. Hitung Rata-rata & Margin
        $salesArr  = $monthlyPerformance['sales'];   // sudah dalam jutaan
        $profitArr = $monthlyPerformance['profits']; // sudah dalam jutaan

        $count = count($salesArr);
        $avgSales  = $count ? round(array_sum($salesArr)  / $count, 2) : 0;
        $avgProfit = $count ? round(array_sum($profitArr) / $count, 2) : 0;
        $profitMargin = $avgSales > 0 
            ? round(($avgProfit / $avgSales) * 100, 2) 
            : 0;

        return [
            'salesToday'            => $salesToday,
            'netProfitThisMonth'    => $netProfitThisMonth,
            'lowStockItems'         => $lowStockItems,
            'activeUsers'           => $activeUsers,
            'totalUsers'            => $totalUsers,
            'recentActivities'      => $recentActivities,
            'fundAllocationData'    => $fundAllocationSettings,
            'monthlyPerformanceData'=> $monthlyPerformance,
            'avgSales'              => $avgSales,
            'avgProfit'             => $avgProfit,
            'profitMargin'          => $profitMargin,
        ];
    }

    /**
     * Helper untuk mengambil data performa bulanan.
     */
    private function getMonthlyPerformanceData(int $businessId, int $months): array
    {
        $labels = [];
        $salesData = [];
        $profitData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $labels[] = $date->isoFormat('MMMM');
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();

            // Penjualan bulan ini
            $sales = Transaction::where('business_id', $businessId)
                ->whereBetween('transaction_date', [$start, $end])
                ->sum('total_amount');
            $salesData[] = round($sales / 1000000, 2); // juta

            // Laba bersih bulan ini
            $gross = DB::table('transactions')
                ->join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
                ->join('products', 'transaction_details.product_id', '=', 'products.id')
                ->where('transactions.business_id', $businessId)
                ->whereBetween('transactions.transaction_date', [$start, $end])
                ->sum(DB::raw('transaction_details.quantity * (products.base_price - products.cost_price)'));

            $expense = CashFlow::where('business_id', $businessId)
                ->where('type', 'expense')
                ->whereBetween('date', [$start, $end])
                ->sum('amount');

            $profitData[] = round(($gross - $expense) / 1000000, 2); // juta
        }

        return [
            'labels'  => $labels,
            'sales'   => $salesData,
            'profits' => $profitData,
        ];
    }
}
