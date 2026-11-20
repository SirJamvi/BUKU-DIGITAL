<?php

namespace App\Services\Acs;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
use App\Models\Transaction;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcsCashDrawerSyncService
{
    /**
     * Mengambil ringkasan keuangan laci kasir per cabang untuk validasi checksheet ACS.
     * Mendukung filter tanggal spesifik atau rentang jam operasional shift sore (07:00 - 17:15 WIB).
     *
     * @param string|null $date Format YYYY-MM-DD (default: hari ini WIB)
     * @param string|null $branchCode Kode cabang (misal 'PUSAT', 'WARUNGBAMBU', atau null untuk semua cabang)
     * @param bool $shiftTimeFilter Jika true, membatasi transaksi pada jendela shift 07:00 - 17:15 WIB
     * @return array
     */
    public function getBranchDrawerSummary(?string $date = null, ?string $branchCode = null, bool $shiftTimeFilter = false): array
    {
        return app(BranchContext::class)->runUnscoped(function () use ($date, $branchCode, $shiftTimeFilter) {
            $targetDate = $date ? Carbon::parse($date, 'Asia/Jakarta')->toDateString() : Carbon::today('Asia/Jakarta')->toDateString();

            // Batas waktu shift sore operasional (07:00 - 17:15 WIB)
            $shiftStart = Carbon::parse($targetDate, 'Asia/Jakarta')->setTime(7, 0, 0);
            $shiftEnd = Carbon::parse($targetDate, 'Asia/Jakarta')->setTime(17, 15, 0);

            // Query cabang aktif
            $branchQuery = Branch::where('is_active', true);
            if ($branchCode) {
                $cleanCode = strtoupper(trim($branchCode));
                $branchQuery->where(function ($q) use ($cleanCode) {
                    $q->where('code', $cleanCode)
                      ->orWhere('code', 'CB-' . $cleanCode)
                      ->orWhere('name', 'LIKE', '%' . $cleanCode . '%')
                      ->orWhereRaw('LOWER(code) = ?', [strtolower($cleanCode)]);
                });
            }

            $branches = $branchQuery->orderByDesc('is_main')->orderBy('code')->get();

            $results = [];

            foreach ($branches as $branch) {
                // 1. MODAL AWAL LACI (Opening Cash / Petty Cash)
                $openingCashQuery = CashierOpeningBalance::where('branch_id', $branch->id)
                    ->whereDate('date', $targetDate);
                $openingCash = (float) $openingCashQuery->sum('amount');
                $openingRecordsCount = $openingCashQuery->count();

                // 2. PENJUALAN TUNAI LANGSUNG (Cash Sales)
                // Filter: Transaksi penjualan lunas dengan metode tunai/cash, tidak termasuk pelunasan kasbon
                $cashSalesQuery = Transaction::where('branch_id', $branch->id)
                    ->where('type', 'sale')
                    ->where('payment_status', 'paid')
                    ->whereRaw('LOWER(payment_method) = ?', ['cash']);

                if ($shiftTimeFilter) {
                    $cashSalesQuery->whereBetween('transaction_date', [$shiftStart, $shiftEnd]);
                } else {
                    $cashSalesQuery->whereDate('transaction_date', $targetDate);
                }

                // Dikecualikan transaksi yang ber-pelunasan (karena pelunasan masuk kategori Debt Collection)
                $cashSalesQuery->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('cash_flow')
                        ->whereColumn('cash_flow.reference_id', 'transactions.id')
                        ->where('cash_flow.description', 'LIKE', '%Pelunasan%');
                });

                $cashSalesAmount = (float) $cashSalesQuery->sum('total_amount');
                $cashSalesCount = $cashSalesQuery->count();

                // 3. PELUNASAN KASBON / PIUTANG TUNAI HARI INI (Cash Debt Collection)
                // Filter: Pemasukan cash_flow bertipe 'income' dengan metode 'cash' & keterangan 'Pelunasan'
                $debtCollectionQuery = CashFlow::where('branch_id', $branch->id)
                    ->where('type', 'income')
                    ->whereRaw('LOWER(payment_method) = ?', ['cash'])
                    ->where('description', 'LIKE', '%Pelunasan%')
                    ->where(function ($q) {
                        $q->where('affects_drawer', 1)
                          ->orWhereNull('affects_drawer');
                    });

                if ($shiftTimeFilter) {
                    $debtCollectionQuery->where(function ($q) use ($targetDate, $shiftStart, $shiftEnd) {
                        $q->whereBetween('created_at', [$shiftStart, $shiftEnd])
                          ->orWhere(function ($sub) use ($targetDate, $shiftStart, $shiftEnd) {
                              $sub->whereDate('date', $targetDate)
                                  ->whereBetween('created_at', [$shiftStart, $shiftEnd]);
                          });
                    });
                } else {
                    $debtCollectionQuery->whereDate('date', $targetDate);
                }

                $debtCollectionAmount = (float) $debtCollectionQuery->sum('amount');
                $debtCollectionCount = $debtCollectionQuery->count();

                // 4. PENGELUARAN KAS DARI LACI KASIR (Cash Expenses from Drawer)
                // Filter: Pengeluaran cash_flow bertipe 'expense' yang memotong laci kasir (bensin, makan, galon)
                $expensesQuery = CashFlow::where('branch_id', $branch->id)
                    ->where('type', 'expense')
                    ->where(function ($q) {
                        $q->whereRaw('LOWER(payment_method) = ?', ['cash'])
                          ->orWhereNull('payment_method');
                    })
                    ->where(function ($q) {
                        $q->where('affects_drawer', 1)
                          ->orWhere('funding_source', 'drawer')
                          ->orWhereNull('affects_drawer');
                    });

                if ($shiftTimeFilter) {
                    $expensesQuery->where(function ($q) use ($targetDate, $shiftStart, $shiftEnd) {
                        $q->whereBetween('created_at', [$shiftStart, $shiftEnd])
                          ->orWhere(function ($sub) use ($targetDate, $shiftStart, $shiftEnd) {
                              $sub->whereDate('date', $targetDate)
                                  ->whereBetween('created_at', [$shiftStart, $shiftEnd]);
                          });
                    });
                } else {
                    $expensesQuery->whereDate('date', $targetDate);
                }

                $expensesAmount = (float) $expensesQuery->sum('amount');
                $expensesCount = $expensesQuery->count();

                // 5. SALDO FISIK LACI KASIR YANG DIHARAPKAN (Expected Drawer Cash)
                $expectedDrawerCash = $openingCash + $cashSalesAmount + $debtCollectionAmount - $expensesAmount;

                $results[] = [
                    'date'                       => $targetDate,
                    'branch_id'                  => $branch->id,
                    'branch_code'                => $branch->code,
                    'branch_name'                => $branch->name,
                    'is_main'                    => (bool) $branch->is_main,
                    'opening_cash'               => round($openingCash, 2),
                    'total_cash_sales'           => round($cashSalesAmount, 2),
                    'total_cash_debt_collection' => round($debtCollectionAmount, 2),
                    'total_cash_expenses'        => round($expensesAmount, 2),
                    'expected_drawer_cash'       => round($expectedDrawerCash, 2),
                    'details'                    => [
                        'opening_records_count' => $openingRecordsCount,
                        'cash_sales_count'      => $cashSalesCount,
                        'debt_collection_count' => $debtCollectionCount,
                        'expenses_count'        => $expensesCount,
                        'shift_applied'         => $shiftTimeFilter ? '07:00:00 - 17:15:00 WIB' : 'Full Day',
                    ],
                ];
            }

            return $results;
        });
    }
}
