<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use App\Models\Inventory;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Mengambil data untuk dashboard kasir.
     * Sesuai SOP, kasir melihat ringkasan penjualan harian mereka dan notifikasi stok.
     *
     * @param int $kasirId
     * @return array
     */
    public function getDashboardData(int $kasirId): array
    {
        try {
            $today = Carbon::today();

            // Total penjualan oleh kasir hari ini
            $mySalesToday = Transaction::where('created_by', $kasirId)
                ->where('type', 'sale')
                ->whereDate('transaction_date', $today)
                ->sum('total_amount');

            // Jumlah transaksi oleh kasir hari ini
            $myTransactionsToday = Transaction::where('created_by', $kasirId)
                ->where('type', 'sale')
                ->whereDate('transaction_date', $today)
                ->count();
            
            // Notifikasi item dengan stok rendah (view-only untuk kasir)
            $lowStockItems = Inventory::whereColumn('current_stock', '<=', 'min_stock')->count();

            return [
                'mySalesToday' => $mySalesToday,
                'myTransactionsToday' => $myTransactionsToday,
                'lowStockItems' => $lowStockItems,
            ];
        } catch (\Exception $e) {
            logger()->error(__METHOD__ . ': ' . $e->getMessage());
            // Mengembalikan nilai default jika terjadi error
            return [
                'mySalesToday' => 0,
                'myTransactionsToday' => 0,
                'lowStockItems' => 0,
            ];
        }
    }
}