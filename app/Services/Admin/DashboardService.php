<?php

namespace App\Services\Admin;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Inventory;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\FundAllocationSetting;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\Admin\FinancialService;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    protected FinancialService $financialService;

    /**
     * Injeksi Service FinancialService ke dalam Constructor
     */
    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    /**
     * Mengambil semua data yang diperlukan untuk dashboard admin.
     */
    public function getDashboardData(): array
    {
        $businessId = Auth::user()->business_id;
        
        // Setup Tanggal
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        // 1. Metrik Utama (Penjualan Hari Ini)
        $salesToday = $this->getSalesForDate($businessId, $today);

        // 2. Perhitungan Laba Bersih (Dengan Cache agar TTFB secepat kilat)
        // Bulan Ini (Cache 60 detik)
        $thisMonthFilters = [
            'start_date' => $startOfMonth->toDateString(), 
            'end_date' => $endOfMonth->toDateString()
        ];
        $netProfitThisMonth = Cache::remember("dashboard_profit_this_month_{$businessId}", 60, function () use ($thisMonthFilters) {
            return $this->financialService->getNetProfitOnly($thisMonthFilters);
        });

        // Bulan Lalu (Cache 1 jam karena data bulan lalu statis)
        $lastMonthFilters = [
            'start_date' => $startOfLastMonth->toDateString(), 
            'end_date' => $endOfLastMonth->toDateString()
        ];
        $netProfitLastMonth = Cache::remember("dashboard_profit_last_month_{$businessId}", 3600, function () use ($lastMonthFilters) {
            return $this->financialService->getNetProfitOnly($lastMonthFilters);
        });

        // 3. Perbandingan & Persentase (Penjualan kemarin di-cache 1 jam)
        $salesYesterday = Cache::remember("sales_yesterday_{$businessId}_" . $yesterday->toDateString(), 3600, function () use ($businessId, $yesterday) {
            return $this->getSalesForDate($businessId, $yesterday);
        });
        $salesChangePercentage = $this->calculatePercentageChange($salesToday, $salesYesterday);
        $profitChangePercentage = $this->calculatePercentageChange($netProfitThisMonth, $netProfitLastMonth);

        // 4. Metrik Operasional Harian Pabrik Es
        $totalBallSoldToday = (int) TransactionDetail::whereHas('transaction', function ($q) use ($businessId, $today) {
            $q->where('business_id', $businessId)
              ->where('type', 'sale')
              ->whereDate('transaction_date', $today);
        })->sum('quantity');

        $cashInToday = (float) Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('payment_status', 'paid')
            ->whereDate('transaction_date', $today)
            ->sum('total_amount');

        $pendingToday = (float) Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('payment_status', 'pending')
            ->whereDate('transaction_date', $today)
            ->sum('total_amount');

        $totalReadyStock = (int) Inventory::where('business_id', $businessId)->sum('current_stock');

        $readyStockItems = Inventory::with('product')
            ->where('business_id', $businessId)
            ->where('current_stock', '>', 0)
            ->orderBy('current_stock', 'desc')
            ->get();

        // 5. Metrik Pendukung (Stock Warning, User, Activity, Allocation)
        $lowStockItems = Inventory::where('business_id', $businessId)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->count();

        $totalUsers = User::where('business_id', $businessId)->count();
        $activeUsers = 1; // Placeholder
        
        $recentActivities = UserActivityLog::with('user')
            ->where('business_id', $businessId)
            ->latest()
            ->limit(5)
            ->get();

        $fundAllocationSettings = FundAllocationSetting::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // 6. DATA GRAFIK
        // A. Data Bulanan (6 Bulan)
        $monthlyPerformance = $this->getMonthlyPerformanceData($businessId, 6);
        
        // B. [BARU] Data Mingguan (4 Minggu Terakhir)
        $weeklyPerformance = $this->getWeeklyPerformanceData($businessId);

        // 7. Hitung Rata-rata & Margin (Menggunakan data bulanan sebagai acuan)
        $salesArr = $monthlyPerformance['sales'];
        $profitArr = $monthlyPerformance['profits'];
        
        $count = count($salesArr);
        $avgSales = $count ? round(array_sum($salesArr) / $count, 2) : 0;
        $avgProfit = $count ? round(array_sum($profitArr) / $count, 2) : 0;
        $profitMargin = $avgSales > 0 
            ? round(($avgProfit / $avgSales) * 100, 2) 
            : 0;

        return [
            'salesToday' => $salesToday,
            'salesChangePercentage' => $salesChangePercentage,
            'totalBallSoldToday' => $totalBallSoldToday,
            'cashInToday' => $cashInToday,
            'pendingToday' => $pendingToday,
            'totalReadyStock' => $totalReadyStock,
            'readyStockItems' => $readyStockItems,
            'netProfitThisMonth' => $netProfitThisMonth,
            'profitChangePercentage' => $profitChangePercentage,
            'lowStockItems' => $lowStockItems,
            'activeUsers' => $activeUsers,
            'totalUsers' => $totalUsers,
            'recentActivities' => $recentActivities,
            'fundAllocationData' => $fundAllocationSettings,
            'monthlyPerformanceData' => $monthlyPerformance,
            'weeklyPerformanceData' => $weeklyPerformance, // [BARU] Ditambahkan ke return array
            'avgSales' => $avgSales,
            'avgProfit' => $avgProfit,
            'profitMargin' => $profitMargin,
        ];
    }

    /**
     * Mengambil total penjualan untuk tanggal tertentu.
     */
    private function getSalesForDate(int $businessId, Carbon $date): float
    {
        return Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->whereDate('transaction_date', $date)
            ->sum('total_amount');
    }

    /**
     * Mengambil total penjualan untuk rentang tanggal tertentu.
     */
    private function getSalesForDateRange(int $businessId, Carbon $startDate, Carbon $endDate): float
    {
        return Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('total_amount');
    }

    /**
     * Menghitung persentase perubahan.
     */
    private function calculatePercentageChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Mengambil data performa bulanan untuk chart.
     */
    private function getMonthlyPerformanceData(int $businessId, int $months): array
    {
        return Cache::remember("dashboard_monthly_perf_{$businessId}_{$months}", 600, function () use ($businessId, $months) {
            $labels = [];
            $salesData = [];
            $profitData = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $labels[] = $date->isoFormat('MMMM');
                $startOfMonth = $date->copy()->startOfMonth();
                $endOfMonth = $date->copy()->endOfMonth();

                // Penjualan (Jutaan)
                $sales = $this->getSalesForDateRange($businessId, $startOfMonth, $endOfMonth);
                $salesData[] = round($sales / 1000000, 2);

                // Profit (Jutaan) via FinancialService
                $monthFilters = [
                    'start_date' => $startOfMonth->toDateString(), 
                    'end_date' => $endOfMonth->toDateString()
                ];
                $netProfit = $this->financialService->getNetProfitOnly($monthFilters);
                $profitData[] = round($netProfit / 1000000, 2);
            }

            return [
                'labels' => $labels,
                'sales' => $salesData,
                'profits' => $profitData,
            ];
        });
    }

    /**
     * [BARU] Mengambil data performa mingguan (4 minggu terakhir)
     */
    private function getWeeklyPerformanceData(int $businessId): array
    {
        return Cache::remember("dashboard_weekly_perf_{$businessId}", 600, function () use ($businessId) {
            $labels = [];
            $salesData = [];
            $profitData = [];

            // Loop 4 minggu terakhir (0 = minggu ini, 3 = 3 minggu lalu)
            for ($i = 3; $i >= 0; $i--) {
                $date = Carbon::now()->subWeeks($i);
                
                // Tentukan awal dan akhir minggu
                $startOfWeek = $date->copy()->startOfWeek();
                $endOfWeek = $date->copy()->endOfWeek();

                // Label range tanggal
                $labels[] = $startOfWeek->format('d M') . ' - ' . $endOfWeek->format('d M');

                // Hitung Penjualan Mingguan
                $sales = $this->getSalesForDateRange($businessId, $startOfWeek, $endOfWeek);
                $salesData[] = round($sales / 1000000, 2); // Dalam Jutaan

                // Hitung Profit Mingguan via FinancialService
                $weekFilters = [
                    'start_date' => $startOfWeek->toDateString(), 
                    'end_date' => $endOfWeek->toDateString()
                ];
                $netProfit = $this->financialService->getNetProfitOnly($weekFilters);
                $profitData[] = round($netProfit / 1000000, 2); // Dalam Jutaan
            }

            return [
                'labels' => $labels,
                'sales' => $salesData,
                'profits' => $profitData,
            ];
        });
    }
}