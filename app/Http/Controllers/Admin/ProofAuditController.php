<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ProofAuditController extends Controller
{
    /**
     * Menampilkan Halaman Pusat Audit Bukti Kasir:
     * - Bukti Nota Pengeluaran (Expenses)
     * - Bukti Pembayaran / Transfer Non-Tunai WA (Pelunasan Piutang / Kasbon)
     * Mendukung filter Hari Ini, Kemarin, dan Hari Sebelum-sebelumnya.
     */
    public function index(Request $request): View
    {
        $businessId = Auth::user()->business_id;

        // 1. Tentukan rentang tanggal filter
        $quickDate = $request->input('quick_date', 'today');
        $today = Carbon::today();

        if ($quickDate === 'yesterday') {
            $startDate = Carbon::yesterday()->format('Y-m-d');
            $endDate = Carbon::yesterday()->format('Y-m-d');
        } elseif ($quickDate === 'last_7_days') {
            $startDate = Carbon::today()->subDays(6)->format('Y-m-d');
            $endDate = Carbon::today()->format('Y-m-d');
        } elseif ($quickDate === 'custom' || $request->filled('start_date')) {
            $startDate = $request->input('start_date', $today->format('Y-m-d'));
            $endDate = $request->input('end_date', $startDate);
        } else {
            // Default Hari Ini
            $startDate = $today->format('Y-m-d');
            $endDate = $today->format('Y-m-d');
            $quickDate = 'today';
        }

        $activeTab = $request->input('tab', 'all');

        // 2. Query Bukti Pengeluaran (Expenses)
        $expenseQuery = CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['category', 'createdBy', 'proofVerifiedBy']);

        // Filter opsi bukti pengeluaran jika dipilih
        if ($request->input('expense_filter') === 'with_proof') {
            $expenseQuery->whereNotNull('receipt_image');
        } elseif ($request->input('expense_filter') === 'without_proof') {
            $expenseQuery->whereNull('receipt_image');
        } elseif ($request->input('expense_filter') === 'unverified') {
            $expenseQuery->whereNull('proof_verified_at');
        }

        $expenses = $expenseQuery->latest('date')->latest('id')->paginate(15, ['*'], 'expenses_page')->withQueryString();

        // 3. Query Bukti Pelunasan Non-Tunai (Transactions)
        $viewMode = $request->input('view_mode', 'grouped');

        if ($viewMode === 'grouped') {
            // =========================================================================
            // MODE RANGKUMAN PER TRANSFER (RINGKAS - DEFAULT):
            // Transaksi yang dilunasi dengan 1 bukti transfer WA (bulk) dikelompokkan
            // menjadi 1 baris utama sesuai mutasi rekening, dengan accordion rincian nota.
            // =========================================================================
            $innerQuery = Transaction::where('business_id', $businessId)
                ->where('payment_status', 'paid')
                ->where('payment_method', '!=', 'cash')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('transaction_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                      ->orWhereBetween('updated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                });

            if ($request->input('payment_filter') === 'with_proof') {
                $innerQuery->whereNotNull('payment_proof');
            } elseif ($request->input('payment_filter') === 'without_proof') {
                $innerQuery->whereNull('payment_proof');
            } elseif ($request->input('payment_filter') === 'unverified') {
                $innerQuery->whereNull('proof_verified_at');
            }

            $innerQuery->select([
                'id',
                'total_amount',
                'updated_at',
                'proof_verified_at',
                \Illuminate\Support\Facades\DB::raw("CASE WHEN payment_proof IS NOT NULL AND payment_proof != '' THEN payment_proof ELSE CONCAT('single_', id) END as batch_key")
            ]);

            $payments = \Illuminate\Support\Facades\DB::table($innerQuery, 't')
                ->select([
                    't.batch_key',
                    \Illuminate\Support\Facades\DB::raw("MAX(t.id) as latest_id"),
                    \Illuminate\Support\Facades\DB::raw("COUNT(t.id) as batch_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(t.total_amount) as total_batch_amount"),
                    \Illuminate\Support\Facades\DB::raw("MAX(t.updated_at) as batch_updated_at"),
                    \Illuminate\Support\Facades\DB::raw("MIN(t.proof_verified_at) as batch_verified_at")
                ])
                ->groupBy('t.batch_key')
                ->orderByDesc('batch_updated_at')
                ->paginate(15, ['*'], 'payments_page')
                ->withQueryString();

            // Eager-load seluruh detail transaksi untuk batch pada halaman ini
            $batchItems = collect($payments->items());
            $proofPaths = $batchItems->pluck('batch_key')->filter(fn($k) => !str_starts_with($k, 'single_'))->values();
            $singleIds = $batchItems->pluck('batch_key')->filter(fn($k) => str_starts_with($k, 'single_'))->map(fn($k) => (int)substr($k, 7))->values();

            $loadedTransactions = collect();
            if ($proofPaths->isNotEmpty() || $singleIds->isNotEmpty()) {
                $loadedTransactions = Transaction::where('business_id', $businessId)
                    ->where(function($q) use ($proofPaths, $singleIds) {
                        if ($proofPaths->isNotEmpty()) {
                            $q->whereIn('payment_proof', $proofPaths);
                        }
                        if ($singleIds->isNotEmpty()) {
                            $q->orWhereIn('id', $singleIds);
                        }
                    })
                    ->with(['customer', 'createdBy', 'proofVerifiedBy'])
                    ->orderBy('id', 'asc')
                    ->get()
                    ->groupBy(function($item) {
                        return (!empty($item->payment_proof)) ? $item->payment_proof : ('single_' . $item->id);
                    });
            }

            $bulkGroups = $loadedTransactions;
        } else {
            // Mode Rinci per Nota Satuan
            $txQuery = Transaction::where('business_id', $businessId)
                ->where('payment_status', 'paid')
                ->where('payment_method', '!=', 'cash')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('transaction_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                      ->orWhereBetween('updated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                })
                ->with(['customer', 'createdBy', 'proofVerifiedBy']);

            if ($request->input('payment_filter') === 'with_proof') {
                $txQuery->whereNotNull('payment_proof');
            } elseif ($request->input('payment_filter') === 'without_proof') {
                $txQuery->whereNull('payment_proof');
            } elseif ($request->input('payment_filter') === 'unverified') {
                $txQuery->whereNull('proof_verified_at');
            }

            $payments = $txQuery->latest('updated_at')->paginate(15, ['*'], 'payments_page')->withQueryString();

            $proofPaths = $payments->pluck('payment_proof')->filter()->unique()->values();
            $bulkGroups = collect();
            if ($proofPaths->isNotEmpty()) {
                $bulkTransactions = Transaction::where('business_id', $businessId)
                    ->whereIn('payment_proof', $proofPaths)
                    ->with(['customer', 'createdBy'])
                    ->orderBy('id', 'asc')
                    ->get();

                $bulkGroups = $bulkTransactions->groupBy('payment_proof');
            }
            $loadedTransactions = $bulkGroups;
        }

        // 4. Ringkasan Statistik untuk Banner/Dashboard Audit
        $hasReceiptCol = Schema::hasColumn('cash_flow', 'receipt_image');
        $hasProofCol = Schema::hasColumn('transactions', 'payment_proof');

        $totalExpensesAmount = CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $totalExpensesCount = CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        $expensesWithProofCount = $hasReceiptCol ? CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('receipt_image')
            ->count() : 0;

        $totalPaymentsAmount = Transaction::where('business_id', $businessId)
            ->where('payment_status', 'paid')
            ->where('payment_method', '!=', 'cash')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('transaction_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                  ->orWhereBetween('updated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->sum('total_amount');

        $totalPaymentsCount = Transaction::where('business_id', $businessId)
            ->where('payment_status', 'paid')
            ->where('payment_method', '!=', 'cash')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('transaction_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                  ->orWhereBetween('updated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->count();

        $totalPaymentBatchesCount = Transaction::where('business_id', $businessId)
            ->where('payment_status', 'paid')
            ->where('payment_method', '!=', 'cash')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('transaction_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                  ->orWhereBetween('updated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->distinct()
            ->count(\Illuminate\Support\Facades\DB::raw("CASE WHEN payment_proof IS NOT NULL AND payment_proof != '' THEN payment_proof ELSE CONCAT('single_', id) END"));

        $paymentsWithProofCount = $hasProofCol ? Transaction::where('business_id', $businessId)
            ->where('payment_status', 'paid')
            ->where('payment_method', '!=', 'cash')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('transaction_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                  ->orWhereBetween('updated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->whereNotNull('payment_proof')
            ->count() : 0;

        $unverifiedTotal = 0;
        if (Schema::hasColumn('cash_flow', 'proof_verified_at')) {
            $unverifiedTotal += CashFlow::where('business_id', $businessId)
                ->where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->whereNotNull('receipt_image')
                ->whereNull('proof_verified_at')
                ->count();
        }
        if (Schema::hasColumn('transactions', 'proof_verified_at')) {
            $unverifiedTotal += Transaction::where('business_id', $businessId)
                ->where('payment_status', 'paid')
                ->where('payment_method', '!=', 'cash')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('transaction_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                      ->orWhereBetween('updated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                })
                ->whereNotNull('payment_proof')
                ->whereNull('proof_verified_at')
                ->distinct()
                ->count(\Illuminate\Support\Facades\DB::raw("CASE WHEN payment_proof IS NOT NULL AND payment_proof != '' THEN payment_proof ELSE CONCAT('single_', id) END"));
        }

        return view('admin.proof_audit.index', compact(
            'expenses',
            'payments',
            'bulkGroups',
            'loadedTransactions',
            'viewMode',
            'startDate',
            'endDate',
            'quickDate',
            'activeTab',
            'totalExpensesAmount',
            'totalExpensesCount',
            'expensesWithProofCount',
            'totalPaymentsAmount',
            'totalPaymentsCount',
            'totalPaymentBatchesCount',
            'paymentsWithProofCount',
            'unverifiedTotal'
        ));
    }

    /**
     * Verifikasi bukti nota pengeluaran oleh Admin.
     */
    public function verifyExpense(CashFlow $expense): RedirectResponse
    {
        if ($expense->business_id !== Auth::user()->business_id) {
            abort(403);
        }

        $expense->update([
            'proof_verified_at' => now(),
            'proof_verified_by' => Auth::id(),
        ]);

        return back()->with('success', 'Bukti nota pengeluaran #' . $expense->id . ' berhasil diverifikasi.');
    }

    /**
     * Batalkan verifikasi bukti nota pengeluaran.
     */
    public function unverifyExpense(CashFlow $expense): RedirectResponse
    {
        if ($expense->business_id !== Auth::user()->business_id) {
            abort(403);
        }

        $expense->update([
            'proof_verified_at' => null,
            'proof_verified_by' => null,
        ]);

        return back()->with('success', 'Status verifikasi nota #' . $expense->id . ' telah dibatalkan.');
    }

    /**
     * Verifikasi bukti transfer pelunasan non-tunai oleh Admin (Cocok dengan Mutasi).
     * Jika transaksi merupakan bagian dari pelunasan bulk (berbagi payment_proof),
     * seluruh transaksi dalam batch tersebut diverifikasi sekaligus.
     */
    public function verifyPayment(Transaction $transaction): RedirectResponse
    {
        if ($transaction->business_id !== Auth::user()->business_id) {
            abort(403);
        }

        $businessId = Auth::user()->business_id;

        if (!empty($transaction->payment_proof)) {
            $count = Transaction::where('business_id', $businessId)
                ->where('payment_proof', $transaction->payment_proof)
                ->update([
                    'proof_verified_at' => now(),
                    'proof_verified_by' => Auth::id(),
                ]);

            if ($count > 1) {
                return back()->with('success', "Bukti transfer Bulk ({$count} transaksi) berhasil diverifikasi cocok mutasi.");
            }
        } else {
            $transaction->update([
                'proof_verified_at' => now(),
                'proof_verified_by' => Auth::id(),
            ]);
        }

        return back()->with('success', 'Bukti pembayaran transaksi #' . $transaction->id . ' berhasil diverifikasi sesuai mutasi.');
    }

    /**
     * Batalkan verifikasi bukti pembayaran.
     * Jika transaksi merupakan bagian dari pelunasan bulk, batalkan verifikasi seluruh batch.
     */
    public function unverifyPayment(Transaction $transaction): RedirectResponse
    {
        if ($transaction->business_id !== Auth::user()->business_id) {
            abort(403);
        }

        $businessId = Auth::user()->business_id;

        if (!empty($transaction->payment_proof)) {
            $count = Transaction::where('business_id', $businessId)
                ->where('payment_proof', $transaction->payment_proof)
                ->update([
                    'proof_verified_at' => null,
                    'proof_verified_by' => null,
                ]);

            if ($count > 1) {
                return back()->with('success', "Status verifikasi untuk seluruh {$count} transaksi pada bukti transfer Bulk ini telah dibatalkan.");
            }
        } else {
            $transaction->update([
                'proof_verified_at' => null,
                'proof_verified_by' => null,
            ]);
        }

        return back()->with('success', 'Status verifikasi transaksi #' . $transaction->id . ' telah dibatalkan.');
    }
}
