<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnauthorizedException extends Exception
{
    /**
     * Pesan error default.
     *
     * @var string
     */
    protected $message = 'Akses ditolak. Anda tidak memiliki izin untuk melakukan tindakan ini.';

    /**
     * Render exception ini ke dalam respons HTTP.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return new JsonResponse(
                [
                    'error' => 'Unauthorized',
                    'message' => $this->getMessage(),
                ],
                403 // 403 Forbidden
            );
        }

        // Tampilkan halaman error 403 khusus
        return response()->view('errors.403', ['exception' => $this], 403);
    }
}