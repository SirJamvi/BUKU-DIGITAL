<?php

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PeriodClosedException extends RuntimeException
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response
    {
        $defaultMsg = 'Periode pembukuan pada tanggal/bulan ini sudah ditutup. Tidak dapat melakukan mutasi atau pembatalan transaksi.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error'   => 'PeriodClosed',
                'message' => $this->getMessage() ?: $defaultMsg,
            ], 422);
        }

        return redirect()->back()->with('error', $this->getMessage() ?: $defaultMsg);
    }
}
