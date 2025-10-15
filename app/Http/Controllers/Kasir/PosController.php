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
            // PERBAIKAN: Redirect langsung ke halaman RECEIPT/STRUK
            // untuk langsung print, bukan ke halaman detail transaksi
            // ==========================================================
            return redirect()
                ->route('kasir.pos.receipt', $transaction->id)
                ->with('success', 'Transaksi berhasil! Struk akan dicetak otomatis.');

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
     * Menampilkan halaman struk/receipt untuk printing.
     */
    public function receipt(Transaction $transaction): View
    {
        // Ambil data transaksi lengkap dengan relasi
        $transaction = $this->posService->getTransactionWithDetails($transaction->id);
        
        return view('kasir.pos.receipt', compact('transaction'));
    }
}