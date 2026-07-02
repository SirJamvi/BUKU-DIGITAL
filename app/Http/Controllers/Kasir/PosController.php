<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Kasir\PosService;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Http\Requests\Kasir\StoreTransactionRequest;

class PosController extends Controller
{
    protected $posService;

    public function __construct(PosService $posService)
    {
        $this->posService = $posService;
    }

    /**
     * Menampilkan antarmuka Point of Sale (POS).
     */
    public function index(): View
    {
        $data = $this->posService->getPosData();
        return view('kasir.pos.index', $data);
    }

    /**
     * Menyimpan transaksi baru dan mengarahkan ke halaman struk.
     */
    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            $transaction = $this->posService->processTransaction($request->validated());

            // ==========================================================
            // Redirect ke struk yang sesuai berdasarkan status pembayaran
            // ==========================================================
            if ($transaction->payment_status === 'pending') {
                return redirect()
                    ->route('kasir.pos.receiptUnpaid', $transaction->id)
                    ->with('success', 'Transaksi Kasbon dicatat! Struk tagihan akan dicetak.');
            }

            return redirect()
                ->route('kasir.pos.receipt', $transaction->id)
                ->with('success', 'Transaksi Lunas berhasil! Struk akan dicetak.');

        } catch (\App\Exceptions\InsufficientStockException $e) {
            // Jika stok tidak cukup, kembali ke halaman POS dengan pesan error
            logger()->warning('POS Transaction failed: ' . $e->getMessage());
            return back()->with('error', $e->getMessage())->withInput();

        } catch (\Exception $e) {
            // Jika ada error lain, kembali ke halaman POS dengan pesan error umum
            logger()->error('Error processing POS transaction: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memproses transaksi.')->withInput();
        }
    }

    /**
     * Menampilkan halaman struk/receipt untuk printing (transaksi LUNAS).
     */
    public function receipt(Transaction $transaction): View
    {
        $transaction = $this->posService->getTransactionWithDetails($transaction->id);

        return view('kasir.pos.receipt', compact('transaction'));
    }

    /**
     * Menampilkan halaman struk TAGIHAN (Belum Lunas / Kasbon).
     */
    public function receiptUnpaid(Transaction $transaction): View
    {
        $transaction = $this->posService->getTransactionWithDetails($transaction->id);

        return view('kasir.pos.receipt_unpaid', compact('transaction'));
    }
}