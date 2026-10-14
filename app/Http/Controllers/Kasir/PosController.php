<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Kasir\PosService;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
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
            $result = $this->posService->processTransaction($request->validated());

            // ==========================================================
            // Jika transaksi adalah Pengiriman Rombongan (Batch Delivery)
            // ==========================================================
            if (is_array($result) && !empty($result['is_batch'])) {
                return redirect()
                    ->route('kasir.pos.deliveryReceipt', $result['trip_code'])
                    ->with('success', "Pengiriman #{$result['trip_code']} berhasil dibuat! " . count($result['transactions']) . " pesanan ditugaskan ke driver.");
            }

            $transaction = $result;

            // ==========================================================
            // Redirect ke struk yang sesuai berdasarkan status pembayaran
            // ==========================================================
            if ($transaction->payment_status === 'pending') {
                $msg = $transaction->driver_id
                    ? "Pesanan Pengiriman Driver (#{$transaction->delivery_trip_code}) dicatat! Struk tagihan & surat jalan siap dicetak."
                    : 'Transaksi Kasbon dicatat! Struk tagihan akan dicetak.';
                return redirect()
                    ->route('kasir.pos.receiptUnpaid', $transaction->id)
                    ->with('success', $msg);
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
            return back()->with('error', 'Terjadi kesalahan saat memproses transaksi: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Menampilkan Surat Jalan dan ringkasan pengiriman rombongan driver.
     */
    public function deliveryReceipt(string $tripCode): View
    {
        $transactions = Transaction::with(['customer', 'details.product', 'driver', 'createdBy'])
            ->where('business_id', Auth::user()->business_id)
            ->where('delivery_trip_code', $tripCode)
            ->get();

        if ($transactions->isEmpty()) {
            abort(404, 'Data pengiriman tidak ditemukan.');
        }

        return view('kasir.pos.delivery_receipt', compact('tripCode', 'transactions'));
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