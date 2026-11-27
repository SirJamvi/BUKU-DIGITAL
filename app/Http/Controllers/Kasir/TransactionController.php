<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\BulkMarkAsPaidRequest;
use App\Http\Requests\Kasir\CompleteDeliveryTripRequest;
use App\Http\Requests\Kasir\StoreTransactionRequest;
use App\Models\CashFlow;
use App\Models\Transaction;
use App\Services\Kasir\TransactionService;
use App\Services\Accounting\CustomerDepositService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Carbon\Carbon;

class TransactionController extends Controller
{
    use AuthorizesRequests;

    /**
     * @var TransactionService
     */
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Menampilkan daftar transaksi.
     */
    public function index(): View
    {
        $kasirId = Auth::id();
        $transactions = $this->transactionService->getTransactionsByKasir($kasirId);

        return view('kasir.transactions.index', compact('transactions'));
    }

    /**
     * Menampilkan detail transaksi (struk).
     */
    public function show(Transaction $transaction): View
    {
        // Keamanan sudah ditangani oleh Global Scope
        $transactionDetails = $this->transactionService->getTransactionDetails($transaction);
        return view('kasir.pos.receipt', $transactionDetails);
    }

    /**
     * [BARU] Memproses pelunasan transaksi yang sebelumnya kasbon.
     */
    public function markAsPaid(\Illuminate\Http\Request $request, Transaction $transaction): RedirectResponse
    {
        // ==========================================================
        // PERBAIKAN #403: Ganti Policy authorize() dengan cek manual
        // Policy 'update' untuk Transaction belum diatur untuk role Kasir,
        // sehingga selalu ditolak (403). Kita cek langsung business_id-nya.
        // ==========================================================
        if ($transaction->business_id !== Auth::user()->business_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        $activeBranchId = app(\App\Support\BranchContext::class)->requireBranchId();
        $isCash = strtolower($request->payment_method ?? '') === 'cash';

        $request->validate([
            'payment_method'       => 'required|string',
            'settlement_date_mode' => 'nullable|string|in:today,nota_date',
            'settlement_notes'     => 'nullable|string|max:255',
            'cash_destination'     => 'nullable|string|in:drawer,owner',
            'payment_proof'        => ($isCash ? 'nullable' : 'required') . '|file|mimes:jpeg,png,jpg,webp|max:10240',
        ], [
            'payment_proof.required' => 'Bukti pembayaran / transfer WhatsApp wajib diunggah untuk metode pelunasan non-tunai (' . strtoupper($request->payment_method ?? '') . ').',
        ]);

        try {
            $user = Auth::user();
            $isAdmin = (method_exists($user, 'isAdmin') && $user->isAdmin()) || in_array($user->role ?? '', ['admin', 'owner']);
            $cashDestination = $request->cash_destination ?? 'drawer';

            $proofPath = null;
            if ($request->hasFile('payment_proof')) {
                $proofPath = \App\Services\ProofUploadService::uploadPaymentProof($request->file('payment_proof'));
            }

            // Tentukan tanggal buku pencatatan cash flow:
            // Jika Cash, selalu masuk kas hari ini (koreksi lupa update dikunci untuk uang tunai fisik)
            $settlementDate = (!$isCash && $request->settlement_date_mode === 'nota_date')
                ? \Carbon\Carbon::parse($transaction->transaction_date)->startOfDay()
                : now();

            $originBranchName = $transaction->branch ? $transaction->branch->name : 'Cabang Asal';
            $isCrossBranch = ((int) $transaction->branch_id !== (int) $activeBranchId);

            // 1. Update status transaksi
            $txUpdate = [
                'payment_status'            => 'paid',
                'initial_payment_method'    => $transaction->initial_payment_method ?: ($transaction->payment_method ?: 'kasbon'),
                'settlement_payment_method' => $request->payment_method,
                'settled_at'                => now(),
                'payment_method'            => $request->payment_method,
            ];
            if ($proofPath) {
                $txUpdate['payment_proof'] = $proofPath;
            }
            $transaction->update($txUpdate);

            // Kunci periode pembukuan bulanan
            \App\Services\Accounting\AccountingPeriodGuard::ensurePeriodOpen(
                (int) $transaction->business_id,
                $activeBranchId ? (int) $activeBranchId : null,
                $settlementDate
            );

            $notesPrefix = '[Pelunasan di ' . ($user->branch->name ?? 'Cabang Kasir');
            if ($isCrossBranch) {
                $notesPrefix .= ' (Nota Asal: ' . $originBranchName . ')';
            }
            if ($request->filled('settlement_notes')) {
                $notesPrefix .= ' - ' . $request->settlement_notes;
            }
            $notesPrefix .= ']';

            $transaction->update([
                'notes' => ($transaction->notes ? $transaction->notes . ' | ' : '') . $notesPrefix
            ]);

            // 2. Catat ke CashFlow (dengan alokasi tanggal pembukuan yang dipilih kasir)
            $desc = 'Pelunasan Transaksi Kasbon #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
            if ($isCrossBranch) {
                $desc .= ' [Asal: ' . $originBranchName . ']';
            }
            if ($request->filled('settlement_notes')) {
                $desc .= ' (' . $request->settlement_notes . ')';
            }
            if ($request->settlement_date_mode === 'nota_date') {
                $desc .= ' [Koreksi Nota Tgl ' . \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y') . ']';
            }

            if ($isAdmin) {
                $affectsDrawer = ($isCash && $cashDestination === 'drawer') ? 1 : 0;
                $creatorId = ($isCash && $cashDestination === 'drawer' && $transaction->created_by)
                    ? $transaction->created_by
                    : Auth::id();
            } else {
                $affectsDrawer = $isCash ? 1 : 0;
                $creatorId = Auth::id();
            }

            $incomeCat = \App\Models\ExpenseCategory::where('business_id', $transaction->business_id)
                ->where('type', 'income')
                ->first();
            $categoryId = $incomeCat ? $incomeCat->id : 1;

            $payable = (float) $transaction->effective_payable_amount;

            if ($payable > 0) {
                \App\Models\CashFlow::create([
                    'business_id'    => $transaction->business_id,
                    'branch_id'      => $activeBranchId, // Dicatat pada cabang kasir penerima dana
                    'type'           => 'income',
                    'flow_type'      => 'debt_settlement',
                    'reference_type' => 'transaction',
                    'category_id'    => $categoryId,
                    'payment_method' => $request->payment_method,
                    'funding_source' => $affectsDrawer ? 'drawer' : 'owner',
                    'affects_drawer' => $affectsDrawer,
                    'amount'         => $payable,
                    'description'    => $desc,
                    'date'           => $settlementDate,
                    'reference_id'   => $transaction->id,
                    'created_by'     => $creatorId,
                ]);
            }

            // Tambahkan akumulasi belanja customer jika transaksi sebelumnya pending
            if ($transaction->customer_id) {
                $customer = \App\Models\Customer::find($transaction->customer_id);
                if ($customer) {
                    $customer->increment('total_purchases', $transaction->total_amount);
                }
            }

            \App\Support\BranchCache::flush($transaction->business_id);

            return back()->with('success', 'Transaksi berhasil divalidasi dan dicatat sebagai Lunas.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memvalidasi pelunasan: ' . $e->getMessage());
        }
    }

