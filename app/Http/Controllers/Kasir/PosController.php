<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\StoreTransactionRequest;
use App\Services\Kasir\PosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    /**
     * @var PosService
     */
    protected $posService;

    public function __construct(PosService $posService)
    {
        $this->posService = $posService;
    }

    /**
     * Menampilkan antarmuka Point of Sale (POS).
     * Memuat produk dan data yang diperlukan untuk transaksi.
     *
     * @return View
     */
    public function index(): View
    {
        try {
            $posData = $this->posService->getPosData();
            return view('kasir.pos.index', $posData);
        } catch (\Exception $e) {
            logger()->error('Error loading POS interface: ' . $e->getMessage());
            
            // Kembalikan view dengan error message, bukan redirect
            return view('kasir.pos.index', [
                'error' => 'Gagal memuat antarmuka POS. Silakan coba lagi.',
                'products' => [],
                'categories' => []
            ]);
        }
    }

    /**
     * Menyimpan transaksi baru dari POS.
     * Proses ini mencakup validasi, pembuatan transaksi, dan pembaruan inventaris.
     *
     * @param StoreTransactionRequest $request
     * @return RedirectResponse
     */
    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            $transaction = $this->posService->processTransaction($request->validated());
            
            // Redirect ke halaman struk atau daftar transaksi dengan pesan sukses
            return redirect()
                ->route('kasir.transactions.show', $transaction->id)
                ->with('success', 'Transaksi berhasil disimpan.');

        } catch (\App\Exceptions\InsufficientStockException $e) {
            logger()->warning('POS Transaction failed: ' . $e->getMessage());
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            logger()->error('Error processing POS transaction: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memproses transaksi.')->withInput();
        }
    }
}