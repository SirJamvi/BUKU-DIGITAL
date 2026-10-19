<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
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
            $today = Carbon::today('Asia/Jakarta');
            $businessId = Auth::user()->business_id;

            // Modal Awal Laci (Kas Kemarin)
            $openingRecord = CashierOpeningBalance::where('business_id', $businessId)
                ->where('user_id', $kasirId)
                ->whereDate('date', $today)
                ->first();
            $myOpeningCash = $openingRecord ? (float) $openingRecord->amount : 0.0;
            $myOpeningNotes = $openingRecord ? $openingRecord->notes : '';

            // ==========================================================
            // [SINKRONISASI] 1. Hitung Saldo Kas Fisik Laci (100% SAMA DENGAN ADMIN)
            // ==========================================================
            // A. Penjualan Tunai Meja Langsung Lunas Hari Ini (dikecualikan transaksi yang ber-pelunasan)
            $cashTransactions = Transaction::where('business_id', $businessId)
                ->where('created_by', $kasirId)
                ->where('type', 'sale')
                ->where('payment_status', 'paid')
                ->where('payment_method', 'cash')
                ->whereDate('transaction_date', $today)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('cash_flow')
                        ->whereColumn('cash_flow.reference_id', 'transactions.id')
                        ->where('cash_flow.description', 'LIKE', '%Pelunasan%');
                })
                ->get();

            $cashSalesAmount = (float) $cashTransactions->sum('total_amount');

            // B. Pelunasan Kasbon & Setoran Driver Tunai yang Diterima Kasir Ini (dari tabel cash_flow)
            $pelunasanCashAmount = (float) CashFlow::where('business_id', $businessId)
                ->where('created_by', $kasirId)
                ->where('type', 'income')
                ->where('payment_method', 'cash')
                ->whereDate('date', $today)
                ->where('description', 'LIKE', '%Pelunasan%')
                ->sum('amount');

            $totalCashInToday = $cashSalesAmount + $pelunasanCashAmount;

            // C. Kas Keluar Tunai Fisik dari Laci Kasir (affects_drawer = 1 & cash)
            $drawerCashExpensesAmount = (float) CashFlow::where('business_id', $businessId)
                ->where('created_by', $kasirId)
                ->where('type', 'expense')
                ->where(function ($q) {
                    $q->where('affects_drawer', 1)
                      ->orWhereNull('affects_drawer');
                })
                ->where(function ($q) {
                    $q->where('payment_method', 'cash')
                      ->orWhereNull('payment_method');
                })
                ->whereDate('date', $today)
                ->sum('amount');

            // D. Saldo Kas Fisik di Laci Real-Time (100% SAMA PERSIS dengan Admin)
            $myNetCashToday = $myOpeningCash + $totalCashInToday - $drawerCashExpensesAmount;

            // E. Penerimaan Non-Tunai Hari Ini (Transfer & DANA - Masuk Rekening Owner)
            $allCashFlowsToday = CashFlow::where('created_by', $kasirId)
                ->whereIn('type', ['income', 'expense'])
                ->whereDate('date', $today)
                ->get();

            $incomesToday = $allCashFlowsToday->where('type', 'income');
            $expensesToday = $allCashFlowsToday->where('type', 'expense');

            $nonCashDana = (float) $incomesToday->where('payment_method', 'dana')->sum('amount');
            $nonCashTransfer = (float) $incomesToday->filter(function ($cf) {
                $pm = strtolower($cf->payment_method ?? '');
                return str_contains($pm, 'transfer') || in_array($pm, ['bca', 'bri', 'mandiri', 'bank']);
            })->sum('amount');
            $nonCashTotal = (float) $incomesToday->where('payment_method', '!=', 'cash')->sum('amount');

            // Hitung Jumlah Struk Masuk
            $myTransactionsToday = $incomesToday->count();

            // Saldo per metode pembayaran untuk detail
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
            $pendingKasbonRaw = Transaction::with(['customer', 'createdBy', 'driver', 'details.product'])
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
                'myOpeningCash'            => $myOpeningCash,
                'myOpeningNotes'           => $myOpeningNotes,
                'totalCashInToday'         => $totalCashInToday,
                'cashSalesAmount'          => $cashSalesAmount,
                'pelunasanCashAmount'      => $pelunasanCashAmount,
                'mySalesToday'             => $totalCashInToday, // Alias kompatibilitas laci kasir
                'myExpensesToday'          => $drawerCashExpensesAmount,
                'myNetCashToday'           => $myNetCashToday,  // Murni Uang Fisik di Laci Real-Time
                'nonCashDana'              => $nonCashDana,
                'nonCashTransfer'          => $nonCashTransfer,
                'nonCashTotal'             => $nonCashTotal,
                'myTransactionsToday'      => $myTransactionsToday,
                'lowStockItems'            => $lowStockItems,
                'balancesByMethod'         => $balancesByMethod,
                'productsSoldToday'        => $productsSoldToday,
                'activeTrips'              => $activeTrips,
                'paymentMethods'           => $paymentMethods,
                'kasbonByCustomer'         => $kasbonByCustomer,
                'totalKasbonCount'         => $pendingKasbonRaw->count(),
                'totalKasbonAmount'        => $pendingKasbonRaw->sum('total_amount'),
            ];
        } catch (\Exception $e) {
            logger()->error(__METHOD__ . ': ' . $e->getMessage());

            return [
                'myOpeningCash'            => 0,
                'myOpeningNotes'           => '',
                'totalCashInToday'         => 0,
                'cashSalesAmount'          => 0,
                'pelunasanCashAmount'      => 0,
                'mySalesToday'             => 0,
                'myExpensesToday'          => 0,
                'myNetCashToday'           => 0,
                'nonCashDana'              => 0,
                'nonCashTransfer'          => 0,
                'nonCashTotal'             => 0,
                'myTransactionsToday'      => 0,
                'lowStockItems'            => 0,
                'balancesByMethod'         => collect(),
                'productsSoldToday'        => collect(),
                'activeTrips'              => collect(),
                'paymentMethods'           => collect(),
                'kasbonByCustomer'         => collect(),
                'totalKasbonCount'         => 0,
                'totalKasbonAmount'        => 0,
            ];
        }
    }
}
