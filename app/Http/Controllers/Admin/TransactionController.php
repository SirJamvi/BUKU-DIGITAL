<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\CashFlow;
use App\Services\Admin\TransactionService as AdminTransactionService;
use App\Services\Kasir\TransactionService as KasirTransactionService;
use App\Http\Requests\Kasir\StoreTransactionRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TransactionController extends Controller
{
    use AuthorizesRequests;

    protected AdminTransactionService $transactionService;

    public function __construct(AdminTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function index(Request $request): View
    {
        $transactions = $this->transactionService->getAllTransactionsWithPagination($request->all());
        return view('admin.transactions.index', compact('transactions'));
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load('customer', 'createdBy', 'details.product');
        return view('admin.transactions.show', compact('transaction'));
    }

    public function edit(Transaction $transaction): View
    {
        $data = app(KasirTransactionService::class)->getEditTransactionData($transaction);
        return view('admin.transactions.edit', $data);
    }

    public function update(StoreTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        try {
            app(KasirTransactionService::class)->updateTransaction($transaction, $request->validated());
            return redirect()
                ->route('admin.transactions.show', $transaction->id)
                ->with('success', 'Transaksi berhasil diperbarui oleh Admin.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui transaksi: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * [BARU] Memvalidasi pelunasan kasbon dari role Admin dengan opsi alokasi kas fisik (Laci Kasir vs Owner).
     */
    public function markAsPaid(Request $request, Transaction $transaction): RedirectResponse
    {
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
            $cashDestination = $request->cash_destination ?? 'drawer';

            $proofPath = null;
            if ($request->hasFile('payment_proof')) {
                $proofPath = \App\Services\ProofUploadService::uploadPaymentProof($request->file('payment_proof'));
            }

            // Tentukan tanggal buku pencatatan cash flow:
            // Jika Cash, selalu masuk kas hari ini (koreksi lupa update dikunci)
            $settlementDate = (!$isCash && $request->settlement_date_mode === 'nota_date')
                ? Carbon::parse($transaction->transaction_date)->startOfDay()
                : now();

            $txUpdate = [
                'payment_status' => 'paid',
                'payment_method' => $request->payment_method
            ];
            if ($proofPath) {
                $txUpdate['payment_proof'] = $proofPath;
            }
            $transaction->update($txUpdate);

            if ($request->filled('settlement_notes')) {
                $transaction->update([
                    'notes' => ($transaction->notes ? $transaction->notes . ' | ' : '') . '[Pelunasan: ' . $request->settlement_notes . ']'
                ]);
            }

            $desc = 'Pelunasan Transaksi Kasbon #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
            if ($request->filled('settlement_notes')) {
                $desc .= ' (' . $request->settlement_notes . ')';
            }
            if ($request->settlement_date_mode === 'nota_date') {
                $desc .= ' [Koreksi Nota Tgl ' . Carbon::parse($transaction->transaction_date)->format('d/m/Y') . ']';
            }

            // Aturan alokasi kas fisik untuk Admin:
            // Jika drawer: masuk ke laci kasir pembuat nota (created_by = $transaction->created_by, affects_drawer = 1)
            // Jika owner / non-cash: dipegang Admin/Owner (created_by = Auth::id(), affects_drawer = 0)
            $affectsDrawer = ($isCash && $cashDestination === 'drawer') ? 1 : 0;
            $creatorId = ($isCash && $cashDestination === 'drawer' && $transaction->created_by)
                ? $transaction->created_by
                : Auth::id();

            CashFlow::create([
                'business_id'    => $transaction->business_id,
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

            return back()->with('success', "Transaksi #{$transaction->id} berhasil dilunasi oleh Admin.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memvalidasi pelunasan: ' . $e->getMessage());
        }
    }

    /**
     * [BARU] Memproses penghapusan transaksi beserta rollback stok dan kas.
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        try {
            // Ambil ID dan tanggal untuk pesan sukses sebelum data hilang
            $txId = $transaction->id;

            $this->transactionService->deleteTransaction($transaction);

            return redirect()
                ->route('admin.transactions.index')
                ->with('success', "Transaksi #{$txId} berhasil dihapus. Stok, Poin, dan Arus Kas telah dikembalikan.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}