    /**
     * [BARU] Menampilkan form untuk mengedit transaksi.
     */
    public function edit(Transaction $transaction): View
    {
        // Perbaikan #2: Otorisasi menggunakan Policy
        $this->authorize('update', $transaction);

        $data = $this->transactionService->getEditTransactionData($transaction);
        return view('kasir.transactions.edit', $data);
    }

    /**
     * [BARU] Memproses pembaruan transaksi.
     */
    public function update(StoreTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        // Perbaikan #2: Otorisasi menggunakan Policy
        $this->authorize('update', $transaction);

        try {
            $this->transactionService->updateTransaction($transaction, $request->validated());
            \App\Support\BranchCache::flush($transaction->business_id);
            return redirect()
                ->route('kasir.transactions.show', $transaction->id)
                ->with('success', 'Transaksi berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui transaksi: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * [BARU] Memproses pelunasan banyak transaksi (Bulk Payment) sekaligus.
     * Mendukung:
     * 1. Pelunasan penuh via Saldo Deposit pelanggan (ketat: saldo >= total nota terpilih)
     * 2. Pelunasan via Cash / Transfer Bank / DANA beserta kelebihan bayar masuk Deposit Pelanggan
     */
    public function bulkMarkAsPaid(BulkMarkAsPaidRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $paymentMethod = \Illuminate\Support\Str::slug($validated['payment_method']);
        $isCash = $paymentMethod === 'cash';
        $isDepositPayment = $paymentMethod === 'deposit';
        $settlementDateMode = $validated['settlement_date_mode'] ?? 'today';
        $settlementNotes = isset($validated['settlement_notes']) ? trim((string) $validated['settlement_notes']) : null;
        $depositOverpayment = $isDepositPayment ? 0.0 : round((float) ($validated['deposit_amount'] ?? 0), 2);

        $activeBranchId = app(\App\Support\BranchContext::class)->requireBranchId();
        $businessId = (int) Auth::user()->business_id;
        $transactionIds = array_values(array_unique(array_map('intval', $validated['transaction_ids'])));

        // Pastikan transaksi yang diubah benar-benar milik bisnis ini dan statusnya masih pending (lintas cabang diizinkan, kecualikan void)
        $transactions = Transaction::withoutGlobalScope('branch')
            ->with(['branch', 'customer'])
            ->whereIn('id', $transactionIds)
            ->where('business_id', $businessId)
            ->where('status', '!=', 'cancelled')
            ->whereNull('voided_at')
            ->where('payment_status', 'pending')
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($transactions->count() !== count($transactionIds)) {
            abort(404, 'Satu atau lebih transaksi bukan milik bisnis Anda atau sudah lunas.');
        }

        // Validasi kepemilikan pelanggan terdaftar jika memakai deposit atau menitipkan kelebihan deposit
        $customerIds = $transactions->pluck('customer_id')->unique()->values();
        $singleCustomerId = ($customerIds->count() === 1 && !empty($customerIds->first()))
            ? (int) $customerIds->first()
            : null;

        if ($isDepositPayment && !$singleCustomerId) {
            Log::warning('[DEPOSIT_DEBUG] KASIR_BULK_DEPOSIT_BLOCKED', [
                'kasir_id'        => Auth::id(),
                'reason'          => 'walkin_or_multi_customer',
                'transaction_ids' => $transactionIds,
            ]);
            return back()->with('error', 'Pelunasan menggunakan Saldo Deposit hanya berlaku untuk satu pelanggan terdaftar yang sama.');
        }

        if ($depositOverpayment > 0 && !$singleCustomerId) {
            Log::warning('[DEPOSIT_DEBUG] KASIR_BULK_DEPOSIT_BLOCKED', [
                'kasir_id'            => Auth::id(),
                'reason'              => 'overpay_walkin_or_multi_customer',
                'deposit_overpayment' => $depositOverpayment,
                'transaction_ids'     => $transactionIds,
            ]);
            return back()->with('error', 'Kelebihan bayar tidak dapat disimpan ke Saldo Deposit untuk Pelanggan Umum atau gabungan lintas pelanggan.');
        }

        if ($isDepositPayment || $depositOverpayment > 0) {
            Log::info('[DEPOSIT_DEBUG] KASIR_BULK_DEPOSIT_REQUEST', [
                'kasir_id'            => Auth::id(),
                'business_id'         => $businessId,
                'branch_id'           => $activeBranchId,
                'customer_id'         => $singleCustomerId,
                'payment_method'      => $paymentMethod,
                'is_deposit_payment'  => $isDepositPayment,
                'deposit_overpayment' => $depositOverpayment,
                'transaction_ids'     => $transactionIds,
            ]);
        }

        try {
            DB::beginTransaction();

            // =================================================================
            // JALUR A: PELUNASAN MENGGUNAKAN SALDO DEPOSIT PELANGGAN (KETAT)
            // =================================================================
            if ($isDepositPayment) {
                $lockedCustomer = \App\Models\Customer::where('business_id', $businessId)
                    ->where('id', $singleCustomerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $totalPayable = round((float) $transactions->sum(fn ($tx) => (float) $tx->effective_payable_amount), 2);
                $availableBalance = round((float) ($lockedCustomer->deposit_balance ?? 0.0), 2);

                if ($totalPayable > $availableBalance + 0.001) {
                    DB::rollBack();

                    Log::warning('[DEPOSIT_DEBUG] KASIR_BULK_DEPOSIT_BLOCKED', [
                        'kasir_id'          => Auth::id(),
                        'reason'            => 'insufficient_balance',
                        'customer_id'       => $singleCustomerId,
                        'total_payable'     => $totalPayable,
                        'available_balance' => $availableBalance,
                        'transaction_ids'   => $transactionIds,
                    ]);

                    return back()->with(
                        'error',
                        'Saldo deposit tidak mencukupi. Total ' . $transactions->count()
                        . ' nota terpilih (Rp ' . number_format($totalPayable, 0, ',', '.')
                        . ') melebihi saldo deposit (Rp ' . number_format($availableBalance, 0, ',', '.')
                        . '). Kurangi jumlah nota yang dicentang.'
                    );
                }

                \App\Services\Accounting\AccountingPeriodGuard::ensurePeriodOpen(
                    $businessId,
                    $activeBranchId ? (int) $activeBranchId : null,
                    now()
                );

                foreach ($transactions as $transaction) {
                    $payable = round((float) $transaction->effective_payable_amount, 2);

                    if ($payable > 0) {
                        $debitNote = 'Pelunasan Kasbon (Bulk) #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
                        if (!empty($settlementNotes)) {
                            $debitNote .= ' - ' . $settlementNotes;
                        }

                        $actualUsed = CustomerDepositService::useDeposit(
                            $businessId,
                            $activeBranchId,
                            $singleCustomerId,
                            $payable,
                            $transaction->id,
                            Auth::id(),
                            $debitNote
                        );

                        if ($actualUsed + 0.001 < $payable) {
                            throw new \RuntimeException(
                                'Saldo deposit tidak mencukupi saat memotong struk #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT) . '.'
                            );
                        }
                    }

                    $originBranchName = $transaction->branch ? $transaction->branch->name : 'Cabang Asal';
                    $isCrossBranch = ((int) $transaction->branch_id !== (int) $activeBranchId);

                    $notesPrefix = '[Pelunasan via Saldo Deposit di ' . (Auth::user()->branch->name ?? 'Cabang Kasir');
                    if ($isCrossBranch) {
                        $notesPrefix .= ' (Nota Asal: ' . $originBranchName . ')';
                    }
                    if (!empty($settlementNotes)) {
                        $notesPrefix .= ' - ' . $settlementNotes;
                    }
                    $notesPrefix .= ']';

                    $newDepositUsed = round((float) ($transaction->deposit_used ?? 0.0) + $payable, 2);

                    $transaction->update([
                        'deposit_used'              => $newDepositUsed,
                        'net_payable_amount'        => 0,
                        'payment_status'            => 'paid',
                        'initial_payment_method'    => $transaction->initial_payment_method ?: ($transaction->payment_method ?: 'kasbon'),
                        'settlement_payment_method' => 'deposit',
                        'settled_at'                => now(),
                        'payment_method'            => 'deposit',
                        'notes'                     => ($transaction->notes ? $transaction->notes . ' | ' : '') . $notesPrefix,
                    ]);

                    $lockedCustomer->increment('total_purchases', $transaction->total_amount);
                }

                $lockedCustomer->refresh();
                $remainingDeposit = (float) ($lockedCustomer->deposit_balance ?? 0.0);

                DB::commit();

                \App\Support\BranchCache::flush($businessId);

                return back()->with(
                    'success',
                    count($transactions) . ' Transaksi (Rp ' . number_format($totalPayable, 0, ',', '.')
                    . ') berhasil dilunasi menggunakan Saldo Deposit. Sisa saldo deposit: Rp '
                    . number_format($remainingDeposit, 0, ',', '.') . '.'
                );
            }

            // =================================================================
            // JALUR B: PELUNASAN CASH / TRANSFER BANK / DANA (+ OPSIONAL DEPOSIT)
            // =================================================================
            // 1 Bukti pembayaran yang diupload kasir diaplikasikan ke semua transaksi yang dicentang
            $proofPath = null;
            if ($request->hasFile('payment_proof')) {
                $proofPath = \App\Services\ProofUploadService::uploadPaymentProof($request->file('payment_proof'));
            }

            $incomeCat = \App\Models\ExpenseCategory::where('business_id', $businessId)
                ->where('type', 'income')
                ->first();
            $categoryId = $incomeCat ? $incomeCat->id : 1;

            $settledReceiptCodes = [];
            $latestSettlementDate = null;

            foreach ($transactions as $transaction) {
                // Tentukan tanggal buku pencatatan cash flow per transaksi:
                // Jika Cash, selalu masuk kas hari ini (koreksi lupa update dikunci)
                $settlementDate = (!$isCash && $settlementDateMode === 'nota_date')
                    ? Carbon::parse($transaction->transaction_date)->startOfDay()
                    : now();

                if ($latestSettlementDate === null || $settlementDate->greaterThan($latestSettlementDate)) {
                    $latestSettlementDate = $settlementDate;
                }

                // Kunci periode pembukuan bulanan
                \App\Services\Accounting\AccountingPeriodGuard::ensurePeriodOpen(
                    (int) $transaction->business_id,
                    $activeBranchId ? (int) $activeBranchId : null,
                    $settlementDate
                );

                $originBranchName = $transaction->branch ? $transaction->branch->name : 'Cabang Asal';
                $isCrossBranch = ((int) $transaction->branch_id !== (int) $activeBranchId);

                // Keterangan audit trail pelunasan
                $notesPrefix = '[Pelunasan di ' . (Auth::user()->branch->name ?? 'Cabang Kasir');
                if ($isCrossBranch) {
                    $notesPrefix .= ' (Nota Asal: ' . $originBranchName . ')';
                }
                if (!empty($settlementNotes)) {
                    $notesPrefix .= ' - ' . $settlementNotes;
                }
                $notesPrefix .= ']';

                // 1. Update status masing-masing transaksi beserta bukti pembayaran & catatan
                $txUpdate = [
                    'payment_status'            => 'paid',
                    'initial_payment_method'    => $transaction->initial_payment_method ?: ($transaction->payment_method ?: 'kasbon'),
                    'settlement_payment_method' => $paymentMethod,
                    'settled_at'                => now(),
                    'payment_method'            => $paymentMethod,
                    'notes'                     => ($transaction->notes ? $transaction->notes . ' | ' : '') . $notesPrefix,
                ];
                if ($proofPath) {
                    $txUpdate['payment_proof'] = $proofPath;
                }
                $transaction->update($txUpdate);

                // 2. Catat ke CashFlow untuk setiap transaksi agar laporan tercatat rapi
                // PENTING: Dicatat di branch_id kasir aktif yang menerima pembayaran ($activeBranchId)
                // sehingga uang fisik laci kasir (myNetCashToday) bertambah di kasir penerima!
                $receiptCode = '#' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
                $settledReceiptCodes[] = $receiptCode;

                $desc = 'Pelunasan Kasbon (Bulk) ' . $receiptCode;
                if ($isCrossBranch) {
                    $desc .= ' [Asal: ' . $originBranchName . ']';
                }
                if (!empty($settlementNotes)) {
                    $desc .= ' (' . $settlementNotes . ')';
                }
                if (!$isCash && $settlementDateMode === 'nota_date') {
                    $desc .= ' [Koreksi Nota Tgl ' . Carbon::parse($transaction->transaction_date)->format('d/m/Y') . ']';
                }

                $payable = (float) $transaction->effective_payable_amount;

                if ($payable > 0) {
                    CashFlow::create([
                        'business_id'    => $transaction->business_id,
                        'branch_id'      => $activeBranchId, // Laci kasir penerima
                        'type'           => 'income',
                        'flow_type'      => 'debt_settlement',
                        'reference_type' => 'transaction',
                        'category_id'    => $categoryId,
                        'payment_method' => $paymentMethod,
                        'funding_source' => $isCash ? 'drawer' : 'owner',
                        'affects_drawer' => $isCash ? 1 : 0,
                        'amount'         => $payable,
                        'description'    => $desc,
                        'date'           => $settlementDate,
                        'reference_id'   => $transaction->id,
                        'created_by'     => Auth::id(),
                    ]);
                }

                // Tambahkan akumulasi belanja customer
                if ($transaction->customer_id) {
                    \App\Models\Customer::where('id', $transaction->customer_id)
                        ->increment('total_purchases', $transaction->total_amount);
                }
            }

            // 3. Simpan kelebihan bayar ke Saldo Deposit Pelanggan jika kasir mengisinya
            if ($depositOverpayment > 0 && $singleCustomerId) {
                $depositDate = (!$isCash && $settlementDateMode === 'nota_date' && $latestSettlementDate)
                    ? $latestSettlementDate
                    : now();
                $refTransactionId = $transactions->count() === 1 ? $transactions->first()->id : null;
                $depositNote = 'Deposit kelebihan bayar pelunasan kasbon (' . implode(', ', $settledReceiptCodes) . ')';
                if (!empty($settlementNotes)) {
                    $depositNote .= ' - ' . $settlementNotes;
                }

                CustomerDepositService::addDeposit(
                    $businessId,
                    $activeBranchId,
                    $singleCustomerId,
                    $depositOverpayment,
                    $paymentMethod,
                    $refTransactionId,
                    $depositNote,
                    Auth::id(),
                    $depositDate
                );
            }

            DB::commit();

            \App\Support\BranchCache::flush($businessId);

            if ($isDepositPayment || $depositOverpayment > 0) {
                Log::info('[DEPOSIT_DEBUG] KASIR_BULK_DEPOSIT_SUCCESS', [
                    'kasir_id'            => Auth::id(),
                    'customer_id'         => $singleCustomerId,
                    'payment_method'      => $paymentMethod,
                    'is_deposit_payment'  => $isDepositPayment,
                    'deposit_overpayment' => $depositOverpayment,
                    'transaction_count'   => $transactions->count(),
                ]);
            }

            if ($depositOverpayment > 0) {
                return back()->with(
                    'success',
                    count($transactions) . ' Transaksi berhasil dilunasi, dan kelebihan bayar sebesar Rp '
                    . number_format($depositOverpayment, 0, ',', '.')
                    . ' otomatis disimpan ke Saldo Deposit pelanggan.'
                );
            }

            return back()->with('success', count($transactions) . ' Transaksi berhasil dilunasi secara bersamaan.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[DEPOSIT_DEBUG] KASIR_BULK_DEPOSIT_ERROR', [
                'kasir_id'        => Auth::id(),
                'payment_method'  => $paymentMethod,
                'transaction_ids' => $transactionIds,
                'exception'       => get_class($e),
                'message'         => $e->getMessage(),
                'location'        => $e->getFile() . ':' . $e->getLine(),
                'trace'           => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ]);
            return back()->with('error', 'Gagal memproses pelunasan massal: ' . $e->getMessage());
        }
    }

    /**
     * [BARU] Memproses penyelesaian pengiriman driver kolektif dengan real payment per transaksi,
     * pelunasan kasbon sebelumnya (FIFO), bukti transfer non-tunai, dan kelebihan bayar ke deposit.
     */
    public function completeDeliveryTrip(CompleteDeliveryTripRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $businessId = (int) Auth::user()->business_id;
        $tripCode = $validated['trip_code'];

        $transactions = Transaction::with(['customer', 'branch'])
            ->where('business_id', $businessId)
            ->where('delivery_trip_code', $tripCode)
            ->where('status', '!=', 'cancelled')
            ->whereNull('voided_at')
            ->get();

        if ($transactions->isEmpty()) {
            return back()->with('error', 'Tidak ditemukan transaksi valid untuk nomor pengiriman ini.');
        }

        $activeBranchId = app(\App\Support\BranchContext::class)->getBranchId()
            ?: (int) $transactions->first()->branch_id;
        $activeBranchName = Auth::user()->branch->name ?? 'Cabang Kasir';

        $incomeCat = \App\Models\ExpenseCategory::where('business_id', $businessId)
            ->where('type', 'income')
            ->first();
        $categoryId = $incomeCat ? $incomeCat->id : 1;

        $uploadedProofPaths = [];

        try {
            DB::beginTransaction();

            $paidCount = 0;
            $kasbonCount = 0;
            $oldKasbonSettledCount = 0;
            $oldKasbonSettledAmount = 0.0;
            $newDepositTotal = 0.0;
            $alreadySettledOldIds = [];

            foreach ($transactions as $transaction) {
                $orderNote = $validated['notes'][$transaction->id] ?? null;

                // 1. Cek jika transaksi sudah lunas 100% dari deposit saat checkout POS
                if ($transaction->payment_status === 'paid' && $transaction->effective_payable_amount <= 0) {
                    $upd = [
                        'order_status'    => 'delivered',
                        'delivery_status' => 'delivered',
                        'delivered_at'    => now(),
                    ];
                    if (!empty($orderNote)) {
                        $upd['notes'] = ($transaction->notes ? $transaction->notes . ' | ' : '') . $orderNote;
                    }
                    $transaction->update($upd);
                    $paidCount++;

                    // Cek jika kasir menginput titipan deposit tambahan via driver
                    $depositAmount = isset($validated['deposits'][$transaction->id])
                        ? round((float) $validated['deposits'][$transaction->id], 2)
                        : 0.0;
                    if ($depositAmount > 0 && $transaction->customer_id) {
                        CustomerDepositService::addDeposit(
                            $businessId,
                            $activeBranchId,
                            $transaction->customer_id,
                            $depositAmount,
                            'cash',
                            $transaction->id,
                            "Deposit kelebihan bayar pengiriman {$tripCode} #{$transaction->id}",
                            Auth::id()
                        );
                        $newDepositTotal = round($newDepositTotal + $depositAmount, 2);
                    }
                    continue;
                }

                $chosenMethod = $validated['payments'][$transaction->id] ?? 'kasbon';
                $isExplicitKasbon = strtolower($chosenMethod) === 'kasbon';
                $methodSlug = $isExplicitKasbon ? 'kasbon' : \Illuminate\Support\Str::slug($chosenMethod);
                $isCashChosen = $methodSlug === 'cash';
                $dateMode = $validated['settlement_date_modes'][$transaction->id] ?? 'today';

                $isOrderSettled = !$isExplicitKasbon
                    && ((string) ($validated['order_settled'][$transaction->id] ?? '1') === '1');

                $rawOldIds = $isExplicitKasbon
                    ? []
                    : (array) ($validated['settled_kasbons'][$transaction->id] ?? []);
                $requestedOldIds = array_values(array_diff(
                    array_unique(array_filter(array_map('intval', $rawOldIds))),
                    $alreadySettledOldIds
                ));

                $depositAmount = (!$isExplicitKasbon && isset($validated['deposits'][$transaction->id]))
                    ? round((float) $validated['deposits'][$transaction->id], 2)
                    : 0.0;

                // Upload bukti pembayaran non-tunai (1 file untuk order ini + kasbon lama + kelebihan deposit pada kartu ini)
                $proofPath = null;
                if (!$isExplicitKasbon && !$isCashChosen && $request->hasFile("payment_proofs.{$transaction->id}")) {
                    $proofPath = \App\Services\ProofUploadService::uploadPaymentProof(
                        $request->file("payment_proofs.{$transaction->id}")
                    );
                    $uploadedProofPaths[] = $proofPath;
                }

                // 2A. Pelunasan Nota Kasbon Sebelumnya (FIFO / Dipilih Kasir)
                if (!empty($requestedOldIds)) {
                    if (!$transaction->customer_id) {
                        throw new \RuntimeException('Pelunasan kasbon sebelumnya hanya berlaku untuk pelanggan terdaftar.');
                    }

                    $oldKasbons = Transaction::withoutGlobalScope('branch')
                        ->with(['branch', 'customer'])
                        ->whereIn('id', $requestedOldIds)
                        ->where('business_id', $businessId)
                        ->where('customer_id', $transaction->customer_id)
                        ->where('id', '!=', $transaction->id)
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
                        ->lockForUpdate()
                        ->orderBy('transaction_date', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();

                    if ($oldKasbons->count() !== count($requestedOldIds)) {
                        throw new \RuntimeException(
                            'Satu atau lebih nota kasbon sebelumnya sudah dilunasi atau berubah status. Silakan muat ulang halaman agar perhitungan kas tetap akurat.'
                        );
                    }

                    foreach ($oldKasbons as $oldKb) {
                        $kbDate = (!$isCashChosen && $dateMode === 'nota_date')
                            ? Carbon::parse($oldKb->transaction_date)->startOfDay()
                            : now();

                        \App\Services\Accounting\AccountingPeriodGuard::ensurePeriodOpen(
                            (int) $oldKb->business_id,
                            $activeBranchId ? (int) $activeBranchId : null,
                            $kbDate
                        );

                        $originBranchName = $oldKb->branch ? $oldKb->branch->name : 'Cabang Asal';
                        $isCrossBranch = ((int) $oldKb->branch_id !== (int) $activeBranchId);

                        $kbNotePrefix = "[Pelunasan Sekalian Pengiriman {$tripCode} di {$activeBranchName}";
                        if ($isCrossBranch) {
                            $kbNotePrefix .= " (Nota Asal: {$originBranchName})";
                        }
                        if (!empty($orderNote)) {
                            $kbNotePrefix .= " - {$orderNote}";
                        }
                        $kbNotePrefix .= ']';

                        $kbUpdate = [
                            'payment_status'            => 'paid',
                            'initial_payment_method'    => $oldKb->initial_payment_method ?: ($oldKb->payment_method ?: 'kasbon'),
                            'settlement_payment_method' => $methodSlug,
                            'settled_at'                => now(),
                            'payment_method'            => $methodSlug,
                            'notes'                     => ($oldKb->notes ? $oldKb->notes . ' | ' : '') . $kbNotePrefix,
                        ];
                        if ($proofPath) {
                            $kbUpdate['payment_proof'] = $proofPath;
                        }
                        $oldKb->update($kbUpdate);

                        $kbPayable = round((float) $oldKb->effective_payable_amount, 2);
                        if ($kbPayable > 0) {
                            $kbDesc = 'Pelunasan Kasbon (Sekalian Trip ' . $tripCode . ') #' . str_pad($oldKb->id, 6, '0', STR_PAD_LEFT)
                                . ' (' . ($oldKb->customer->name ?? 'Pelanggan') . ')';
                            if ($isCrossBranch) {
                                $kbDesc .= ' [Asal: ' . $originBranchName . ']';
                            }
                            if (!empty($orderNote)) {
                                $kbDesc .= ' - ' . $orderNote;
                            }
                            if (!$isCashChosen && $dateMode === 'nota_date') {
                                $kbDesc .= ' [Koreksi Nota Tgl ' . Carbon::parse($oldKb->transaction_date)->format('d/m/Y') . ']';
                            }

                            CashFlow::create([
                                'business_id'    => $oldKb->business_id,
                                'branch_id'      => $activeBranchId,
                                'type'           => 'income',
                                'flow_type'      => 'debt_settlement',
                                'reference_type' => 'transaction',
                                'category_id'    => $categoryId,
                                'payment_method' => $methodSlug,
                                'funding_source' => $isCashChosen ? 'drawer' : 'owner',
                                'affects_drawer' => $isCashChosen ? 1 : 0,
                                'amount'         => $kbPayable,
                                'description'    => $kbDesc,
                                'date'           => $kbDate,
                                'reference_id'   => $oldKb->id,
                                'created_by'     => Auth::id(),
                            ]);
                        }

                        \App\Models\Customer::where('id', $oldKb->customer_id)
                            ->increment('total_purchases', $oldKb->total_amount);

                        $alreadySettledOldIds[] = $oldKb->id;
                        $oldKasbonSettledCount++;
                        $oldKasbonSettledAmount = round($oldKasbonSettledAmount + $kbPayable, 2);
                    }
                }

                // 2B. Update & Catat Order Pengiriman Saat Ini
                if ($isOrderSettled) {
                    $paidCount++;

                    $settlementDate = (!$isCashChosen && $dateMode === 'nota_date')
                        ? Carbon::parse($transaction->transaction_date)->startOfDay()
                        : now();

                    \App\Services\Accounting\AccountingPeriodGuard::ensurePeriodOpen(
                        (int) $transaction->business_id,
                        $activeBranchId ? (int) $activeBranchId : null,
                        $settlementDate
                    );

                    $updateData = [
                        'payment_method'            => $methodSlug,
                        'initial_payment_method'    => $transaction->initial_payment_method ?: ($transaction->payment_method ?: 'kasbon'),
                        'settlement_payment_method' => $methodSlug,
                        'settled_at'                => now(),
                        'payment_status'            => 'paid',
                        'order_status'              => 'delivered',
                        'delivery_status'           => 'delivered',
                        'delivered_at'              => now(),
                    ];
                    if ($proofPath) {
                        $updateData['payment_proof'] = $proofPath;
                    }
                    if (!empty($orderNote)) {
                        $updateData['notes'] = ($transaction->notes ? $transaction->notes . ' | ' : '') . $orderNote;
                    }
                    $transaction->update($updateData);

                    $desc = 'Pelunasan Pengiriman ' . $tripCode . ' #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT) . ' (' . ($transaction->customer->name ?? 'Pelanggan Umum') . ')';
                    if (!empty($orderNote)) {
                        $desc .= ' - ' . $orderNote;
                    }
                    if (!$isCashChosen && $dateMode === 'nota_date') {
                        $desc .= ' [Koreksi Nota Tgl ' . Carbon::parse($transaction->transaction_date)->format('d/m/Y') . ']';
                    }

                    $payable = round((float) $transaction->effective_payable_amount, 2);

                    if ($payable > 0) {
                        CashFlow::create([
                            'business_id'    => $transaction->business_id,
                            'branch_id'      => $activeBranchId,
                            'type'           => 'income',
                            'flow_type'      => 'debt_settlement',
                            'reference_type' => 'transaction',
                            'category_id'    => $categoryId,
                            'payment_method' => $methodSlug,
                            'funding_source' => $isCashChosen ? 'drawer' : 'owner',
                            'affects_drawer' => $isCashChosen ? 1 : 0,
                            'amount'         => $payable,
                            'description'    => $desc,
                            'date'           => $settlementDate,
                            'reference_id'   => $transaction->id,
                            'created_by'     => Auth::id(),
                        ]);
                    }

                    if ($transaction->customer_id) {
                        \App\Models\Customer::where('id', $transaction->customer_id)
                            ->increment('total_purchases', $transaction->total_amount);
                    }
                } else {
                    $kasbonCount++;
                    $updateData = [
                        'payment_method'            => 'kasbon',
                        'initial_payment_method'    => $transaction->initial_payment_method ?: ($transaction->payment_method ?: 'kasbon'),
                        'settlement_payment_method' => null,
                        'settled_at'                => null,
                        'payment_status'            => 'pending',
                        'order_status'              => 'delivered',
                        'delivery_status'           => 'delivered',
                        'delivered_at'              => now(),
                    ];
                    if (!empty($orderNote)) {
                        $updateData['notes'] = ($transaction->notes ? $transaction->notes . ' | ' : '') . $orderNote;
                    }
                    $transaction->update($updateData);
                }

                // 2C. Input Deposit kelebihan bayar pengiriman jika ada sisa setelah pelunasan
                if ($depositAmount > 0 && $transaction->customer_id) {
                    $depDate = (!$isCashChosen && $dateMode === 'nota_date')
                        ? Carbon::parse($transaction->transaction_date)->startOfDay()
                        : now();

                    CustomerDepositService::addDeposit(
                        $businessId,
                        $activeBranchId,
                        $transaction->customer_id,
                        $depositAmount,
                        $methodSlug,
                        $transaction->id,
                        "Deposit kelebihan bayar pengiriman {$tripCode} #{$transaction->id}",
                        Auth::id(),
                        $depDate
                    );
                    $newDepositTotal = round($newDepositTotal + $depositAmount, 2);
                }
            }

            DB::commit();

            \App\Support\BranchCache::flush($businessId);

            Log::info('[DEPOSIT_DEBUG] KASIR_TRIP_DEPOSIT_SUMMARY', [
                'kasir_id'                 => Auth::id(),
                'trip_code'                => $tripCode,
                'paid_count'               => $paidCount,
                'kasbon_count'             => $kasbonCount,
                'old_kasbon_settled_count' => $oldKasbonSettledCount,
                'old_kasbon_settled_amt'   => $oldKasbonSettledAmount,
                'new_deposit_total'        => $newDepositTotal,
            ]);

            $msg = "Pengiriman #{$tripCode} berhasil diselesaikan! ";
            if ($paidCount > 0) {
                $msg .= "{$paidCount} transaksi trip lunas. ";
            }
            if ($oldKasbonSettledCount > 0) {
                $msg .= "{$oldKasbonSettledCount} kasbon lama (Rp " . number_format($oldKasbonSettledAmount, 0, ',', '.') . ") ikut dilunasi. ";
            }
            if ($newDepositTotal > 0) {
                $msg .= "Kelebihan bayar +Rp " . number_format($newDepositTotal, 0, ',', '.') . " masuk Saldo Deposit. ";
            }
            if ($kasbonCount > 0) {
                $msg .= "{$kasbonCount} transaksi dicatat sebagai Kasbon/Piutang.";
            }

            return back()->with('success', trim($msg));
        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($uploadedProofPaths as $p) {
                \App\Services\ProofUploadService::deleteProof($p);
            }
            Log::error('[DEPOSIT_DEBUG] KASIR_TRIP_ERROR', [
                'kasir_id'  => Auth::id(),
                'trip_code' => $tripCode,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'location'  => $e->getFile() . ':' . $e->getLine(),
                'trace'     => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ]);
            return back()->with('error', 'Gagal menyelesaikan pengiriman driver: ' . $e->getMessage());
        }
    }

    /**
     * Endpoint mandiri untuk kasir mencatat deposit pelanggan di luar pengiriman driver.
     */
    public function storeCustomerDeposit(\Illuminate\Http\Request $request): RedirectResponse
    {
        $request->validate([
            'customer_id'    => 'required|integer|exists:customers,id',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'notes'          => 'nullable|string|max:255',
        ]);

        try {
            $businessId = Auth::user()->business_id;
            $branchId = app(\App\Support\BranchContext::class)->requireBranchId();

            Log::info('[DEPOSIT_DEBUG] KASIR_STORE_DEPOSIT_REQUEST', [
                'kasir_id'       => Auth::id(),
                'business_id'    => $businessId,
                'branch_id'      => $branchId,
                'customer_id'    => (int) $request->customer_id,
                'amount'         => (float) $request->amount,
                'payment_method' => $request->payment_method,
                'notes'          => $request->notes,
            ]);

            $log = CustomerDepositService::addDeposit(
                $businessId,
                $branchId,
                (int) $request->customer_id,
                (float) $request->amount,
                $request->payment_method,
                null,
                $request->notes,
                Auth::id()
            );

            \App\Support\BranchCache::flush($businessId);

            Log::info('[DEPOSIT_DEBUG] KASIR_STORE_DEPOSIT_SUCCESS', [
                'kasir_id'       => Auth::id(),
                'deposit_log_id' => $log->id,
                'cash_flow_id'   => $log->cash_flow_id,
                'customer_id'    => $log->customer_id,
                'amount'         => (float) $log->amount,
                'balance_after'  => (float) $log->balance_after,
            ]);

            return back()->with('success', 'Deposit pelanggan sebesar Rp ' . number_format($request->amount, 0, ',', '.') . ' berhasil disimpan.');
        } catch (\Exception $e) {
            Log::error('[DEPOSIT_DEBUG] KASIR_STORE_DEPOSIT_ERROR', [
                'kasir_id'    => Auth::id(),
                'customer_id' => $request->customer_id,
                'amount'      => $request->amount,
                'exception'   => get_class($e),
                'message'     => $e->getMessage(),
                'location'    => $e->getFile() . ':' . $e->getLine(),
                'trace'       => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ]);
            return back()->with('error', 'Gagal mencatat deposit pelanggan: ' . $e->getMessage());
        }
    }
}
