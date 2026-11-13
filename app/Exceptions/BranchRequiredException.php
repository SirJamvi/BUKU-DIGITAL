<?php

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchRequiredException extends RuntimeException
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response
    {
        $defaultMsg = 'Operasi ini memerlukan cabang aktif yang spesifik. Pilih cabang di menu Monitoring Cabang untuk melakukan input.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error'   => 'BranchRequired',
                'message' => $this->getMessage() ?: $defaultMsg,
            ], 422);
        }

        return redirect()->back()->with('error', $this->getMessage() ?: $defaultMsg);
    }
}

