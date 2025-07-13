<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Kasir\PosService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse; // <-- Tambahkan ini
use Illuminate\View\View; // <-- Tambahkan ini
use App\Http\Requests\Kasir\StoreTransactionRequest; // <-- Gunakan Form Request Anda

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
            // INI PERBAIKANNYA:
            // Selalu redirect ke halaman detail transaksi (struk)
            // setelah transaksi berhasil, bukan mengembalikan JSON.
            // ==========================================================
            return redirect()
                ->route('kasir.transactions.show', $transaction->id)
                ->with('success', 'Transaksi berhasil disimpan. Struk siap dicetak.');

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
}