<?php

namespace App\Services\Admin;

use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\FundAllocationSetting;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
// 1. IMPOR SERVICE YANG SUDAH MEMILIKI LOGIKA BENAR
use App\Services\Admin\FinancialService;

class DashboardService
{
    /**
     * 2. TAMBAHKAN SERVICE BARU SEBAGAI PROPERTI
     */
    protected FinancialService $financialService;

    /**
     * 3. INJEKSI SERVICE KE DALAM CONSTRUCTOR
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
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        // --- Metrik Utama ---
        $salesToday = $this->getSalesForDate($businessId, $today);

        // ====================================================================
        // 4. PERBAIKAN PERHITUNGAN LABA BERSIH
        // ====================================================================
        
        // Menggunakan FinancialService untuk menghitung Laba Bersih Bulan Ini
        $thisMonthFilters = [
            'start_date' => $startOfMonth->toDateString(), 
            'end_date' => $endOfMonth->toDateString()
        ];
        $thisMonthReport = $this->financialService->getFinancialReport($thisMonthFilters);
        $netProfitThisMonth = $thisMonthReport['net_profit'];

        // Menggunakan FinancialService untuk menghitung Laba Bersih Bulan Lalu
        $lastMonthFilters = [
            'start_date' => $startOfLastMonth->toDateString(), 
            'end_date' => $endOfLastMonth->toDateString()
        ];
        $lastMonthReport = $this->financialService->getFinancialReport($lastMonthFilters);
        $netProfitLastMonth = $lastMonthReport['net_profit'];
        
        // ====================================================================

        // --- Perbandingan ---
        $salesYesterday = $this->getSalesForDate($businessId, $yesterday);
        $salesChangePercentage = $this->calculatePercentageChange($salesToday, $salesYesterday);

        // Perhitungan persentase profit sekarang sudah menggunakan data yang benar
        $profitChangePercentage = $this->calculatePercentageChange($netProfitThisMonth, $netProfitLastMonth);

        // --- Metrik Lainnya ---
        $lowStockItems = Inventory::where('business_id', $businessId)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->count();

        $totalUsers = User::where('business_id', $businessId)->count();
        $activeUsers = 1; // Placeholder; ganti jika ada tabel session

        // --- Aktivitas Terbaru ---
        $recentActivities = UserActivityLog::with('user')
            ->where('business_id', $businessId)
            ->latest()
            ->limit(5)
            ->get();

        // --- Data Alokasi Dana ---
        $fundAllocationSettings = FundAllocationSetting::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // --- Data Performa Bulanan (6 bulan terakhir) ---
        $monthlyPerformance = $this->getMonthlyPerformanceData($businessId, 6);

        // --- Hitung Rata-rata & Margin ---
        $salesArr = $monthlyPerformance['sales'];   // sudah dalam jutaan
        $profitArr = $monthlyPerformance['profits']; // sudah dalam jutaan

        $count = count($salesArr);
        $avgSales = $count ? round(array_sum($salesArr) / $count, 2) : 0;
        $avgProfit = $count ? round(array_sum($profitArr) / $count, 2) : 0;
        $profitMargin = $avgSales > 0 
            ? round(($avgProfit / $avgSales) * 100, 2) 
            : 0;

        return [
            'salesToday' => $salesToday,
            'salesChangePercentage' => $salesChangePercentage,
            'netProfitThisMonth' => $netProfitThisMonth,
            'profitChangePercentage' => $profitChangePercentage,
            'lowStockItems' => $lowStockItems,
            'activeUsers' => $activeUsers,
            'totalUsers' => $totalUsers,
            'recentActivities' => $recentActivities,
            'fundAllocationData' => $fundAllocationSettings,
            'monthlyPerformanceData' => $monthlyPerformance,
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
     * 5. HAPUS METODE LAMA INI KARENA SALAH DAN TIDAK DIGUNAKAN LAGI
     * private function getNetProfitForPeriod(...) { ... }
     */

    /**
     * Menghitung persentase perubahan antara nilai sekarang dan sebelumnya.
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
        $labels = [];
        $salesData = [];
        $profitData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $labels[] = $date->isoFormat('MMMM');
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            // Penjualan bulan ini (dalam jutaan)
            $sales = $this->getSalesForDateRange($businessId, $startOfMonth, $endOfMonth);
            $salesData[] = round($sales / 1000000, 2);

            // ====================================================================
            // 6. PERBAIKAN PERHITUNGAN LABA BERSIH BULANAN
            // ====================================================================
            $monthFilters = [
                'start_date' => $startOfMonth->toDateString(), 
                'end_date' => $endOfMonth->toDateString()
            ];
            $monthReport = $this->financialService->getFinancialReport($monthFilters);
            $netProfit = $monthReport['net_profit'];
            $profitData[] = round($netProfit / 1000000, 2);
            // ====================================================================
        }

        return [
            'labels' => $labels,
            'sales' => $salesData,
            'profits' => $profitData,
        ];
    }
}