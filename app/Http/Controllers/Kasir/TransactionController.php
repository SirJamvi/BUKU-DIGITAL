<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\StoreTransactionRequest;
use App\Models\CashFlow;
use App\Models\Transaction;
use App\Services\Kasir\TransactionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                'payment_status' => 'paid',
                'payment_method' => $request->payment_method
            ];
            if ($proofPath) {
                $txUpdate['payment_proof'] = $proofPath;
            }
            $transaction->update($txUpdate);

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

            \App\Models\CashFlow::create([
                'business_id'    => $transaction->business_id,
                'branch_id'      => $activeBranchId, // Dicatat pada cabang kasir penerima dana
                'type'           => 'income',
                'category_id'    => 1,
                'payment_method' => $request->payment_method,
                'funding_source' => $affectsDrawer ? 'drawer' : 'owner',
                'affects_drawer' => $affectsDrawer,
                'amount'         => $transaction->total_amount,
                'description'    => $desc,
                'date'           => $settlementDate,
                'reference_id'   => $transaction->id,
                'created_by'     => $creatorId,
            ]);

            // Tambahkan akumulasi belanja customer
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
     */
    public function bulkMarkAsPaid(\Illuminate\Http\Request $request): RedirectResponse
    {
        $isCash = strtolower($request->payment_method ?? '') === 'cash';

        $request->validate([
            'transaction_ids'      => 'required|array',
            'transaction_ids.*'    => 'integer|exists:transactions,id',
            'payment_method'       => 'required|string',
            'settlement_date_mode' => 'nullable|string|in:today,nota_date',
            'settlement_notes'     => 'nullable|string|max:255',
            'payment_proof'        => ($isCash ? 'nullable' : 'required') . '|file|mimes:jpeg,png,jpg,webp|max:10240',
        ], [
            'transaction_ids.required' => 'Anda belum memilih transaksi satupun untuk dilunasi.',
            'payment_proof.required'   => 'Bukti pembayaran / transfer WhatsApp wajib diunggah untuk pelunasan non-tunai (' . strtoupper($request->payment_method ?? '') . ').',
        ]);

        $activeBranchId = app(\App\Support\BranchContext::class)->requireBranchId();
        $businessId = Auth::user()->business_id;
        $transactionIds = $request->transaction_ids;

        // Pastikan transaksi yang diubah benar-benar milik bisnis ini dan statusnya masih pending (lintas cabang diizinkan)
        $transactions = Transaction::withoutGlobalScope('branch')
            ->with(['branch', 'customer'])
            ->whereIn('id', $transactionIds)
            ->where('business_id', $businessId)
            ->where('payment_status', 'pending')
            ->get();

        if ($transactions->count() !== count($transactionIds)) {
            abort(404, 'Satu atau lebih transaksi bukan milik bisnis Anda atau sudah lunas.');
        }

        try {
            DB::beginTransaction();

            // 1 Bukti pembayaran yang diupload kasir diaplikasikan ke semua transaksi yang dicentang
            $proofPath = null;
            if ($request->hasFile('payment_proof')) {
                $proofPath = \App\Services\ProofUploadService::uploadPaymentProof($request->file('payment_proof'));
            }

            foreach ($transactions as $transaction) {
                // Tentukan tanggal buku pencatatan cash flow per transaksi:
                // Jika Cash, selalu masuk kas hari ini (koreksi lupa update dikunci)
                $settlementDate = (!$isCash && $request->settlement_date_mode === 'nota_date')
                    ? \Carbon\Carbon::parse($transaction->transaction_date)->startOfDay()
                    : now();

                // 1. Update status masing-masing transaksi beserta bukti pembayaran
                $txUpdate = [
                    'payment_status' => 'paid',
                    'payment_method' => $request->payment_method
                ];
                if ($proofPath) {
                    $txUpdate['payment_proof'] = $proofPath;
                }
                $transaction->update($txUpdate);

                $originBranchName = $transaction->branch ? $transaction->branch->name : 'Cabang Asal';
                $isCrossBranch = ((int) $transaction->branch_id !== (int) $activeBranchId);

                // Keterangan audit trail pelunasan
                $notesPrefix = '[Pelunasan di ' . (Auth::user()->branch->name ?? 'Cabang Kasir');
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

                // 2. Catat ke CashFlow untuk setiap transaksi agar laporan tercatat rapi
                // PENTING: Dicatat di branch_id kasir aktif yang menerima pembayaran ($activeBranchId)
                // sehingga uang fisik laci kasir (myNetCashToday) bertambah di kasir penerima!
                $desc = 'Pelunasan Kasbon (Bulk) #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
                if ($isCrossBranch) {
                    $desc .= ' [Asal: ' . $originBranchName . ']';
                }
                if ($request->filled('settlement_notes')) {
                    $desc .= ' (' . $request->settlement_notes . ')';
                }
                if ($request->settlement_date_mode === 'nota_date') {
                    $desc .= ' [Koreksi Nota Tgl ' . \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y') . ']';
                }

                \App\Models\CashFlow::create([
                    'business_id'    => $transaction->business_id,
                    'branch_id'      => $activeBranchId, // Laci kasir penerima
                    'type'           => 'income',
                    'category_id'    => 1,
                    'payment_method' => $request->payment_method,
                    'funding_source' => $isCash ? 'drawer' : 'owner',
                    'affects_drawer' => $isCash ? 1 : 0,
                    'amount'         => $transaction->total_amount,
                    'description'    => $desc,
                    'date'           => $settlementDate,
                    'reference_id'   => $transaction->id,
                    'created_by'     => Auth::id(),
                ]);

                // Tambahkan akumulasi belanja customer
                if ($transaction->customer_id) {
                    $customer = \App\Models\Customer::find($transaction->customer_id);
                    if ($customer) {
                        $customer->increment('total_purchases', $transaction->total_amount);
                    }
                }
            }

            DB::commit();

            \App\Support\BranchCache::flush($businessId);

            return back()->with('success', count($transactions) . ' Transaksi berhasil dilunasi secara bersamaan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses pelunasan massal: ' . $e->getMessage());
        }
    }

    /**
     * [BARU] Memproses penyelesaian pengiriman driver kolektif dengan real payment per transaksi.
     */
    public function completeDeliveryTrip(\Illuminate\Http\Request $request): RedirectResponse
    {
        $request->validate([
            'trip_code' => 'required|string',
            'payments' => 'required|array',
            'notes' => 'nullable|array',
        ]);

        $businessId = Auth::user()->business_id;
        $tripCode = $request->trip_code;

        $transactions = Transaction::with('customer')
            ->where('business_id', $businessId)
            ->where('delivery_trip_code', $tripCode)
            ->get();

        if ($transactions->isEmpty()) {
            return back()->with('error', 'Tidak ditemukan transaksi valid untuk nomor pengiriman ini.');
        }

        try {
            DB::beginTransaction();

            $paidCount = 0;
            $kasbonCount = 0;

            foreach ($transactions as $transaction) {
                $chosenMethod = $request->payments[$transaction->id] ?? 'kasbon';
                $orderNote = $request->notes[$transaction->id] ?? null;
                $isKasbon = strtolower($chosenMethod) === 'kasbon';

                $methodSlug = $isKasbon ? 'kasbon' : \Illuminate\Support\Str::slug($chosenMethod);

                $updateData = [
                    'payment_method' => $methodSlug,
                    'payment_status' => $isKasbon ? 'pending' : 'paid',
                    'order_status'   => 'delivered',
                    'delivery_status'=> 'delivered',
                    'delivered_at'   => now(),
                ];

                if (!empty($orderNote)) {
                    $updateData['notes'] = ($transaction->notes ? $transaction->notes . ' | ' : '') . $orderNote;
                }

                $transaction->update($updateData);

                if (!$isKasbon) {
                    $paidCount++;

                    $dateMode = $request->settlement_date_modes[$transaction->id] ?? $request->settlement_date_mode ?? 'today';
                    $settlementDate = ($dateMode === 'nota_date')
                        ? \Carbon\Carbon::parse($transaction->transaction_date)->startOfDay()
                        : now();

                    $desc = 'Pelunasan Pengiriman ' . $tripCode . ' #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT) . ' (' . ($transaction->customer->name ?? 'Pelanggan Umum') . ')';
                    if (!empty($orderNote)) {
                        $desc .= ' - ' . $orderNote;
                    }
                    if ($dateMode === 'nota_date') {
                        $desc .= ' [Koreksi Nota Tgl ' . \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y') . ']';
                    }

                    $isCashChosen = strtolower($methodSlug) === 'cash';

                    // Catat ke CashFlow secara dinamis dengan metode pembayaran riil & alokasi tanggal
                    \App\Models\CashFlow::create([
                        'business_id'    => $transaction->business_id,
                        'branch_id'      => $transaction->branch_id,
                        'type'           => 'income',
                        'category_id'    => 1,
                        'payment_method' => $methodSlug,
                        'funding_source' => $isCashChosen ? 'drawer' : 'owner',
                        'affects_drawer' => $isCashChosen ? 1 : 0,
                        'amount'         => $transaction->total_amount,
                        'description'    => $desc,
                        'date'           => $settlementDate,
                        'reference_id'   => $transaction->id,
                        'created_by'     => Auth::id(),
                    ]);

                    // Tambahkan akumulasi belanja customer
                    if ($transaction->customer_id) {
                        $customer = \App\Models\Customer::find($transaction->customer_id);
                        if ($customer) {
                            $customer->increment('total_purchases', $transaction->total_amount);
                        }
                    }
                } else {
                    $kasbonCount++;
                }
            }

            DB::commit();

            \App\Support\BranchCache::flush($businessId);

            $msg = "Pengiriman #{$tripCode} berhasil diselesaikan! ";
            if ($paidCount > 0) {
                $msg .= "{$paidCount} transaksi lunas dicatat ke kasir. ";
            }
            if ($kasbonCount > 0) {
                $msg .= "{$kasbonCount} transaksi tetap dicatat sebagai Kasbon/Piutang.";
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('completeDeliveryTrip error: ' . $e->getMessage());
            return back()->with('error', 'Gagal menyelesaikan pengiriman driver: ' . $e->getMessage());
        }
    }
}
