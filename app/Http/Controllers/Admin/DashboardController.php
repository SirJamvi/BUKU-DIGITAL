<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Accounting\DailyTransactionSummaryService;
use App\Services\Admin\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Menampilkan halaman dashboard admin.
     */
    public function index(Request $request): View
    {
        try {
            // --- SETUP FILTER TANGGAL ---
            // Mengambil input tanggal dari URL (jika ada), default-nya adalah hari ini
            $dateInput = $request->input('filter_date');
            $filterDate = $dateInput ? Carbon::parse($dateInput)->startOfDay() : Carbon::today();

            // Mengambil semua data dashboard dari service bawaan mengikuti tanggal filter
            $dashboardData = $this->dashboardService->getDashboardData($filterDate);

            // Simpan ke array untuk dikirim ke view
            $dashboardData['filterDate'] = $filterDate->format('Y-m-d');
            $dashboardData['isToday'] = $filterDate->isToday();
            $dashboardData['formattedDate'] = $filterDate->isoFormat('D MMMM Y');

            // Mengambil stok realtime (tidak terpengaruh filter tanggal, selalu stok saat ini - abaikan ongkos kirim)
            $dashboardData['realtimeStocks'] = \App\Models\Inventory::with('product')
                ->whereHas('product', function ($q) {
                    $q->where('name', 'NOT LIKE', '%ongkos kirim%')
                      ->where('name', 'NOT LIKE', '%ongkir%');
                })
                ->get();

            // Mengambil data pergerakan stok (miss opname) berdasarkan tanggal filter
            $dashboardData['missedOpnames'] = \App\Models\StockMovement::with(['product', 'createdBy'])
                ->whereDate('created_at', $filterDate)
                ->whereNotNull('notes')
                ->where('notes', '!=', '')
                ->where(function ($query) {
                    $query->where('type', 'adjustment')
                        ->orWhere('type', 'opname');
                })
                ->orderBy('created_at', 'desc')
                ->get();

            // --- HISTORY PECAH BALL BERDASARKAN TANGGAL FILTER ---

            // 1. Ambil pergerakan stok KELUAR (Karung/Ball yang dipecah)
            $breakUnitOuts = \App\Models\StockMovement::with(['product', 'createdBy'])
                ->whereDate('created_at', $filterDate)
                ->where('notes', 'Pecah Ball (Bahan Baku)')
                ->orderBy('created_at', 'desc')
                ->get();

            // 2. Ambil pergerakan stok MASUK (Hasil pecahan eceran)
            $breakUnitIns = \App\Models\StockMovement::with(['product'])
                ->whereDate('created_at', $filterDate)
                ->where('notes', 'LIKE', 'Hasil Pecahan dari Produk ID:%')
                ->orderBy('created_at', 'desc')
                ->get();

            // 3. Pasangkan data Induk (yang dipecah) dengan Anaknya (hasil pecahannya)
            $breakUnitHistory = [];
            foreach ($breakUnitOuts as $out) {
                // Kita kelompokkan berdasarkan menit yang sama dan ID produk asal
                $timeKey = $out->created_at->format('Y-m-d H:i');

                $hasil = $breakUnitIns->filter(function ($in) use ($timeKey, $out) {
                    return $in->created_at->format('Y-m-d H:i') === $timeKey &&
                        str_contains($in->notes, 'Produk ID: ' . $out->product_id);
                });

                $breakUnitHistory[] = [
                    'time' => $out->created_at,
                    'kasir' => $out->createdBy->name ?? 'Kasir',
                    'source_product' => $out->product->name ?? 'Produk Dihapus',
                    'source_qty' => $out->quantity,
                    'targets' => $hasil // Kumpulan kemasan hasil
                ];
            }

            $dashboardData['breakUnitHistory'] = $breakUnitHistory;

            // --- REKAP PENGIRIMAN DRIVER BERDASARKAN FILTER TANGGAL ---
            $businessId = Auth::user()->business_id;

            $driverTransactions = Transaction::with([
                    'customer',
                    'driver',
                    'details.product'
                ])
                ->where('business_id', $businessId)
                ->where('type', 'sale')
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) {
                    $q->whereNotNull('driver_id')
                      ->orWhereNotNull('claimed_by_driver_id');
                })
                ->whereDate('transaction_date', $filterDate)
                ->orderBy('transaction_date', 'asc')
                ->get();

            $driverRecap = [];
            $groupedByDriver = $driverTransactions->groupBy(function ($item) {
                return $item->driver_id ?: ($item->claimed_by_driver_id ?: 'unknown');
            });

            foreach ($groupedByDriver as $driverId => $trxs) {
                $driver = $trxs->first()->driver ?? User::find($driverId);
                $driverName = $driver ? $driver->name : 'Driver #' . $driverId;
                $driverPhone = $driver ? $driver->phone : '-';

                // Kelompokkan per Surat Jalan / Ritase (Trip)
                $trips = $trxs->groupBy(function ($t) {
                    return $t->delivery_trip_code ?: ('TRX-' . str_pad($t->id, 6, '0', STR_PAD_LEFT));
                });

                $totalQty = 0;
                $productQuantities = [];

                foreach ($trxs as $trx) {
                    foreach ($trx->details as $detail) {
                        $pName = $detail->product->name ?? 'Produk';
                        if ($detail->product && $detail->product->isService()) {
                            continue; // Abaikan produk jasa/ongkir dari hitungan muatan fisik ball
                        }
                        $qty = (int) $detail->quantity;
                        $totalQty += $qty;
                        $productQuantities[$pName] = ($productQuantities[$pName] ?? 0) + $qty;
                    }
                }

                $totalAmount = $trxs->sum('total_amount');
                $totalPaid = $trxs->where('payment_status', 'paid')->sum('total_amount');
                $totalPending = $trxs->where('payment_status', 'pending')->sum('total_amount');

                // Breakdown metode pembayaran riil
                $paymentMethodsSummary = [];
                foreach ($trxs as $trx) {
                    if ($trx->payment_status === 'pending') {
                        $methodName = 'Kasbon / Pending';
                    } else {
                        $methodName = strtoupper(str_replace('-', ' ', $trx->payment_method ?: 'Cash'));
                    }
                    $paymentMethodsSummary[$methodName] = ($paymentMethodsSummary[$methodName] ?? 0) + $trx->total_amount;
                }

                $tripList = [];
                foreach ($trips as $tripCode => $tripItems) {
                    $tripQty = 0;
                    $tripProductBreakdown = [];
                    foreach ($tripItems as $tItem) {
                        foreach ($tItem->details as $d) {
                            $pName = $d->product->name ?? 'Produk';
                            if ($d->product && $d->product->isService()) {
                                continue; // Abaikan produk jasa/ongkir dari hitungan muatan ritase
                            }
                            $qty = (int) $d->quantity;
                            $tripQty += $qty;
                            $tripProductBreakdown[$pName] = ($tripProductBreakdown[$pName] ?? 0) + $qty;
                        }
                    }

                    $tripList[] = [
                        'trip_code' => $tripCode,
                        'is_batch' => str_starts_with($tripCode, 'TRIP-'),
                        'time' => $tripItems->first()->transaction_date,
                        'total_orders' => $tripItems->count(),
                        'total_qty' => $tripQty,
                        'product_breakdown' => $tripProductBreakdown,
                        'total_amount' => $tripItems->sum('total_amount'),
                        'transactions' => $tripItems,
                    ];
                }

                $driverRecap[] = [
                    'driver_id' => $driverId,
                    'driver_name' => $driverName,
                    'driver_phone' => $driverPhone,
                    'total_trips' => $trips->count(),
                    'total_orders' => $trxs->count(),
                    'total_qty' => $totalQty,
                    'product_quantities' => $productQuantities,
                    'total_amount' => $totalAmount,
                    'total_paid' => $totalPaid,
                    'total_pending' => $totalPending,
                    'payment_methods_summary' => $paymentMethodsSummary,
                    'trips' => $tripList,
                ];
            }

            $dashboardData['driverRecap'] = $driverRecap;

            // --- MONITORING CASH ON HAND REAL PER KASIR (PRIORITAS) ---
            $cashierMonitoring = $this->dashboardService->getCashierCashOnHandData($businessId, $filterDate);
            $cashierSummaryTotals = $this->dashboardService->computeCashierSummaryTotals($cashierMonitoring);
            $dashboardData['cashierMonitoring'] = $cashierMonitoring;
            $dashboardData['cashierSummaryTotals'] = $cashierSummaryTotals;
            $dashboardData['activeCashiersCount'] = $cashierSummaryTotals['active_cashiers_count'] ?? 0;
            $dashboardData['standbyCashiersCount'] = $cashierSummaryTotals['standby_cashiers_count'] ?? 0;

            // --- LOG AKTIVITAS PELUNASAN KASBON & PENGIRIMAN HARI INI ---
            $dashboardData['settlementLogs'] = $this->dashboardService->getSettlementAuditLogs($businessId, $filterDate);

            // --- LIVE MONITORING PERBANDINGAN ANTAR CABANG (MODE KONSOLIDASI PUSAT) ---
            $context = app(\App\Support\BranchContext::class);
            if ($context->isConsolidated() || session('active_branch_id') === 'all') {
                $dashboardData['branchComparison'] = $this->dashboardService->getBranchComparisonData($businessId, $filterDate);
            } else {
                $dashboardData['branchComparison'] = [];
            }

            // --- BREAKDOWN MATRIKS TRANSAKSI FULL PER CABANG & PER KASIR + DROPDOWN FILTER ---
            $summaryService = app(DailyTransactionSummaryService::class);
            $dashboardData['matrixBreakdown'] = $summaryService->getBreakdownByBranchAndCashier($businessId, $filterDate);
            $dashboardData['filterBranches'] = Branch::where('business_id', $businessId)
                ->where('is_active', true)
                ->orderByDesc('is_main')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_main']);
            $dashboardData['filterCashiers'] = User::with('branch:id,name')
                ->where('business_id', $businessId)
                ->where(function ($q) {
                    $q->where('role', 'kasir')
                        ->orWhereJsonContains('secondary_roles', 'kasir')
                        ->orWhere('role', 'admin');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'branch_id']);

            return view('admin.dashboard.index', $dashboardData);
        } catch (\Exception $e) {
            logger()->error('Error fetching dashboard data: ' . $e->getMessage());
            return view('admin.dashboard.index', [
                'error' => 'Tidak dapat memuat data dashboard. Silakan coba lagi nanti.'
            ]);
        }
    }

    /**
     * Endpoint JSON untuk Live Polling (60s) Perbandingan Antar Cabang.
     */
    public function branchComparisonJson(Request $request)
    {
        $businessId = Auth::user()->business_id;
        $dateInput = $request->input('date');
        $date = $dateInput ? Carbon::parse($dateInput) : Carbon::today('Asia/Jakarta');

        $data = $this->dashboardService->getBranchComparisonData($businessId, $date);

        return response()->json([
            'status' => 'success',
            'date'   => $date->toDateString(),
            'data'   => $data,
        ]);
    }

    /**
     * Endpoint JSON AJAX untuk memfilter Matriks Rekapitulasi Transaksi Full (Cabang & Kasir).
     */
    public function transactionMatrixJson(Request $request, DailyTransactionSummaryService $summaryService): JsonResponse
    {
        $validated = $request->validate([
            'date'       => 'nullable|date',
            'branch_id'  => 'nullable|integer',
            'cashier_id' => 'nullable|integer',
        ]);

        $businessId = (int) Auth::user()->business_id;
        $date = !empty($validated['date']) ? Carbon::parse($validated['date']) : Carbon::today('Asia/Jakarta');
        $branchId = !empty($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $cashierId = !empty($validated['cashier_id']) ? (int) $validated['cashier_id'] : null;

        // Jika Admin berada di konteks cabang tunggal, paksa branch_id sesuai konteks
        $context = app(\App\Support\BranchContext::class);
        if ($context->isBranch() && $context->getBranchId() !== null) {
            $branchId = (int) $context->getBranchId();
        }

        $summary = $summaryService->getFilteredSummary($businessId, $date, $branchId, $cashierId);
        $breakdown = $summaryService->getBreakdownByBranchAndCashier($businessId, $date, $branchId);

        return response()->json([
            'status'    => 'success',
            'date'      => $date->toDateString(),
            'summary'   => $summary,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Endpoint JSON AJAX Drilldown item penyusun Matriks Rekapitulasi Transaksi Full Admin.
     */
    public function transactionDrilldownJson(Request $request, DailyTransactionSummaryService $summaryService): JsonResponse
    {
        $validated = $request->validate([
            'category'   => 'required|string|in:sale_direct,debt_settlement,customer_deposit,inflow_gross,refunds,drawer_out,non_drawer_out,kasbon_new,deposit_used',
            'channel'    => 'nullable|string|in:all,cash,dana,transfer,deposit,other',
            'date'       => 'nullable|date',
            'branch_id'  => 'nullable|integer',
            'cashier_id' => 'nullable|integer',
        ]);

        $businessId = (int) Auth::user()->business_id;
        $date = !empty($validated['date']) ? Carbon::parse($validated['date']) : Carbon::today('Asia/Jakarta');
        $branchId = !empty($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $cashierId = !empty($validated['cashier_id']) ? (int) $validated['cashier_id'] : null;

        $context = app(\App\Support\BranchContext::class);
        if ($context->isBranch() && $context->getBranchId() !== null) {
            $branchId = (int) $context->getBranchId();
        }

        $items = $summaryService->getDrilldownItems(
            $businessId,
            $date,
            $validated['category'],
            $validated['channel'] ?? null,
            $branchId,
            $cashierId
        );

        return response()->json([
            'status' => 'success',
            'date'   => $date->toDateString(),
            'count'  => count($items),
            'total'  => array_sum(array_column($items, 'amount')),
            'items'  => $items,
        ]);
    }
}
