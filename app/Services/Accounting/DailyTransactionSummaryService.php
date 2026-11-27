<?php

namespace App\Services\Accounting;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
use App\Models\CustomerDepositLog;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyTransactionSummaryService
{
    /**
     * Normalisasi payment_method ke saluran baku (cash, dana, transfer, deposit, other).
     */
    public static function classifyChannel(?string $paymentMethod): string
    {
        $pm = strtolower(trim((string) $paymentMethod));

        if ($pm === 'cash') {
            return 'cash';
        }

        if ($pm === 'dana') {
            return 'dana';
        }

        if (str_contains($pm, 'transfer') || in_array($pm, ['bca', 'bri', 'mandiri', 'bni', 'bank'], true)) {
            return 'transfer';
        }

        if ($pm === 'deposit') {
            return 'deposit';
        }

        return 'other';
    }

    /**
     * Klasifikasi flow_type arus kas masuk (mendukung data legacy tanpa flow_type).
     */
    public static function resolveIncomeBucket(CashFlow $cf): string
    {
        $ft = $cf->flow_type;

        if ($ft === 'customer_deposit') {
            return 'customer_deposit';
        }

        if ($ft === 'debt_settlement') {
            return 'debt_settlement';
        }

        if ($ft === null && stripos((string) ($cf->description ?? ''), 'Pelunasan') !== false) {
            return 'debt_settlement';
        }

        return 'sale_direct';
    }

    /**
     * Ambil ringkasan transaksi full untuk satu kasir pada tanggal tertentu.
     */
    public function getForCashier(
        int $businessId,
        ?int $branchId,
        int $cashierId,
        Carbon|string $date,
        float $openingCash = 0.0
    ): array {
        $targetDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateStr = $targetDate->toDateString();

        $cashFlowsQuery = CashFlow::where('business_id', $businessId)
            ->where('created_by', $cashierId)
            ->whereIn('type', ['income', 'expense'])
            ->whereDate('date', $dateStr);

        if ($branchId) {
            $cashFlowsQuery->where('branch_id', $branchId);
        }
        $cashFlows = $cashFlowsQuery->get();

        $depositDebitsQuery = CustomerDepositLog::where('business_id', $businessId)
            ->where('created_by', $cashierId)
            ->where('type', 'debit')
            ->whereDate('created_at', $dateStr)
            ->where(function ($q) {
                $q->whereNull('transaction_id')
                    ->orWhereHas('transaction', function ($tq) {
                        $tq->withoutGlobalScopes()
                            ->where('status', '!=', 'cancelled')
                            ->whereNull('voided_at');
                    });
            });

        if ($branchId) {
            $depositDebitsQuery->where('branch_id', $branchId);
        }
        $depositDebits = $depositDebitsQuery->get();

        $transactionsQuery = Transaction::where('business_id', $businessId)
            ->where('created_by', $cashierId)
            ->where('type', 'sale')
            ->where('status', '!=', 'cancelled')
            ->whereNull('voided_at')
            ->whereDate('transaction_date', $dateStr);

        if ($branchId) {
            $transactionsQuery->where('branch_id', $branchId);
        }
        $transactions = $transactionsQuery->get();

        return $this->buildFromCollections($cashFlows, $depositDebits, $transactions, $openingCash);
    }

    /**
     * Ambil ringkasan transaksi full gabungan 1 bisnis/cabang aktif (mengikuti global scope branch aktif) pada tanggal tertentu.
     */
    public function getForBusinessScope(int $businessId, Carbon|string $date, float $openingCash = 0.0): array
    {
        $targetDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateStr = $targetDate->toDateString();

        $cashFlows = CashFlow::where('business_id', $businessId)
            ->whereIn('type', ['income', 'expense'])
            ->whereDate('date', $dateStr)
            ->get();

        $depositDebitsQuery = CustomerDepositLog::where('business_id', $businessId)
            ->where('type', 'debit')
            ->whereDate('created_at', $dateStr)
            ->where(function ($q) {
                $q->whereNull('transaction_id')
                    ->orWhereHas('transaction', function ($tq) {
                        $tq->withoutGlobalScopes()
                            ->where('status', '!=', 'cancelled')
                            ->whereNull('voided_at');
                    });
            });

        app(\App\Support\BranchContext::class)->applyTo($depositDebitsQuery, 'customer_deposit_logs', 'branch_id');
        $depositDebits = $depositDebitsQuery->get();

        $transactions = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', '!=', 'cancelled')
            ->whereNull('voided_at')
            ->whereDate('transaction_date', $dateStr)
            ->get();

        return $this->buildFromCollections($cashFlows, $depositDebits, $transactions, $openingCash);
    }

    /**
     * Mesin kalkulasi SSOT tunggal dari koleksi cash_flow, deposit_debit, dan transactions.
     * Dipakai oleh Dashboard Kasir, Dashboard Admin (Summary Cabang/Bisnis), dan Kartu/Modal Per Kasir Admin.
     */
    public function buildFromCollections(
        Collection $cashFlows,
        Collection $depositDebits,
        Collection $transactions,
        float $openingCash = 0.0
    ): array {
        $emptyChannels = fn () => [
            'cash'         => 0.0,
            'dana'         => 0.0,
            'transfer'     => 0.0,
            'other'        => 0.0,
            'deposit_used' => 0.0,
            'total_money'  => 0.0,
            'total'        => 0.0,
            'count'        => 0,
        ];

        $rows = [
            'sale_direct'      => $emptyChannels(),
            'debt_settlement'  => $emptyChannels(),
            'customer_deposit' => $emptyChannels(),
            'inflow_gross'     => $emptyChannels(),
            'refunds'          => $emptyChannels(),
            'refund_void'      => $emptyChannels(),
            'refund_deposit'   => $emptyChannels(),
            'inflow_net'       => $emptyChannels(),
        ];

        $drawer = [
            'opening_cash'       => round($openingCash, 2),
            'cash_in_sales'      => 0.0,
            'cash_in_settlement' => 0.0,
            'cash_in_deposit'    => 0.0,
            'cash_in_total'      => 0.0,
            'out_operational'    => 0.0,
            'out_stock_purchase' => 0.0,
            'out_cash_handover'  => 0.0,
            'out_cash_refund'    => 0.0,
            'out_deposit_refund' => 0.0,
            'out_total'          => 0.0,
            'expected_drawer'    => 0.0,
        ];

        $nonDrawer = [
            'operational_and_other' => 0.0,
            'cash_refund'           => 0.0,
            'deposit_refund'        => 0.0,
            'total'                 => 0.0,
        ];

        // 1. Proses seluruh CashFlow (Income & Expense)
        foreach ($cashFlows as $cf) {
            $amount = (float) ($cf->amount ?? 0.0);
            if ($amount <= 0) {
                continue;
            }

            $channel = self::classifyChannel($cf->payment_method);
            $moneyChannel = in_array($channel, ['cash', 'dana', 'transfer'], true) ? $channel : 'other';
            $affectsDrawer = (bool) ($cf->affects_drawer ?? false);

            if ($cf->type === 'income') {
                $bucket = self::resolveIncomeBucket($cf);
                $rows[$bucket][$moneyChannel] += $amount;
                $rows[$bucket]['total_money'] += $amount;
                $rows[$bucket]['total'] += $amount;
                $rows[$bucket]['count']++;

                if ($affectsDrawer && $moneyChannel === 'cash') {
                    if ($bucket === 'sale_direct') {
                        $drawer['cash_in_sales'] += $amount;
                    } elseif ($bucket === 'debt_settlement') {
                        $drawer['cash_in_settlement'] += $amount;
                    } elseif ($bucket === 'customer_deposit') {
                        $drawer['cash_in_deposit'] += $amount;
                    }
                }
            } elseif ($cf->type === 'expense') {
                $ft = $cf->flow_type ?? 'expense';

                if ($ft === 'cash_refund') {
                    $rows['refund_void'][$moneyChannel] += $amount;
                    $rows['refund_void']['total_money'] += $amount;
                    $rows['refund_void']['total'] += $amount;
                    $rows['refund_void']['count']++;

                    $rows['refunds'][$moneyChannel] += $amount;
                    $rows['refunds']['total_money'] += $amount;
                    $rows['refunds']['total'] += $amount;
                    $rows['refunds']['count']++;
                } elseif ($ft === 'deposit_refund') {
                    $rows['refund_deposit'][$moneyChannel] += $amount;
                    $rows['refund_deposit']['total_money'] += $amount;
                    $rows['refund_deposit']['total'] += $amount;
                    $rows['refund_deposit']['count']++;

                    $rows['refunds'][$moneyChannel] += $amount;
                    $rows['refunds']['total_money'] += $amount;
                    $rows['refunds']['total'] += $amount;
                    $rows['refunds']['count']++;
                }

                // Klasifikasi Kas Keluar Laci vs Non-Laci (100% sinkron dengan CashierClosingService)
                $isDrawerFlow = $affectsDrawer
                    && $moneyChannel === 'cash'
                    && in_array($ft, ['expense', 'stock_purchase', 'cash_handover', 'cash_refund', 'deposit_refund'], true);

                if ($isDrawerFlow) {
                    match ($ft) {
                        'expense'        => $drawer['out_operational'] += $amount,
                        'stock_purchase' => $drawer['out_stock_purchase'] += $amount,
                        'cash_handover'  => $drawer['out_cash_handover'] += $amount,
                        'cash_refund'    => $drawer['out_cash_refund'] += $amount,
                        'deposit_refund' => $drawer['out_deposit_refund'] += $amount,
                        default          => null,
                    };
                } else {
                    if ($ft === 'cash_refund') {
                        $nonDrawer['cash_refund'] += $amount;
                    } elseif ($ft === 'deposit_refund') {
                        $nonDrawer['deposit_refund'] += $amount;
                    } else {
                        $nonDrawer['operational_and_other'] += $amount;
                    }
                }
            }
        }

        // 2. Proses Pemakaian Saldo Deposit (CustomerDepositLog type = debit)
        $depositUsedCountSales = 0;
        $depositUsedCountSettlement = 0;
        foreach ($depositDebits as $log) {
            $amount = (float) ($log->amount ?? 0.0);
            if ($amount <= 0) {
                continue;
            }

            $isSettlement = stripos((string) ($log->notes ?? ''), 'Pelunasan') !== false;
            if ($isSettlement) {
                $rows['debt_settlement']['deposit_used'] += $amount;
                $rows['debt_settlement']['total'] += $amount;
                $depositUsedCountSettlement++;
            } else {
                $rows['sale_direct']['deposit_used'] += $amount;
                $rows['sale_direct']['total'] += $amount;
                $depositUsedCountSales++;
            }
        }

        // Hitung subtotal Uang Masuk Bruto & Netto per saluran
        foreach (['cash', 'dana', 'transfer', 'other'] as $ch) {
            $rows['inflow_gross'][$ch] = $rows['sale_direct'][$ch]
                + $rows['debt_settlement'][$ch]
                + $rows['customer_deposit'][$ch];

            $rows['inflow_net'][$ch] = $rows['inflow_gross'][$ch] - $rows['refunds'][$ch];
        }

        $rows['inflow_gross']['deposit_used'] = $rows['sale_direct']['deposit_used'] + $rows['debt_settlement']['deposit_used'];
        $rows['inflow_gross']['total_money'] = $rows['inflow_gross']['cash']
            + $rows['inflow_gross']['dana']
            + $rows['inflow_gross']['transfer']
            + $rows['inflow_gross']['other'];
        $rows['inflow_gross']['total'] = $rows['inflow_gross']['total_money'];
        $rows['inflow_gross']['count'] = $rows['sale_direct']['count']
            + $rows['debt_settlement']['count']
            + $rows['customer_deposit']['count'];

        $rows['inflow_net']['total_money'] = $rows['inflow_gross']['total_money'] - $rows['refunds']['total_money'];
        $rows['inflow_net']['total'] = $rows['inflow_net']['total_money'];

        // Finalisasi Rekonsiliasi Laci & Non-Laci
        $drawer['cash_in_total'] = $drawer['cash_in_sales'] + $drawer['cash_in_settlement'] + $drawer['cash_in_deposit'];
        $drawer['out_total'] = $drawer['out_operational']
            + $drawer['out_stock_purchase']
            + $drawer['out_cash_handover']
            + $drawer['out_cash_refund']
            + $drawer['out_deposit_refund'];
        $drawer['expected_drawer'] = $drawer['opening_cash'] + $drawer['cash_in_total'] - $drawer['out_total'];

        $nonDrawer['total'] = $nonDrawer['operational_and_other']
            + $nonDrawer['cash_refund']
            + $nonDrawer['deposit_refund'];

        // 3. Proses Ringkasan Omset & Kasbon Baru Hari Ini (dari tabel transactions)
        $omsetToday = 0.0;
        $omsetCount = 0;
        $omsetPaidToday = 0.0;
        $omsetPaidCount = 0;
        $kasbonNewToday = 0.0;
        $kasbonNewCount = 0;
        $deliveringTodayAmount = 0.0;
        $deliveringTodayCount = 0;

        foreach ($transactions as $tx) {
            $amt = (float) ($tx->total_amount ?? 0.0);
            $omsetToday += $amt;
            $omsetCount++;

            if ($tx->payment_status === 'paid') {
                $omsetPaidToday += $amt;
                $omsetPaidCount++;
            } elseif ($tx->payment_status === 'pending') {
                $isDelivering = ($tx->order_status === 'delivering' || $tx->delivery_status === 'delivering');
                $payable = (float) $tx->effective_payable_amount;
                if ($isDelivering) {
                    $deliveringTodayAmount += $payable;
                    $deliveringTodayCount++;
                } else {
                    $kasbonNewToday += $payable;
                    $kasbonNewCount++;
                }
            }
        }

        // 4. Ringkasan Mutasi Deposit Hari Ini
        $depositInTotal = $rows['customer_deposit']['total_money'];
        $depositUsedTotal = $rows['inflow_gross']['deposit_used'];
        $depositRefundTotal = $rows['refund_deposit']['total_money'];

        $depositSummary = [
            'in_cash'         => $rows['customer_deposit']['cash'],
            'in_dana'         => $rows['customer_deposit']['dana'],
            'in_transfer'     => $rows['customer_deposit']['transfer'],
            'in_other'        => $rows['customer_deposit']['other'],
            'in_total'        => $depositInTotal,
            'in_count'        => $rows['customer_deposit']['count'],
            'used_sales'      => $rows['sale_direct']['deposit_used'],
            'used_settlement' => $rows['debt_settlement']['deposit_used'],
            'used_total'      => $depositUsedTotal,
            'used_count'      => $depositUsedCountSales + $depositUsedCountSettlement,
            'refund_cash'     => $rows['refund_deposit']['cash'],
            'refund_dana'     => $rows['refund_deposit']['dana'],
            'refund_transfer' => $rows['refund_deposit']['transfer'],
            'refund_other'    => $rows['refund_deposit']['other'],
            'refund_total'    => $depositRefundTotal,
            'refund_count'    => $rows['refund_deposit']['count'],
            'net_change'      => $depositInTotal - $depositUsedTotal - $depositRefundTotal,
        ];

        $hasOtherChannel = ($rows['inflow_gross']['other'] > 0 || $rows['refunds']['other'] > 0);
        $nonCashGrossTotal = $rows['inflow_gross']['dana'] + $rows['inflow_gross']['transfer'] + $rows['inflow_gross']['other'];
        $nonCashNetTotal = $rows['inflow_net']['dana'] + $rows['inflow_net']['transfer'] + $rows['inflow_net']['other'];

        return [
            'rows'               => $rows,
            'drawer'             => $drawer,
            'non_drawer'         => $nonDrawer,
            'deposit'            => $depositSummary,
            'sales_kasbon'       => [
                'omset_today'             => $omsetToday,
                'omset_count'             => $omsetCount,
                'omset_paid_today'        => $omsetPaidToday,
                'omset_paid_count'        => $omsetPaidCount,
                'kasbon_new_today'        => $kasbonNewToday,
                'kasbon_new_count'        => $kasbonNewCount,
                'delivering_today_amount' => $deliveringTodayAmount,
                'delivering_today_count'  => $deliveringTodayCount,
            ],
            'non_cash_gross_total' => $nonCashGrossTotal,
            'non_cash_net_total'   => $nonCashNetTotal,
            'has_other_channel'    => $hasOtherChannel,
        ];
    }

    /**
     * Kembalikan struktur kosong aman untuk fallback catch block.
     */
    public function emptySummary(): array
    {
        return $this->buildFromCollections(collect(), collect(), collect(), 0.0);
    }

    /**
     * Ambil ringkasan transaksi full terfilter (mendukung filter cabang & kasir opsional untuk AJAX Admin).
     */
    public function getFilteredSummary(
        int $businessId,
        Carbon|string $date,
        ?int $branchId = null,
        ?int $cashierId = null
    ): array {
        $targetDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateStr = $targetDate->toDateString();
        $context = app(BranchContext::class);

        $cfQuery = CashFlow::withoutGlobalScope('branch')
            ->where('business_id', $businessId)
            ->whereIn('type', ['income', 'expense'])
            ->whereDate('date', $dateStr);

        $depQuery = CustomerDepositLog::where('business_id', $businessId)
            ->where('type', 'debit')
            ->whereDate('created_at', $dateStr)
            ->where(function ($q) {
                $q->whereNull('transaction_id')
                    ->orWhereHas('transaction', function ($tq) {
                        $tq->withoutGlobalScopes()
                            ->where('status', '!=', 'cancelled')
                            ->whereNull('voided_at');
                    });
            });

        $txQuery = Transaction::withoutGlobalScope('branch')
            ->where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', '!=', 'cancelled')
            ->whereNull('voided_at')
            ->whereDate('transaction_date', $dateStr);

        $openingQuery = CashierOpeningBalance::where('business_id', $businessId)
            ->whereDate('date', $dateStr);

        if ($branchId !== null) {
            $cfQuery->where('branch_id', $branchId);
            $depQuery->where('branch_id', $branchId);
            $txQuery->where('branch_id', $branchId);
            $openingQuery->where('branch_id', $branchId);
        } else {
            $context->applyTo($cfQuery, 'cash_flow', 'branch_id');
            $context->applyTo($depQuery, 'customer_deposit_logs', 'branch_id');
            $context->applyTo($txQuery, 'transactions', 'branch_id');
            $context->applyTo($openingQuery, 'cashier_opening_balances', 'branch_id');
        }

        if ($cashierId !== null) {
            $cfQuery->where('created_by', $cashierId);
            $depQuery->where('created_by', $cashierId);
            $txQuery->where('created_by', $cashierId);
            $openingQuery->where('user_id', $cashierId);
        }

        $openingCash = (float) $openingQuery->sum('amount');

        return $this->buildFromCollections(
            $cfQuery->get(),
            $depQuery->get(),
            $txQuery->get(),
            $openingCash
        );
    }

    /**
     * Ambil breakdown ringkasan per Cabang dan per Kasir secara batch (zero N+1) untuk tab di Admin Dashboard.
     */
    public function getBreakdownByBranchAndCashier(
        int $businessId,
        Carbon|string $date,
        ?int $branchId = null
    ): array {
        $targetDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateStr = $targetDate->toDateString();
        $context = app(BranchContext::class);

        $cfQuery = CashFlow::withoutGlobalScope('branch')
            ->where('business_id', $businessId)
            ->whereIn('type', ['income', 'expense'])
            ->whereDate('date', $dateStr);

        $depQuery = CustomerDepositLog::where('business_id', $businessId)
            ->where('type', 'debit')
            ->whereDate('created_at', $dateStr)
            ->where(function ($q) {
                $q->whereNull('transaction_id')
                    ->orWhereHas('transaction', function ($tq) {
                        $tq->withoutGlobalScopes()
                            ->where('status', '!=', 'cancelled')
                            ->whereNull('voided_at');
                    });
            });

        $txQuery = Transaction::withoutGlobalScope('branch')
            ->where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', '!=', 'cancelled')
            ->whereNull('voided_at')
            ->whereDate('transaction_date', $dateStr);

        $openingQuery = CashierOpeningBalance::where('business_id', $businessId)
            ->whereDate('date', $dateStr);

        if ($branchId !== null) {
            $cfQuery->where('branch_id', $branchId);
            $depQuery->where('branch_id', $branchId);
            $txQuery->where('branch_id', $branchId);
            $openingQuery->where('branch_id', $branchId);
        } else {
            $context->applyTo($cfQuery, 'cash_flow', 'branch_id');
            $context->applyTo($depQuery, 'customer_deposit_logs', 'branch_id');
            $context->applyTo($txQuery, 'transactions', 'branch_id');
            $context->applyTo($openingQuery, 'cashier_opening_balances', 'branch_id');
        }

        $allCf = $cfQuery->get();
        $allDep = $depQuery->get();
        $allTx = $txQuery->get();
        $allOpening = $openingQuery->get();

        // 1. Breakdown Per Cabang
        $branchesQuery = Branch::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name');

        if ($branchId !== null) {
            $branchesQuery->where('id', $branchId);
        } elseif ($context->isBranch() && $context->getBranchId() !== null) {
            $branchesQuery->where('id', $context->getBranchId());
        }
        $branches = $branchesQuery->get();

        $cfByBranch = $allCf->groupBy('branch_id');
        $depByBranch = $allDep->groupBy('branch_id');
        $txByBranch = $allTx->groupBy('branch_id');
        $openingByBranch = $allOpening->groupBy('branch_id');

        $byBranch = [];
        foreach ($branches as $b) {
            $bOpening = (float) $openingByBranch->get($b->id, collect())->sum('amount');
            $sum = $this->buildFromCollections(
                $cfByBranch->get($b->id, collect()),
                $depByBranch->get($b->id, collect()),
                $txByBranch->get($b->id, collect()),
                $bOpening
            );

            $byBranch[] = [
                'branch_id'      => $b->id,
                'branch_name'    => $b->name,
                'branch_code'    => $b->code,
                'is_main'        => (bool) $b->is_main,
                'omset'          => $sum['sales_kasbon']['omset_today'],
                'cash_in'        => $sum['rows']['inflow_gross']['cash'],
                'dana_in'        => $sum['rows']['inflow_gross']['dana'],
                'transfer_in'    => $sum['rows']['inflow_gross']['transfer'],
                'deposit_in'     => $sum['deposit']['in_total'],
                'deposit_used'   => $sum['deposit']['used_total'],
                'refunds'        => $sum['rows']['refunds']['total_money'],
                'inflow_net'     => $sum['rows']['inflow_net']['total_money'],
                'kasbon_new'     => $sum['sales_kasbon']['kasbon_new_today'],
                'drawer_out'     => $sum['drawer']['out_total'],
                'drawer_balance' => $sum['drawer']['expected_drawer'],
            ];
        }

        // 2. Breakdown Per Kasir / User yang punya aktivitas
        $userIds = $allCf->pluck('created_by')
            ->merge($allDep->pluck('created_by'))
            ->merge($allTx->pluck('created_by'))
            ->merge($allOpening->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();

        $users = User::with('branch')
            ->where('business_id', $businessId)
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get();

        $cfByUser = $allCf->groupBy('created_by');
        $depByUser = $allDep->groupBy('created_by');
        $txByUser = $allTx->groupBy('created_by');
        $openingByUser = $allOpening->groupBy('user_id');

        $branchNameById = Branch::where('business_id', $businessId)->pluck('name', 'id')->all();

        $byCashier = [];
        foreach ($users as $u) {
            $uCf = $cfByUser->get($u->id, collect());
            $uDep = $depByUser->get($u->id, collect());
            $uTx = $txByUser->get($u->id, collect());
            $uOpenRows = $openingByUser->get($u->id, collect());
            $uOpening = (float) $uOpenRows->sum('amount');
            $sum = $this->buildFromCollections($uCf, $uDep, $uTx, $uOpening);

            // Tampilkan cabang tempat transaksi aktual kasir pada tanggal ini (sinkron dengan tab Per Cabang)
            $actualBranchNames = $uCf->pluck('branch_id')
                ->merge($uDep->pluck('branch_id'))
                ->merge($uTx->pluck('branch_id'))
                ->merge($uOpenRows->pluck('branch_id'))
                ->filter()
                ->unique()
                ->map(fn ($id) => $branchNameById[$id] ?? null)
                ->filter()
                ->values()
                ->all();

            $displayBranchName = !empty($actualBranchNames)
                ? implode(', ', $actualBranchNames)
                : ($u->branch->name ?? '-');

            $byCashier[] = [
                'user_id'        => $u->id,
                'name'           => $u->name,
                'role'           => $u->role,
                'branch_id'      => $u->branch_id,
                'branch_name'    => $displayBranchName,
                'omset'          => $sum['sales_kasbon']['omset_today'],
                'cash_in'        => $sum['rows']['inflow_gross']['cash'],
                'dana_in'        => $sum['rows']['inflow_gross']['dana'],
                'transfer_in'    => $sum['rows']['inflow_gross']['transfer'],
                'deposit_in'     => $sum['deposit']['in_total'],
                'deposit_used'   => $sum['deposit']['used_total'],
                'refunds'        => $sum['rows']['refunds']['total_money'],
                'inflow_net'     => $sum['rows']['inflow_net']['total_money'],
                'kasbon_new'     => $sum['sales_kasbon']['kasbon_new_today'],
                'drawer_out'     => $sum['drawer']['out_total'],
                'drawer_balance' => $sum['drawer']['expected_drawer'],
            ];
        }

        usort($byCashier, fn ($a, $b) => $b['inflow_net'] <=> $a['inflow_net']);

        return [
            'by_branch'  => $byBranch,
            'by_cashier' => $byCashier,
        ];
    }

    /**
     * Ambil daftar item transaksi/mutasi penyusun suatu baris matriks (AJAX Drilldown).
     */
    public function getDrilldownItems(
        int $businessId,
        Carbon|string $date,
        string $category,
        ?string $channel = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): array {
        $targetDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateStr = $targetDate->toDateString();
        $context = app(BranchContext::class);
        $items = [];

        $branchMap = Branch::where('business_id', $businessId)->pluck('name', 'id')->all();

        $applyBranch = function ($query, string $table) use ($branchId, $context) {
            if ($branchId !== null) {
                $query->where("{$table}.branch_id", $branchId);
            } else {
                $context->applyTo($query, $table, 'branch_id');
            }
        };

        $matchesChannel = function (?string $pm) use ($channel): bool {
            if ($channel === null || $channel === '' || $channel === 'all') {
                return true;
            }
            $ch = self::classifyChannel($pm);
            if ($channel === 'other') {
                return !in_array($ch, ['cash', 'dana', 'transfer', 'deposit'], true);
            }
            return $ch === $channel;
        };

        // 1. Kategori berbasis Transaction (Kasbon Baru Hari Ini)
        if ($category === 'kasbon_new') {
            $txQ = Transaction::withoutGlobalScope('branch')
                ->with(['customer', 'createdBy'])
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
                ->whereDate('transaction_date', $dateStr)
                ->orderByDesc('transaction_date');

            $applyBranch($txQ, 'transactions');
            if ($cashierId !== null) {
                $txQ->where('created_by', $cashierId);
            }

            foreach ($txQ->get() as $tx) {
                $items[] = [
                    'time'           => $tx->transaction_date ? $tx->transaction_date->format('H:i') : '-',
                    'code'           => '#' . str_pad((string) $tx->id, 6, '0', STR_PAD_LEFT),
                    'type_label'     => 'Kasbon Baru',
                    'customer_name'  => $tx->customer->name ?? 'Pelanggan Umum',
                    'cashier_name'   => $tx->createdBy->name ?? '-',
                    'branch_name'    => $branchMap[$tx->branch_id] ?? '-',
                    'payment_method' => 'KASBON',
                    'amount'         => (float) $tx->effective_payable_amount,
                    'sign'           => '',
                    'description'    => $tx->notes ?: 'Transaksi tempo belum lunas',
                    'affects_drawer' => false,
                ];
            }

            return $items;
        }

        // 2. Kategori berbasis CashFlow (income / expense)
        if (in_array($category, ['sale_direct', 'debt_settlement', 'customer_deposit', 'inflow_gross', 'refunds', 'drawer_out', 'non_drawer_out'], true) && $channel !== 'deposit') {
            $cfQ = CashFlow::withoutGlobalScope('branch')
                ->with(['createdBy', 'category', 'transaction.customer'])
                ->where('business_id', $businessId)
                ->whereDate('date', $dateStr)
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            $applyBranch($cfQ, 'cash_flow');
            if ($cashierId !== null) {
                $cfQ->where('created_by', $cashierId);
            }

            if (in_array($category, ['sale_direct', 'debt_settlement', 'customer_deposit', 'inflow_gross'], true)) {
                $cfQ->where('type', 'income');
            } else {
                $cfQ->where('type', 'expense');
            }

            foreach ($cfQ->get() as $cf) {
                $amt = (float) ($cf->amount ?? 0);
                if ($amt <= 0) {
                    continue;
                }

                $ch = self::classifyChannel($cf->payment_method);
                $moneyCh = in_array($ch, ['cash', 'dana', 'transfer'], true) ? $ch : 'other';
                $affectsDrawer = (bool) ($cf->affects_drawer ?? false);
                $ft = $cf->flow_type ?? ($cf->type === 'income' ? 'sale_direct' : 'expense');

                if ($cf->type === 'income') {
                    $bucket = self::resolveIncomeBucket($cf);
                    if ($category !== 'inflow_gross' && $bucket !== $category) {
                        continue;
                    }
                    if (!$matchesChannel($cf->payment_method)) {
                        continue;
                    }
                } else {
                    if ($category === 'refunds') {
                        if (!in_array($ft, ['cash_refund', 'deposit_refund'], true)) {
                            continue;
                        }
                        if (!$matchesChannel($cf->payment_method)) {
                            continue;
                        }
                    } elseif ($category === 'drawer_out') {
                        $isDrawerFlow = $affectsDrawer
                            && $moneyCh === 'cash'
                            && in_array($ft, ['expense', 'stock_purchase', 'cash_handover', 'cash_refund', 'deposit_refund'], true);
                        if (!$isDrawerFlow) {
                            continue;
                        }
                    } elseif ($category === 'non_drawer_out') {
                        $isDrawerFlow = $affectsDrawer
                            && $moneyCh === 'cash'
                            && in_array($ft, ['expense', 'stock_purchase', 'cash_handover', 'cash_refund', 'deposit_refund'], true);
                        if ($isDrawerFlow) {
                            continue;
                        }
                    }
                }

                $custName = optional(optional($cf->transaction)->customer)->name ?? '-';
                $code = $cf->reference_id ? '#' . str_pad((string) $cf->reference_id, 6, '0', STR_PAD_LEFT) : 'CF-' . $cf->id;

                $items[] = [
                    'time'           => $cf->created_at ? $cf->created_at->timezone('Asia/Jakarta')->format('H:i') : '-',
                    'code'           => $code,
                    'type_label'     => $cf->flow_type_label,
                    'customer_name'  => $custName,
                    'cashier_name'   => $cf->createdBy->name ?? '-',
                    'branch_name'    => $branchMap[$cf->branch_id] ?? '-',
                    'payment_method' => strtoupper(str_replace('-', ' ', (string) ($cf->payment_method ?: '-'))),
                    'amount'         => $amt,
                    'sign'           => $cf->type === 'income' ? '+' : '-',
                    'description'    => $cf->description ?: '-',
                    'affects_drawer' => $affectsDrawer,
                ];
            }
        }

        // 3. Pemakaian Saldo Deposit (CustomerDepositLog type = debit)
        if (
            in_array($category, ['deposit_used', 'sale_direct', 'debt_settlement'], true)
            && ($channel === null || $channel === '' || $channel === 'all' || $channel === 'deposit')
        ) {
            $depQ = CustomerDepositLog::with(['customer', 'createdBy'])
                ->where('business_id', $businessId)
                ->where('type', 'debit')
                ->whereDate('created_at', $dateStr)
                ->where(function ($q) {
                    $q->whereNull('transaction_id')
                        ->orWhereHas('transaction', function ($tq) {
                            $tq->withoutGlobalScopes()
                                ->where('status', '!=', 'cancelled')
                                ->whereNull('voided_at');
                        });
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            $applyBranch($depQ, 'customer_deposit_logs');
            if ($cashierId !== null) {
                $depQ->where('created_by', $cashierId);
            }

            foreach ($depQ->get() as $log) {
                $isSettlement = stripos((string) ($log->notes ?? ''), 'Pelunasan') !== false;
                if ($category === 'sale_direct' && $isSettlement) {
                    continue;
                }
                if ($category === 'debt_settlement' && !$isSettlement) {
                    continue;
                }

                $items[] = [
                    'time'           => $log->created_at ? $log->created_at->timezone('Asia/Jakarta')->format('H:i') : '-',
                    'code'           => $log->transaction_id ? '#' . str_pad((string) $log->transaction_id, 6, '0', STR_PAD_LEFT) : 'DEP-' . $log->id,
                    'type_label'     => $isSettlement ? 'Potong Deposit (Pelunasan)' : 'Potong Deposit (Penjualan)',
                    'customer_name'  => $log->customer->name ?? '-',
                    'cashier_name'   => $log->createdBy->name ?? '-',
                    'branch_name'    => $branchMap[$log->branch_id] ?? '-',
                    'payment_method' => 'POTONG DEPOSIT',
                    'amount'         => (float) $log->amount,
                    'sign'           => '',
                    'description'    => $log->notes ?: 'Pemakaian saldo deposit pelanggan',
                    'affects_drawer' => false,
                ];
            }
        }

        return $items;
    }
}
