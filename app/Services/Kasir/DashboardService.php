<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
use App\Models\Customer;
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
            $todayStart = $today->copy()->startOfDay();
            $todayEnd = $today->copy()->endOfDay();
            $todayDate = $today->toDateString();
            $businessId = Auth::user()?->business_id ?? (\App\Models\User::find($kasirId)?->business_id);
            $branchId = app(\App\Support\BranchContext::class)->getBranchId();

            // Modal Awal Laci (Kas Kemarin)
            $openingQuery = CashierOpeningBalance::where('business_id', $businessId)
                ->where('user_id', $kasirId)
                ->where('date', $todayDate);
            if ($branchId) {
                $openingQuery->where('branch_id', $branchId);
            }
            $openingRecord = $openingQuery->first();
            $myOpeningCash = $openingRecord ? (float) $openingRecord->amount : 0.0;
            $myOpeningNotes = $openingRecord ? $openingRecord->notes : '';

            // ==========================================================
            // [SINKRONISASI] 1. Hitung Saldo Kas Fisik Laci (100% SAMA DENGAN ADMIN)
            // ==========================================================
            // A. Penjualan Tunai Meja Langsung Lunas Hari Ini (SSOT 100% dari tabel cash_flow)
            $cashSalesQuery = CashFlow::where('business_id', $businessId)
                ->where('created_by', $kasirId)
                ->where('type', 'income')
                ->where('flow_type', 'sale_direct')
                ->where('affects_drawer', 1)
                ->where('date', $todayDate)
                ->whereRaw('LOWER(payment_method) = ?', ['cash']);

            if ($branchId) {
                $cashSalesQuery->where('branch_id', $branchId);
            }
            $cashSalesAmount = (float) $cashSalesQuery->sum('amount');

            // B. Pelunasan Kasbon & Setoran Driver Tunai yang Diterima Kasir Ini (dari tabel cash_flow)
            $pelunasanCashQuery = CashFlow::where('business_id', $businessId)
                ->where('created_by', $kasirId)
                ->where('type', 'income')
                ->where('payment_method', 'cash')
                ->where('affects_drawer', 1)
                ->where('date', $todayDate)
                ->where(function ($q) {
                    $q->where('flow_type', 'debt_settlement')
                      ->orWhere('description', 'LIKE', '%Pelunasan%');
                });

            if ($branchId) {
                $pelunasanCashQuery->where('branch_id', $branchId);
            }
            $pelunasanCashAmount = (float) $pelunasanCashQuery->sum('amount');

            // B2. Penerimaan Titipan Deposit Pelanggan Tunai di Laci Kasir (Non-P&L)
            $depositCashQuery = CashFlow::where('business_id', $businessId)
                ->where('created_by', $kasirId)
                ->where('type', 'income')
                ->whereRaw('LOWER(payment_method) = ?', ['cash'])
                ->where('affects_drawer', 1)
                ->where('flow_type', 'customer_deposit')
                ->where('date', $todayDate);

            if ($branchId) {
                $depositCashQuery->where('branch_id', $branchId);
            }
            $depositCashAmount = (float) $depositCashQuery->sum('amount');

            $totalCashInToday = $cashSalesAmount + $pelunasanCashAmount + $depositCashAmount;

            // C. Kas Keluar Tunai Fisik dari Laci Kasir (affects_drawer = 1 & cash)
            $drawerCashExpensesQuery = CashFlow::where('business_id', $businessId)
                ->where('created_by', $kasirId)
                ->where('type', 'expense')
                ->where('affects_drawer', 1)
                ->where('payment_method', 'cash')
                ->whereIn('flow_type', ['expense', 'stock_purchase', 'cash_handover', 'cash_refund', 'deposit_refund'])
                ->where('date', $todayDate);

            if ($branchId) {
                $drawerCashExpensesQuery->where('branch_id', $branchId);
            }
            $drawerCashExpensesAmount = (float) $drawerCashExpensesQuery->sum('amount');

            // D. Saldo Kas Fisik di Laci Real-Time (100% SAMA PERSIS dengan Admin)
            $myNetCashToday = $myOpeningCash + $totalCashInToday - $drawerCashExpensesAmount;

            // E. Penerimaan Non-Tunai Hari Ini (Transfer & DANA - Masuk Rekening Owner)
            $allCashFlowsQuery = CashFlow::where('created_by', $kasirId)
                ->whereIn('type', ['income', 'expense'])
                ->where('date', $todayDate);

            if ($branchId) {
                $allCashFlowsQuery->where('branch_id', $branchId);
            }
            $allCashFlowsToday = $allCashFlowsQuery->get();

            $incomesToday = $allCashFlowsToday->where('type', 'income');
            $expensesToday = $allCashFlowsToday->where('type', 'expense');

            $nonCashDana = (float) $incomesToday->where('payment_method', 'dana')->sum('amount');
            $nonCashTransfer = (float) $incomesToday->filter(function ($cf) {
                $pm = strtolower($cf->payment_method ?? '');
                return str_contains($pm, 'transfer') || in_array($pm, ['bca', 'bri', 'mandiri', 'bank']);
            })->sum('amount');
            $nonCashTotal = (float) $incomesToday->where('payment_method', '!=', 'cash')->sum('amount');

            $nonCashSalesAmount = (float) $incomesToday
                ->where('payment_method', '!=', 'cash')
                ->where('flow_type', 'sale_direct')
                ->sum('amount');

            $nonCashDebtCollectionAmount = (float) $incomesToday
                ->where('payment_method', '!=', 'cash')
                ->where('flow_type', 'debt_settlement')
                ->sum('amount');

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
            $lowStockQuery = Inventory::whereColumn('current_stock', '<=', 'min_stock');
            if ($branchId) {
                $lowStockQuery->where('branch_id', $branchId);
            }
            $lowStockItems = $lowStockQuery->count();

            // 2. Hitung jumlah fisik produk es kristal yang keluar hari ini (Kecualikan transaksi void/batal)
            $soldQuery = DB::table('transactions')
                ->join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
                ->join('products', 'transaction_details.product_id', '=', 'products.id')
                ->where('transactions.created_by', $kasirId)
                ->where('transactions.status', '!=', 'cancelled')
                ->whereNull('transactions.voided_at')
                ->whereBetween('transactions.transaction_date', [$todayStart, $todayEnd]);

            app(\App\Support\BranchContext::class)->applyTo($soldQuery, 'transactions', 'branch_id');

            $productsSoldToday = $soldQuery
                ->select('products.name', DB::raw('SUM(transaction_details.quantity) as total_quantity_sold'))
                ->groupBy('products.name')
                ->orderByDesc('total_quantity_sold')
                ->limit(5)
                ->get();

            // 3. [BARU] Ambil Pengiriman Berjalan (Driver Delivery Trips yang sedang aktif - Kecualikan transaksi void/batal)
            $activeDeliveriesQuery = Transaction::with(['customer', 'driver', 'details.product'])
                ->where('business_id', $businessId)
                ->where('type', 'sale')
                ->where('status', '!=', 'cancelled')
                ->whereNull('voided_at')
                ->whereNotIn('order_status', ['cancelled', 'delivered'])
                ->whereNotIn('delivery_status', ['cancelled', 'delivered'])
                ->where(function ($q) {
                    $q->where('order_status', 'delivering')
                      ->orWhere('delivery_status', 'delivering');
                })
                ->orderBy('transaction_date', 'desc');

            if ($branchId) {
                $activeDeliveriesQuery->where('branch_id', $branchId);
            }
            $activeDeliveriesRaw = $activeDeliveriesQuery->get();

            $activeTrips = $activeDeliveriesRaw->groupBy(function ($item) {
                return $item->delivery_trip_code ?: ('TRIP-' . ($item->driver_id ?: $item->claimed_by_driver_id) . '-' . $item->transaction_date->format('ymdHi'));
            });

            // 4. [BARU] Ambil Metode Pembayaran Dinamis untuk Dashboard (Modal Pelunasan)
            $paymentMethods = \App\Models\PaymentMethod::where('business_id', $businessId)
                ->where('is_active', true)
                ->orderBy('name', 'asc')
                ->get();

            // 5. Ambil Kasbon yang Belum Lunas & Grouping Berdasarkan Pelanggan (Kecualikan yang sedang dibawa driver & transaksi void/batal)
            // Kasbon disatukan untuk semua cabang dalam 1 bisnis (lintas cabang) dengan eager load 'branch'
            $pendingKasbonQuery = Transaction::withoutGlobalScope('branch')
                ->with(['customer', 'createdBy', 'driver', 'details.product', 'branch'])
                ->where('business_id', $businessId)
                ->where('type', 'sale')
                ->where('status', '!=', 'cancelled')
                ->whereNull('voided_at')
                ->where('payment_status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('order_status')
                      ->orWhere('order_status', '!=', 'delivering');
                })
                ->where(function ($q) {
                    $q->whereNull('delivery_status')
                      ->orWhere('delivery_status', '!=', 'delivering');
                })
                ->orderBy('transaction_date', 'asc');

            $pendingKasbonRaw = $pendingKasbonQuery->get();

            $kasbonByCustomer = $pendingKasbonRaw->groupBy(function ($item) {
                return $item->customer_id ? 'cust_' . $item->customer_id : 'umum';
            });

            // Daftar Pelanggan untuk Setor Deposit Mandiri
            $customers = Customer::where('business_id', $businessId)
                ->select('id', 'name', 'phone', 'deposit_balance')
                ->orderBy('name')
                ->get();

            return [
                'myOpeningCash'            => $myOpeningCash,
                'myOpeningNotes'           => $myOpeningNotes,
                'totalCashInToday'         => $totalCashInToday,
                'cashSalesAmount'          => $cashSalesAmount,
                'pelunasanCashAmount'      => $pelunasanCashAmount,
                'depositCashAmount'        => $depositCashAmount,
                'mySalesToday'             => $totalCashInToday, // Alias kompatibilitas laci kasir
                'myExpensesToday'          => $drawerCashExpensesAmount,
                'myNetCashToday'           => $myNetCashToday,  // Murni Uang Fisik di Laci Real-Time
                'nonCashDana'                  => $nonCashDana,
                'nonCashTransfer'              => $nonCashTransfer,
                'nonCashTotal'                 => $nonCashTotal,
                'nonCashSalesAmount'           => $nonCashSalesAmount,
                'nonCashDebtCollectionAmount'  => $nonCashDebtCollectionAmount,
                'myTransactionsToday'      => $myTransactionsToday,
                'lowStockItems'            => $lowStockItems,
                'balancesByMethod'         => $balancesByMethod,
                'salesByPaymentMethod'     => $incomesToday->groupBy('payment_method')->map(fn($rows) => (float) $rows->sum('amount')),
                'productsSoldToday'        => $productsSoldToday,
                'activeTrips'              => $activeTrips,
                'paymentMethods'           => $paymentMethods,
                'kasbonByCustomer'         => $kasbonByCustomer,
                'totalKasbonCount'         => $pendingKasbonRaw->count(),
                'totalKasbonAmount'        => $pendingKasbonRaw->sum('total_amount'),
                'customers'                => $customers,
            ];
        } catch (\Exception $e) {
            logger()->error(__METHOD__ . ': ' . $e->getMessage());

            return [
                'myOpeningCash'            => 0,
                'myOpeningNotes'           => '',
                'totalCashInToday'         => 0,
                'cashSalesAmount'          => 0,
                'pelunasanCashAmount'      => 0,
                'depositCashAmount'        => 0,
                'mySalesToday'             => 0,
                'myExpensesToday'          => 0,
                'myNetCashToday'           => 0,
                'nonCashDana'              => 0,
                'nonCashTransfer'          => 0,
                'nonCashTotal'             => 0,
                'myTransactionsToday'      => 0,
                'lowStockItems'            => 0,
                'balancesByMethod'         => collect(),
                'salesByPaymentMethod'     => collect(),
                'productsSoldToday'        => collect(),
                'activeTrips'              => collect(),
                'paymentMethods'           => collect(),
                'kasbonByCustomer'         => collect(),
                'totalKasbonCount'         => 0,
                'totalKasbonAmount'        => 0,
                'customers'                => collect(),
            ];
        }
    }
}
