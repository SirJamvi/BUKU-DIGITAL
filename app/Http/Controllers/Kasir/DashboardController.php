<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Accounting\DailyTransactionSummaryService;
use App\Services\Kasir\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\CashierOpeningBalance;
use App\Models\Transaction;

class DashboardController extends Controller
{
    /**
     * @var DashboardService
     */
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Menampilkan dashboard utama untuk kasir.
     * Sesuai SOP, dashboard kasir menampilkan ringkasan penjualan harian mereka.
     *
     * @return View
     */
    public function index(): View
    {
        try {
            $kasirId = Auth::id();
            $data = $this->dashboardService->getDashboardData($kasirId);
            
            return view('kasir.dashboard.index', $data);
        } catch (\Exception $e) {
            logger()->error('Error fetching kasir dashboard data: ' . $e->getMessage());
            if (view()->exists('kasir.errors.500')) {
                return view('kasir.errors.500', ['message' => 'Tidak dapat memuat data dashboard.']);
            }
            abort(500, 'Tidak dapat memuat data dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Menyimpan atau memperbarui Modal Awal Laci (Kas Kemarin) untuk kasir hari ini.
     */
    public function setOpeningBalance(Request $request): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'notes'  => 'nullable|string|max:255',
        ]);

        $businessId = Auth::user()->business_id;
        $userId = Auth::id();
        $branchId = app(\App\Support\BranchContext::class)->requireBranchId();
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        CashierOpeningBalance::updateOrCreate(
            [
                'business_id' => $businessId,
                'branch_id'   => $branchId,
                'user_id'     => $userId,
                'date'        => $today,
            ],
            [
                'amount' => $request->amount,
                'notes'  => $request->notes,
            ]
        );

        return redirect()->back()->with('success', 'Modal Awal Laci (Kas Kemarin) sebesar Rp ' . number_format($request->amount, 0, ',', '.') . ' berhasil disimpan.');
    }

    /**
     * Halaman khusus Rekap Shift & Detail Transaksi Full untuk Kasir (mendukung filter tanggal & cetak).
     */
    public function shiftSummary(Request $request, DailyTransactionSummaryService $summaryService): View
    {
        $kasirId = (int) Auth::id();
        $businessId = (int) Auth::user()->business_id;
        $branchId = app(\App\Support\BranchContext::class)->getBranchId();

        $dateInput = $request->input('date');
        $targetDate = $dateInput ? Carbon::parse($dateInput, 'Asia/Jakarta')->startOfDay() : Carbon::today('Asia/Jakarta');
        $dateStr = $targetDate->toDateString();

        $openingQuery = CashierOpeningBalance::where('business_id', $businessId)
            ->where('user_id', $kasirId)
            ->whereDate('date', $dateStr);
        if ($branchId) {
            $openingQuery->where('branch_id', $branchId);
        }
        $openingRecord = $openingQuery->first();
        $myOpeningCash = $openingRecord ? (float) $openingRecord->amount : 0.0;
        $myOpeningNotes = $openingRecord ? ($openingRecord->notes ?? '') : '';

        $transactionSummary = $summaryService->getForCashier(
            $businessId,
            $branchId ? (int) $branchId : null,
            $kasirId,
            $targetDate,
            $myOpeningCash
        );

        // Snapshot Piutang Kasbon Aktif (lintas cabang dalam bisnis) & Trip Berjalan
        $pendingKasbonRaw = Transaction::withoutGlobalScope('branch')
            ->where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', '!=', 'cancelled')
            ->whereNull('voided_at')
            ->where('payment_status', 'pending')
            ->where(function ($q) {
                $q->whereNull('order_status')->orWhere('order_status', '!=', 'delivering');
            })
            ->where(function ($q) {
                $q->whereNull('delivery_status')->orWhere('delivery_status', '!=', 'delivering');
            })
            ->get(['id', 'total_amount', 'deposit_used', 'net_payable_amount']);

        $activeDeliveriesQuery = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', '!=', 'cancelled')
            ->whereNull('voided_at')
            ->whereNotIn('order_status', ['cancelled', 'delivered'])
            ->whereNotIn('delivery_status', ['cancelled', 'delivered'])
            ->where(function ($q) {
                $q->where('order_status', 'delivering')->orWhere('delivery_status', 'delivering');
            });
        if ($branchId) {
            $activeDeliveriesQuery->where('branch_id', $branchId);
        }
        $activeDeliveriesRaw = $activeDeliveriesQuery->get(['id', 'delivery_trip_code', 'driver_id', 'claimed_by_driver_id', 'transaction_date', 'total_amount', 'deposit_used', 'net_payable_amount']);

        $activeTripsCount = $activeDeliveriesRaw->groupBy(function ($item) {
            return $item->delivery_trip_code ?: ('TRIP-' . ($item->driver_id ?: $item->claimed_by_driver_id) . '-' . optional($item->transaction_date)->format('ymdHi'));
        })->count();

        return view('kasir.dashboard.shift_summary', [
            'selectedDate'           => $dateStr,
            'formattedDate'          => $targetDate->isoFormat('dddd, D MMMM Y'),
            'isToday'                => $targetDate->isToday(),
            'myOpeningCash'          => $myOpeningCash,
            'myOpeningNotes'         => $myOpeningNotes,
            'totalCashInToday'       => (float) $transactionSummary['drawer']['cash_in_total'],
            'myExpensesToday'        => (float) $transactionSummary['drawer']['out_total'],
            'myNetCashToday'         => (float) $transactionSummary['drawer']['expected_drawer'],
            'transactionSummary'     => $transactionSummary,
            'totalKasbonCount'       => $pendingKasbonRaw->count(),
            'totalKasbonAmount'      => (float) $pendingKasbonRaw->sum(fn ($tx) => (float) $tx->effective_payable_amount),
            'activeTripsCount'       => $activeTripsCount,
            'activeTripsOrderCount'  => $activeDeliveriesRaw->count(),
            'activeTripsTotalAmount' => (float) $activeDeliveriesRaw->sum(fn ($tx) => (float) $tx->effective_payable_amount),
        ]);
    }

    /**
     * Endpoint JSON AJAX Drilldown item penyusun baris Matriks Transaksi Full untuk kasir yang login.
     */
    public function transactionDrilldownJson(Request $request, DailyTransactionSummaryService $summaryService): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|in:sale_direct,debt_settlement,customer_deposit,inflow_gross,refunds,drawer_out,non_drawer_out,kasbon_new,deposit_used',
            'channel'  => 'nullable|string|in:all,cash,dana,transfer,deposit,other',
            'date'     => 'nullable|date',
        ]);

        $businessId = (int) Auth::user()->business_id;
        $branchId = app(\App\Support\BranchContext::class)->getBranchId();
        $kasirId = (int) Auth::id();
        $date = !empty($validated['date'])
            ? Carbon::parse($validated['date'], 'Asia/Jakarta')
            : Carbon::today('Asia/Jakarta');

        $items = $summaryService->getDrilldownItems(
            $businessId,
            $date,
            $validated['category'],
            $validated['channel'] ?? null,
            $branchId ? (int) $branchId : null,
            $kasirId
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