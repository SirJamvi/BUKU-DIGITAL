<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientStockException extends Exception
{
    /**
     * Pesan error default.
     *
     * @var string
     */
    protected $message = 'Stok produk tidak mencukupi untuk menyelesaikan transaksi.';

    /**
     * Render exception ini ke dalam respons HTTP.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function render(Request $request)
    {
        // Jika request mengharapkan JSON (misalnya dari API)
        if ($request->expectsJson()) {
            return new JsonResponse(
                [
                    'error' => 'Insufficient Stock',
                    'message' => $this->getMessage(),
                ],
                422 // 422 Unprocessable Entity adalah status yang sesuai
            );
        }

        // Untuk request web biasa, redirect kembali dengan pesan error
        return redirect()
            ->back()
            ->with('error', $this->getMessage())
            ->withInput();
    }
}