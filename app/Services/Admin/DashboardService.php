<?php

namespace App\Services\Admin;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Inventory;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\FundAllocationSetting;
use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\Admin\FinancialService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        // 4. Metrik Operasional Harian Pabrik Es (Abaikan Ongkos Kirim / Ongkir)
        $totalBallSoldToday = (int) TransactionDetail::whereHas('transaction', function ($q) use ($businessId, $today) {
            $q->where('business_id', $businessId)
              ->where('type', 'sale')
              ->whereDate('transaction_date', $today);
        })
        ->whereHas('product', function ($q) {
            $q->where('name', 'NOT LIKE', '%ongkos kirim%')
              ->where('name', 'NOT LIKE', '%ongkir%');
        })
        ->sum('quantity');

        $cashInToday = (float) Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('payment_status', 'paid')
            ->where('status', 'completed')
            ->whereDate('transaction_date', $today)
            ->sum('total_amount');

        $pendingToday = (float) Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('payment_status', 'pending')
            ->where('status', 'completed')
            ->whereDate('transaction_date', $today)
            ->sum('total_amount');

        // Rincian Saluran Pembayaran Hari Ini:
        // A. Penjualan Langsung Lunas di Kasir (Cash, DANA, Transfer)
        $todaySalesCash = (float) Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('payment_status', 'paid')
            ->where('payment_method', 'cash')
            ->where('status', 'completed')
            ->whereDate('transaction_date', $today)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('cash_flow')
                    ->whereColumn('cash_flow.reference_id', 'transactions.id')
                    ->where('cash_flow.description', 'LIKE', '%Pelunasan%');
            })
            ->sum('total_amount');

        $todaySalesDana = (float) Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('payment_status', 'paid')
            ->where('payment_method', 'dana')
            ->where('status', 'completed')
            ->whereDate('transaction_date', $today)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('cash_flow')
                    ->whereColumn('cash_flow.reference_id', 'transactions.id')
                    ->where('cash_flow.description', 'LIKE', '%Pelunasan%');
            })
            ->sum('total_amount');

        $todaySalesTransfer = (float) Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('payment_status', 'paid')
            ->whereIn('payment_method', ['transfer', 'transfer-bank', 'transfer_bank', 'bank_transfer'])
            ->where('status', 'completed')
            ->whereDate('transaction_date', $today)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('cash_flow')
                    ->whereColumn('cash_flow.reference_id', 'transactions.id')
                    ->where('cash_flow.description', 'LIKE', '%Pelunasan%');
            })
            ->sum('total_amount');

        // B. Pelunasan Kasbon & Pengiriman yang Cair Hari Ini
        $pelunasanCashToday = (float) CashFlow::where('business_id', $businessId)
            ->where('type', 'income')
            ->where('payment_method', 'cash')
            ->whereDate('date', $today)
            ->where('description', 'LIKE', '%Pelunasan%')
            ->sum('amount');

        $pelunasanDanaToday = (float) CashFlow::where('business_id', $businessId)
            ->where('type', 'income')
            ->where('payment_method', 'dana')
            ->whereDate('date', $today)
            ->where('description', 'LIKE', '%Pelunasan%')
            ->sum('amount');

        $pelunasanTransferToday = (float) CashFlow::where('business_id', $businessId)
            ->where('type', 'income')
            ->whereIn('payment_method', ['transfer', 'transfer-bank', 'transfer_bank', 'bank_transfer'])
            ->whereDate('date', $today)
            ->where('description', 'LIKE', '%Pelunasan%')
            ->sum('amount');

        // Total per saluran masuk uang hari ini
        $totalInflowCash = $todaySalesCash + $pelunasanCashToday;
        $totalInflowDana = $todaySalesDana + $pelunasanDanaToday;
        $totalInflowTransfer = $todaySalesTransfer + $pelunasanTransferToday;
        $totalAllInflowToday = $totalInflowCash + $totalInflowDana + $totalInflowTransfer;

        // Info Pelunasan Kasbon Lalu Cair Hari Ini
        $pelunasanCairTotal = $pelunasanCashToday + $pelunasanDanaToday + $pelunasanTransferToday;
        $pelunasanCairCash = $pelunasanCashToday;
        $pelunasanCairNonCash = $pelunasanDanaToday + $pelunasanTransferToday;

        // Sisa Stok Cold Storage Hanya untuk Produk Fisik Es Kristal (Abaikan Ongkos Kirim)
        $totalReadyStock = (int) Inventory::where('business_id', $businessId)
            ->whereHas('product', function ($q) {
                $q->where('name', 'NOT LIKE', '%ongkos kirim%')
                  ->where('name', 'NOT LIKE', '%ongkir%');
            })
            ->sum('current_stock');

        $readyStockItems = Inventory::with('product')
            ->where('business_id', $businessId)
            ->whereHas('product', function ($q) {
                $q->where('name', 'NOT LIKE', '%ongkos kirim%')
                  ->where('name', 'NOT LIKE', '%ongkir%');
            })
            ->where('current_stock', '>', 0)
            ->orderBy('current_stock', 'desc')
            ->get();

        // 5. Metrik Pendukung (Stock Warning, User, Activity, Allocation)
        $lowStockItems = Inventory::where('business_id', $businessId)
            ->whereHas('product', function ($q) {
                $q->where('name', 'NOT LIKE', '%ongkos kirim%')
                  ->where('name', 'NOT LIKE', '%ongkir%');
            })
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
            'totalInflowCash' => $totalInflowCash,
            'totalInflowDana' => $totalInflowDana,
            'totalInflowTransfer' => $totalInflowTransfer,
            'totalAllInflowToday' => $totalAllInflowToday,
            'pelunasanCairTotal' => $pelunasanCairTotal,
            'pelunasanCairCash' => $pelunasanCairCash,
            'pelunasanCairNonCash' => $pelunasanCairNonCash,
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

    /**
     * Menghitung Dual-Metric Kasir (Saldo Fisik Laci & Estimasi Laba Bersih Shift).
     * 1. Saldo Fisik Laci: [Kas Awal] + [Kas Masuk] + [Pelunasan] - [Semua Kas Keluar Laci]
     * 2. Estimasi Laba Shift: [Omset Penjualan] - [COGS Snapshot] - [Beban Ops Murni (is_cogs = 0)]
     */
    public function getCashierCashOnHandData(int $businessId, Carbon $filterDate): array
    {
        // Ambil HANYA user role 'kasir' yang aktif atau memiliki aktivitas transaksi / kas masuk-keluar di tanggal ini
        $users = User::where('business_id', $businessId)
            ->where('role', 'kasir')
            ->where(function ($q) use ($filterDate) {
                $q->where('is_active', true)
                  ->orWhereHas('transactions', function ($tq) use ($filterDate) {
                      $tq->whereDate('transaction_date', $filterDate);
                  })
                  ->orWhereIn('id', function ($cq) use ($filterDate) {
                      $cq->select('created_by')->from('cash_flow')->whereDate('date', $filterDate);
                  });
            })
            ->orderBy('name', 'asc')
            ->get();

        $cashierData = [];

        foreach ($users as $user) {
            // Validasi ketat: Abaikan akun non-kasir
            if ($user->role !== 'kasir') {
                continue;
            }

            // 1. Transaksi Penjualan Tunai Lunas Langsung di Meja Kasir (Masuk ke Laci)
            // HANYA transaksi tunai yang dibuat langsung lunas di POS oleh kasir ini
            // Mengecualikan transaksi yang dilunasi lewat proses pelunasan kasbon/pengiriman driver agar:
            // - Mencegah double-counting
            // - Mencegah uang pelunasan shift sore (Kasir B) salah masuk ke laci pembuat nota shift pagi (Kasir A)
            $cashTransactions = Transaction::with(['details.product'])
                ->where('business_id', $businessId)
                ->where('created_by', $user->id)
                ->where('type', 'sale')
                ->where('payment_status', 'paid')
                ->where('payment_method', 'cash')
                ->where('status', 'completed')
                ->whereDate('transaction_date', $filterDate)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('cash_flow')
                        ->whereColumn('cash_flow.reference_id', 'transactions.id')
                        ->where('cash_flow.description', 'LIKE', '%Pelunasan%');
                })
                ->get();

            $cashSalesAmount = (float) $cashTransactions->sum('total_amount');
            $cashOrdersCount = $cashTransactions->count();

            // 2. Pelunasan Kasbon & Setoran Driver Tunai yang Diterima Kasir Ini (dari tabel cash_flow)
            // Uang kas fisik masuk 100% ke kasir yang menerima uang & menekan tombol pelunasan/selesaikan pengiriman
            // (hanya yang memotong/menambah laci kasir: affects_drawer = 1 & cash)
            $pelunasanCashAmount = (float) CashFlow::where('cash_flow.business_id', $businessId)
                ->where('cash_flow.created_by', $user->id)
                ->where('cash_flow.type', 'income')
                ->where('cash_flow.payment_method', 'cash')
                ->where(function ($q) {
                    $q->where('cash_flow.affects_drawer', 1)
                      ->orWhereNull('cash_flow.affects_drawer');
                })
                ->whereDate('cash_flow.date', $filterDate)
                ->where('cash_flow.description', 'LIKE', '%Pelunasan%')
                ->sum('amount');

            $totalCashIn = $cashSalesAmount + $pelunasanCashAmount;

            // 3. Seluruh Penjualan Hari Ini (Tunai, Non-Tunai & Kasbon) untuk Perhitungan Omset & COGS
            $allSalesToday = Transaction::with(['details.product'])
                ->where('business_id', $businessId)
                ->where('created_by', $user->id)
                ->where('type', 'sale')
                ->where('status', 'completed')
                ->whereDate('transaction_date', $filterDate)
                ->get();

            $totalSalesRevenue = (float) $allSalesToday->sum('total_amount');

            // 4. Hitung COGS / HPP (Modal Es Kristal) Berdasarkan Snapshot cost_price Detail Transaksi
            $cogsAmount = 0;
            $itemsSoldBreakdown = [];

            foreach ($allSalesToday as $trx) {
                foreach ($trx->details as $detail) {
                    $pName = optional($detail->product)->name ?? 'Produk';
                    // Abaikan ongkos kirim dari hitungan COGS
                    $isOngkir = str_contains(strtolower($pName), 'ongkos kirim') || str_contains(strtolower($pName), 'ongkir');
                    if ($isOngkir) {
                        continue;
                    }

                    $qty = (float) $detail->quantity;
                    // Prioritaskan snapshot HPP dari detail transaksi, fallback ke master Product
                    $costPrice = (float) ($detail->cost_price ?? optional($detail->product)->cost_price ?? 0);
                    $subCogs = $costPrice * $qty;
                    $cogsAmount += $subCogs;

                    if (!isset($itemsSoldBreakdown[$pName])) {
                        $itemsSoldBreakdown[$pName] = [
                            'name' => $pName,
                            'qty' => 0,
                            'unit' => optional($detail->product)->unit ?? 'Ball',
                            'cost_price' => $costPrice,
                            'total_cogs' => 0,
                        ];
                    }
                    $itemsSoldBreakdown[$pName]['qty'] += $qty;
                    $itemsSoldBreakdown[$pName]['total_cogs'] += $subCogs;
                }
            }

            // 5. METRIK 1: Saldo Fisik Laci ($physicalCashDrawer / Cash on Hand)
            // Modal awal laci kasir (kas kemarin)
            $openingRecord = CashierOpeningBalance::where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->whereDate('date', $filterDate)
                ->first();
            $openingCash = $openingRecord ? (float) $openingRecord->amount : 0.0;

            // Kas keluar fisik dari laci kasir (hanya yang memotong laci kasir: affects_drawer = 1 & cash)
            $drawerCashExpensesQuery = CashFlow::with('category')
                ->where('business_id', $businessId)
                ->where('created_by', $user->id)
                ->where('type', 'expense')
                ->where(function ($q) {
                    $q->where('affects_drawer', 1)
                      ->orWhereNull('affects_drawer');
                })
                ->where(function ($q) {
                    $q->where('payment_method', 'cash')
                      ->orWhereNull('payment_method');
                })
                ->whereDate('date', $filterDate);

            $allCashExpensesAmount = (float) $drawerCashExpensesQuery->sum('amount');

            // Ambil SEMUA daftar pengeluaran kasir ini untuk modal rincian (baik yang potong laci maupun uang bos)
            $expensesList = CashFlow::with('category')
                ->where('business_id', $businessId)
                ->where('created_by', $user->id)
                ->where('type', 'expense')
                ->whereDate('date', $filterDate)
                ->get();

            $physicalCashDrawer = $openingCash + $cashSalesAmount + $pelunasanCashAmount - $allCashExpensesAmount;

            // 6. METRIK 2: Estimasi Laba Bersih Shift ($shiftNetProfit)
            // Hanya memperhitungkan beban operasional murni (is_cogs = 0) agar tidak double-counting dengan COGS
            $operationalExpensesAmount = (float) CashFlow::where('business_id', $businessId)
                ->where('created_by', $user->id)
                ->where('type', 'expense')
                ->whereHas('category', function ($q) {
                    $q->where('is_cogs', 0);
                })
                ->whereDate('date', $filterDate)
                ->sum('amount');

            // Laba Bersih = Omset Penjualan - COGS - Beban Operasional Murni
            $shiftNetProfit = $totalSalesRevenue - $cogsAmount - $operationalExpensesAmount;

            // 7. Transaksi Non-Tunai Langsung Hari Ini (DANA & Transfer Bank)
            $nonCashDana = (float) Transaction::where('business_id', $businessId)
                ->where('created_by', $user->id)
                ->where('type', 'sale')
                ->where('payment_status', 'paid')
                ->where('payment_method', 'dana')
                ->where('status', 'completed')
                ->whereDate('transaction_date', $filterDate)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('cash_flow')
                        ->whereColumn('cash_flow.reference_id', 'transactions.id')
                        ->where('cash_flow.description', 'LIKE', '%Pelunasan%');
                })
                ->sum('total_amount');

            $nonCashTransfer = (float) Transaction::where('business_id', $businessId)
                ->where('created_by', $user->id)
                ->where('type', 'sale')
                ->where('payment_status', 'paid')
                ->whereIn('payment_method', ['transfer', 'transfer-bank', 'transfer_bank', 'bank_transfer'])
                ->where('status', 'completed')
                ->whereDate('transaction_date', $filterDate)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('cash_flow')
                        ->whereColumn('cash_flow.reference_id', 'transactions.id')
                        ->where('cash_flow.description', 'LIKE', '%Pelunasan%');
                })
                ->sum('total_amount');

            $nonCashSalesAmount = $nonCashDana + $nonCashTransfer;

            // 8. Pelunasan Kasbon via Non-Tunai yang Diproses Kasir Ini (Tidak Masuk Laci Fisik)
            $pelunasanDanaAmount = (float) CashFlow::where('cash_flow.business_id', $businessId)
                ->where('cash_flow.created_by', $user->id)
                ->where('cash_flow.type', 'income')
                ->where('cash_flow.payment_method', 'dana')
                ->whereDate('cash_flow.date', $filterDate)
                ->where('cash_flow.description', 'LIKE', '%Pelunasan%')
                ->sum('amount');

            $pelunasanTransferAmount = (float) CashFlow::where('cash_flow.business_id', $businessId)
                ->where('cash_flow.created_by', $user->id)
                ->where('cash_flow.type', 'income')
                ->whereIn('cash_flow.payment_method', ['transfer', 'transfer-bank', 'transfer_bank', 'bank_transfer'])
                ->whereDate('cash_flow.date', $filterDate)
                ->where('cash_flow.description', 'LIKE', '%Pelunasan%')
                ->sum('amount');

            $pelunasanNonCashTotal = $pelunasanDanaAmount + $pelunasanTransferAmount;

            // 9. Kasbon Baru Hari Ini
            $kasbonAmount = (float) Transaction::where('business_id', $businessId)
                ->where('created_by', $user->id)
                ->where('type', 'sale')
                ->where('payment_status', 'pending')
                ->where('status', 'completed')
                ->whereDate('transaction_date', $filterDate)
                ->sum('total_amount');

            $hasActivity = ($openingCash > 0 || $totalCashIn > 0 || $allCashExpensesAmount > 0 || $cogsAmount > 0 || $nonCashDana > 0 || $nonCashTransfer > 0 || $pelunasanNonCashTotal > 0 || $kasbonAmount > 0 || $cashOrdersCount > 0 || $expensesList->isNotEmpty());

            // Jika kasir tidak aktif dan sama sekali tidak ada aktivitas di tanggal filter, jangan tampilkan
            if (!$user->is_active && !$hasActivity) {
                continue;
            }

            $cashierData[] = [
                'user_id'                  => $user->id,
                'name'                     => $user->name,
                'role'                     => $user->role,
                'phone'                    => $user->phone ?? '-',
                'total_cash_in'            => $totalCashIn,
                'cash_sales'               => $cashSalesAmount,
                'pelunasan_cash'           => $pelunasanCashAmount,
                'cash_orders_count'        => $cashOrdersCount,
                'sales_revenue'            => $totalSalesRevenue,
                'cogs'                     => $cogsAmount,
                'items_sold'               => array_values($itemsSoldBreakdown),
                'expenses'                 => $allCashExpensesAmount,
                'expenses_list'            => $expensesList,
                'operational_expenses'     => $operationalExpensesAmount,
                'opening_cash'             => $openingCash,
                'physical_drawer'          => $physicalCashDrawer,
                'shift_net_profit'         => $shiftNetProfit,
                'real_cash_on_hand'        => $physicalCashDrawer, // Alias untuk kompatibilitas
                'non_cash_sales'           => $nonCashSalesAmount,
                'non_cash_dana'            => $nonCashDana,
                'non_cash_transfer'        => $nonCashTransfer,
                'pelunasan_non_cash_total' => $pelunasanNonCashTotal,
                'pelunasan_dana'           => $pelunasanDanaAmount,
                'pelunasan_transfer'       => $pelunasanTransferAmount,
                'kasbon'                   => $kasbonAmount,
                'has_activity'             => $hasActivity,
            ];
        }

        // Urutkan: Kasir Aktif di paling depan/kiri atas,
        // disusul Saldo Fisik Laci terbesar, lalu kasir Standby di urutan belakang.
        usort($cashierData, function ($a, $b) {
            if ($a['has_activity'] !== $b['has_activity']) {
                return $b['has_activity'] <=> $a['has_activity'];
            }
            if ($a['has_activity']) {
                return $b['physical_drawer'] <=> $a['physical_drawer'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $cashierData;
    }

    /**
     * Mengambil log aktivitas pelunasan pending payment / kasbon / pengiriman pada tanggal filter berjalan.
     * Berdasarkan waktu klik / created_at pada cash_flow.
     */
    public function getSettlementAuditLogs(int $businessId, Carbon $filterDate): \Illuminate\Support\Collection
    {
        return CashFlow::with(['createdBy', 'transaction.customer', 'transaction.driver'])
            ->where('business_id', $businessId)
            ->where('type', 'income')
            ->where('description', 'LIKE', '%Pelunasan%')
            ->whereDate('created_at', $filterDate)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}