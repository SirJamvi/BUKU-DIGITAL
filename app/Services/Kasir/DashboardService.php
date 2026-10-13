<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\CashFlow;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Mengambil data untuk dashboard kasir.
     */
    public function getDashboardData(int $kasirId): array
    {
        try {
            $today = Carbon::today();
            $businessId = Auth::user()->business_id;

            // ==========================================================
            // [PERBAIKAN] 1. Ambil SEMUA Arus Kas Hari Ini (Income & Expense)
            // ==========================================================
            $allCashFlowsToday = CashFlow::where('created_by', $kasirId)
                ->whereIn('type', ['income', 'expense'])
                ->whereDate('date', $today)
                ->get();

            // Pisahkan Income dan Expense
            $incomesToday = $allCashFlowsToday->where('type', 'income');
            $expensesToday = $allCashFlowsToday->where('type', 'expense');

            // Hitung Total Penjualan Kotor (Income) & Total Pengeluaran (Expense)
            $mySalesToday = $incomesToday->sum('amount');
            $myExpensesToday = $expensesToday->sum('amount');

            // Hitung Uang Bersih di Laci Kasir (Saldo Real-time)
            $myNetCashToday = $mySalesToday - $myExpensesToday;

            // Hitung Jumlah Struk Masuk
            $myTransactionsToday = $incomesToday->count();

            // ==========================================================
            // Hitung SALDO BERSIH per Metode Pembayaran (Income - Expense)
            // ==========================================================
            // Ambil semua metode pembayaran unik yang dipakai hari ini
            $usedMethods = $allCashFlowsToday->pluck('payment_method')->unique();

            $balancesByMethod = collect();
            foreach ($usedMethods as $method) {
                $inc = $incomesToday->where('payment_method', $method)->sum('amount');
                $exp = $expensesToday->where('payment_method', $method)->sum('amount');
                $net = $inc - $exp;

                $balancesByMethod->put($method, [
                    'income'  => $inc,
                    'expense' => $exp,
                    'balance' => $net
                ]);
            }

            // Notifikasi item dengan stok rendah
            $lowStockItems = Inventory::whereColumn('current_stock', '<=', 'min_stock')->count();

            // 2. Hitung jumlah fisik produk es kristal yang keluar hari ini
            $productsSoldToday = DB::table('transactions')
                ->join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
                ->join('products', 'transaction_details.product_id', '=', 'products.id')
                ->where('transactions.created_by', $kasirId)
                ->whereDate('transactions.transaction_date', $today)
                ->select('products.name', DB::raw('SUM(transaction_details.quantity) as total_quantity_sold'))
                ->groupBy('products.name')
                ->orderByDesc('total_quantity_sold')
                ->limit(5)
                ->get();

            // 3. [BARU] Ambil Pengiriman Berjalan (Driver Delivery Trips yang sedang aktif)
            $activeDeliveriesRaw = Transaction::with(['customer', 'driver', 'details.product'])
                ->where('business_id', $businessId)
                ->where('type', 'sale')
                ->where(function ($q) {
                    $q->where('order_status', 'delivering')
                      ->orWhere('delivery_status', 'delivering');
                })
                ->orderBy('transaction_date', 'desc')
                ->get();

            $activeTrips = $activeDeliveriesRaw->groupBy(function ($item) {
                return $item->delivery_trip_code ?: ('TRIP-' . ($item->driver_id ?: $item->claimed_by_driver_id) . '-' . $item->transaction_date->format('ymdHi'));
            });

            // 4. [BARU] Ambil Metode Pembayaran Dinamis untuk Dashboard (Modal Pelunasan)
            $paymentMethods = \App\Models\PaymentMethod::where('business_id', $businessId)
                ->where('is_active', true)
                ->orderBy('name', 'asc')
                ->get();

            // 5. Ambil Kasbon yang Belum Lunas & Grouping Berdasarkan Pelanggan (Kecualikan yang sedang dibawa driver)
            $pendingKasbonRaw = Transaction::with('customer')
                ->where('business_id', $businessId)
                ->where('type', 'sale')
                ->where('payment_status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('order_status')
                      ->orWhere('order_status', '!=', 'delivering');
                })
                ->where(function ($q) {
                    $q->whereNull('delivery_status')
                      ->orWhere('delivery_status', '!=', 'delivering');
                })
                ->orderBy('transaction_date', 'asc')
                ->get();

            $kasbonByCustomer = $pendingKasbonRaw->groupBy(function ($item) {
                return $item->customer_id ? 'cust_' . $item->customer_id : 'umum';
            });

            return [
                'mySalesToday'        => $mySalesToday,
                'myExpensesToday'     => $myExpensesToday, // <-- Variabel baru
                'myNetCashToday'      => $myNetCashToday,  // <-- Variabel baru (Uang real di laci)
                'myTransactionsToday' => $myTransactionsToday,
                'lowStockItems'       => $lowStockItems,
                'balancesByMethod'    => $balancesByMethod, // <-- Gantikan salesByPaymentMethod
                'productsSoldToday'   => $productsSoldToday,
                'activeTrips'         => $activeTrips,      // <-- [BARU] Pengiriman Berjalan
                'paymentMethods'      => $paymentMethods,   // <-- [BARU] Metode Pembayaran Dinamis
                'kasbonByCustomer'    => $kasbonByCustomer,
                'totalKasbonCount'    => $pendingKasbonRaw->count(),
                'totalKasbonAmount'   => $pendingKasbonRaw->sum('total_amount'),
            ];
        } catch (\Exception $e) {
            logger()->error(__METHOD__ . ': ' . $e->getMessage());

            return [
                'mySalesToday'        => 0,
                'myExpensesToday'     => 0,
                'myNetCashToday'      => 0,
                'myTransactionsToday' => 0,
                'lowStockItems'       => 0,
                'balancesByMethod'    => collect(),
                'productsSoldToday'   => collect(),
                'activeTrips'         => collect(),
                'paymentMethods'      => collect(),
                'kasbonByCustomer'    => collect(),
                'totalKasbonCount'    => 0,
                'totalKasbonAmount'   => 0,
            ];
        }
    }
}
