<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\CashFlow;
use App\Models\StockMovement;
use App\Services\Admin\FinancialService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsappReportController extends Controller
{
    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    public function generateDailyReport(Request $request): JsonResponse
    {
        // 1. Pengaman API Sederhana (Ganti 'rahasia123' dengan token Anda sendiri)
        if ($request->query('token') !== 'rahasia123') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 2. Simulasi Login agar Auth::user()->business_id berfungsi untuk Service
        $admin = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->first() ?? User::first();

        Auth::login($admin);
        $businessId = $admin->business_id;
        $today = Carbon::today();

        // ==============================================================
        // A. DATA ADMIN
        // ==============================================================
        // 1. Penjualan Hari Ini
        $salesToday = Transaction::where('business_id', $businessId)
            ->whereDate('transaction_date', $today)
            ->where('type', 'sale')
            ->sum('total_amount');

        // 2. Laba Bersih Bulan Ini (Menggunakan Service yang sudah ada)
        $monthFilters = [
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString()
        ];
        $financialReport = $this->financialService->getFinancialReport($monthFilters);
        $netProfitThisMonth = $financialReport['net_profit'];

        // 3. Opname Stok Aman (Hari Ini)
        $safeStockCount = Inventory::where('business_id', $businessId)
            ->whereColumn('current_stock', '>', 'min_stock')
            ->count();

        // 4. Riwayat Pecah Ball Hari Ini
        $breakUnitOuts = StockMovement::with('product')
            ->whereDate('created_at', $today)
            ->where('notes', 'Pecah Ball (Bahan Baku)')
            ->get();

        $pecahBallText = "";
        if ($breakUnitOuts->count() > 0) {
            foreach ($breakUnitOuts as $out) {
                $pecahBallText .= "\n- " . ($out->product->name ?? 'Produk') . " (" . $out->quantity . " ball)";
            }
        } else {
            $pecahBallText = "\n- Tidak ada aktivitas pecah ball hari ini.";
        }

        // ==============================================================
        // B. DATA KASIR (GLOBAL UNTUK HARI INI)
        // ==============================================================
        // 1. Kasbon / Unpaid Hari Ini
        $unpaidTransactions = Transaction::where('business_id', $businessId)
            ->whereDate('transaction_date', $today)
            ->where('type', 'sale')
            ->where('payment_status', 'pending');
        $unpaidAmount = $unpaidTransactions->sum('total_amount');
        $unpaidCount = $unpaidTransactions->count();

        // 2. Transaksi Lunas
        $paidTransactionsCount = Transaction::where('business_id', $businessId)
            ->whereDate('transaction_date', $today)
            ->where('type', 'sale')
            ->where('payment_status', 'paid')
            ->count();

        // 3. Saldo Kas Aktual & Rincian per Metode
        $cashFlowsToday = CashFlow::where('business_id', $businessId)
            ->whereIn('type', ['income', 'expense'])
            ->whereDate('date', $today)
            ->get();

        $usedMethods = $cashFlowsToday->pluck('payment_method')->unique();
        $methodsText = "";
        $totalLaci = 0;

        foreach ($usedMethods as $method) {
            $inc = $cashFlowsToday->where('type', 'income')->where('payment_method', $method)->sum('amount');
            $exp = $cashFlowsToday->where('type', 'expense')->where('payment_method', $method)->sum('amount');
            $net = $inc - $exp;
            $totalLaci += $net;

            $methodName = ucfirst($method);
            $methodsText .= "\n🔹 {$methodName}: Rp " . number_format($net, 0, ',', '.') . " (Masuk: Rp " . number_format($inc, 0, ',', '.') . " | Keluar: Rp " . number_format($exp, 0, ',', '.') . ")";
        }

        if (empty($methodsText)) {
            $methodsText = "\n- Belum ada transaksi.";
        }

        // 4. Produk Keluar Hari Ini
        $productsSold = DB::table('transactions')
            ->join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->where('transactions.business_id', $businessId)
            ->whereDate('transactions.transaction_date', $today)
            ->select('products.name', DB::raw('SUM(transaction_details.quantity) as qty'))
            ->groupBy('products.name')
            ->orderByDesc('qty')
            ->get();

        $productSoldText = "";
        foreach ($productsSold as $item) {
            $productSoldText .= "\n📦 {$item->name}: {$item->qty} unit";
        }
        if (empty($productSoldText)) {
            $productSoldText = "\n- Belum ada produk terjual.";
        }

        // ==============================================================
        // C. FORMATTING TEKS UNTUK WHATSAPP
        // ==============================================================
        $dateFormatted = $today->isoFormat('dddd, D MMMM Y');

        $whatsappMessage = "📊 *REPORT HARIAN BUKUDIG* 📊\n📅 {$dateFormatted}\n\n";

        $whatsappMessage .= "👑 *DASHBOARD ADMIN*\n";
        $whatsappMessage .= "💰 Penjualan Hari Ini: Rp " . number_format($salesToday, 0, ',', '.') . "\n";
        $whatsappMessage .= "📈 Laba Bersih Bulan Ini: Rp " . number_format($netProfitThisMonth, 0, ',', '.') . "\n";
        $whatsappMessage .= "✅ Opname Stok Aman: {$safeStockCount} Produk\n";
        $whatsappMessage .= "♻️ Riwayat Pecah Ball Hari Ini: {$pecahBallText}\n\n";

        $whatsappMessage .= "👨‍💼 *DASHBOARD KASIR*\n";
        $whatsappMessage .= "💵 Saldo Laci Kasir: Rp " . number_format($totalLaci, 0, ',', '.') . "\n";
        $whatsappMessage .= "📝 Kasbon/Unpaid Hari Ini: Rp " . number_format($unpaidAmount, 0, ',', '.') . " ({$unpaidCount} Struk)\n";
        $whatsappMessage .= "✅ Transaksi Lunas: {$paidTransactionsCount} Struk\n\n";

        $whatsappMessage .= "💳 *Rincian Saldo per Metode Pembayaran:*" . $methodsText . "\n\n";

        $whatsappMessage .= "🛒 *Produk Keluar Hari Ini:*" . $productSoldText;

        // Mengembalikan format JSON dengan satu key teks untuk mempermudah iOS Shortcuts
        return response()->json([
            'status' => 'success',
            'whatsapp_text' => $whatsappMessage
        ]);
    }
}
