<?php

namespace App\Services\Admin;

use App\Models\UserSession;
use App\Models\Inventory;
use App\Models\OwnerProfit;
use App\Models\Transaction;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Mengambil data agregat untuk ditampilkan di dashboard admin.
     *
     * @return array
     */
    public function getDashboardData(): array
    {
        try {
            $today = Carbon::today();

            $salesToday = Transaction::where('type', 'sale')
                ->whereDate('transaction_date', $today)
                ->sum('total_amount');

            $netProfitToday = OwnerProfit::where('period_month', $today->month)
                ->where('period_year', $today->year)
                ->value('net_profit') ?? 0;

            $lowStockItems = Inventory::whereColumn('current_stock', '<=', 'min_stock')
                ->count();

            $activeUsers = UserSession::whereNull('logout_time')
                ->where('last_activity', '>=', Carbon::now()->subMinutes(15))
                ->distinct('user_id')
                ->count('user_id');

            $recentActivities = UserActivityLog::latest()->take(5)->get();

            return [
                'salesToday' => $salesToday,
                'netProfitToday' => $netProfitToday,
                'lowStockItems' => $lowStockItems,
                'activeUsers' => $activeUsers,
                'recentActivities' => $recentActivities,
            ];

        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage());

            return [
                'salesToday' => 0,
                'netProfitToday' => 0,
                'lowStockItems' => 0,
                'activeUsers' => 0,
                'recentActivities' => collect(),
            ];
        }
    }
}
