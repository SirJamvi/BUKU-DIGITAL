<?php

namespace App\Services\Kasir;

use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
use App\Models\CashierShiftClosing;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Services\Accounting\AccountingPeriodGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashierClosingService
{
    /**
     * Mencatat mutasi kas fisik dari laci kasir ke rekening Owner atau Bank (Cash Handover / Drop).
     * Bukan beban P&L, melainkan transfer kas internal yang mengurangi saldo fisik laci.
     */
    public function recordCashHandover(
        int $businessId,
        int $branchId,
        int $cashierId,
        float $amount,
        ?string $notes = null
    ): CashFlow {
        AccountingPeriodGuard::ensurePeriodOpen($businessId, $branchId, now());

        $category = ExpenseCategory::firstOrCreate(
            [
                'business_id' => $businessId,
                'name'        => 'Setoran Kas Laci ke Owner/Bank',
            ],
            [
                'type'          => 'Operasional',
                'category_type' => 'Non-usaha',
                'is_cogs'       => 0,
                'is_active'     => 1,
            ]
        );

        return CashFlow::create([
            'business_id'    => $businessId,
            'branch_id'      => $branchId,
            'type'           => 'expense',
            'flow_type'      => 'cash_handover',
            'reference_type' => 'handover',
            'category_id'    => $category->id,
            'amount'         => $amount,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'description'    => 'Setoran Kas Fisik Laci ke Owner/Bank' . ($notes ? ": {$notes}" : ''),
            'date'           => now()->toDateString(),
            'created_by'     => $cashierId,
        ]);
    }

    /**
     * Menghitung rekonsiliasi dan menutup shift kasir harian (Cashier Shift Closing).
     * Mencatat Cash Over/Short (variance) secara permanen.
     */
    public function closeDailyShift(array $data): CashierShiftClosing
    {
        $businessId = (int) $data['business_id'];
        $branchId = (int) $data['branch_id'];
        $userId = (int) $data['user_id'];
        $date = !empty($data['date']) ? Carbon::parse($data['date'])->toDateString() : now()->toDateString();
        $shiftCode = $data['shift_code'] ?? 'default';
        $actualCashCounted = (float) ($data['actual_cash_counted'] ?? 0);
        $endingCashForNextShift = (float) ($data['ending_cash_for_next_shift'] ?? 0);
        $notes = $data['notes'] ?? null;
        $closedBy = !empty($data['closed_by']) ? (int) $data['closed_by'] : (Auth::id() ?: $userId);

        AccountingPeriodGuard::ensurePeriodOpen($businessId, $branchId, $date);

        // 1. Modal Awal Laci
        $openingRecord = CashierOpeningBalance::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->first();
        $openingCash = $openingRecord ? (float) $openingRecord->amount : 0.00;

        // 2. Penjualan Tunai Meja Langsung Lunas (SSOT 100% dari tabel cash_flow)
        $cashSales = (float) CashFlow::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('created_by', $userId)
            ->where('type', 'income')
            ->where('flow_type', 'sale_direct')
            ->where('affects_drawer', 1)
            ->whereRaw('LOWER(payment_method) = ?', ['cash'])
            ->whereDate('date', $date)
            ->sum('amount');

        // 3. Pelunasan Piutang / Kasbon Tunai (Debt Settlement)
        $cashDebtCollection = (float) CashFlow::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('created_by', $userId)
            ->where('type', 'income')
            ->where('payment_method', 'cash')
            ->where('affects_drawer', 1)
            ->where(function ($q) {
                $q->where('flow_type', 'debt_settlement')
                  ->orWhere('description', 'LIKE', '%Pelunasan%');
            })
            ->whereDate('date', $date)
            ->sum('amount');

        // 4. Pengeluaran Laci Kasir (Beban Operasional & Pembelian Stok Luar)
        $cashExpenses = (float) CashFlow::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('created_by', $userId)
            ->where('type', 'expense')
            ->where('payment_method', 'cash')
            ->where('affects_drawer', 1)
            ->whereIn('flow_type', ['expense', 'stock_purchase'])
            ->whereDate('date', $date)
            ->sum('amount');

        // 5. Setoran Fisik Laci (Cash Handover)
        $cashHandoverAmount = (float) CashFlow::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('created_by', $userId)
            ->where('type', 'expense')
            ->where('payment_method', 'cash')
            ->where('affects_drawer', 1)
            ->where('flow_type', 'cash_handover')
            ->whereDate('date', $date)
            ->sum('amount');

        // 6. Contra-Entry Refund / Void Tunai
        $cashRefunds = (float) CashFlow::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('created_by', $userId)
            ->where('type', 'expense')
            ->where('payment_method', 'cash')
            ->where('affects_drawer', 1)
            ->where('flow_type', 'cash_refund')
            ->whereDate('date', $date)
            ->sum('amount');

        // 7. Expected Drawer Cash & Variance
        $expectedDrawerCash = $openingCash + $cashSales + $cashDebtCollection - ($cashExpenses + $cashHandoverAmount + $cashRefunds);
        $variance = $actualCashCounted - $expectedDrawerCash;

        return CashierShiftClosing::updateOrCreate(
            [
                'business_id' => $businessId,
                'branch_id'   => $branchId,
                'user_id'     => $userId,
                'date'        => $date,
                'shift_code'  => $shiftCode,
            ],
            [
                'opening_cash'               => $openingCash,
                'cash_sales'                 => $cashSales,
                'cash_debt_collection'       => $cashDebtCollection,
                'cash_expenses'              => $cashExpenses,
                'cash_handover_amount'       => $cashHandoverAmount,
                'cash_refunds'               => $cashRefunds,
                'expected_drawer_cash'       => $expectedDrawerCash,
                'actual_cash_counted'        => $actualCashCounted,
                'variance'                   => $variance,
                'ending_cash_for_next_shift' => $endingCashForNextShift,
                'notes'                      => $notes,
                'closed_at'                  => now(),
                'closed_by'                  => $closedBy,
            ]
        );
    }
}
