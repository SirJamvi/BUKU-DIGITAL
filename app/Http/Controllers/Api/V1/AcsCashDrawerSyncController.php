<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Acs\AcsCashDrawerSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AcsCashDrawerSyncController extends Controller
{
    protected AcsCashDrawerSyncService $syncService;

    public function __construct(AcsCashDrawerSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Endpoint Sinkronisasi Saldo Laci Kasir BukuDig ke ACS (resdigaz_sacs).
     * Digunakan untuk auto-validasi saat Closing Shift Sore (16:45 – 17:15 WIB).
     *
     * Route: GET /api/v1/sync/acs/drawer-summary
     * Header: X-Sync-Secret: <SECRET_KEY> atau X-ACS-Key: <SECRET_KEY> atau Authorization: Bearer <SECRET_KEY>
     * Query Params:
     *   - date (opsional, default: hari ini WIB, format: YYYY-MM-DD)
     *   - branch_code (opsional, contoh: 'CB-PUSAT', 'WARUNGBAMBU', atau kosong untuk semua cabang)
     *   - shift (opsional, 'sore' | 'all', default: 'sore' membatasi 07:00 - 17:15 WIB)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDrawerSummary(Request $request): JsonResponse
    {
        // 1. Validasi Keamanan API Key / Shared Secret
        $authorized = $this->verifyAuthorization($request);
        if (!$authorized) {
            Log::warning('ACS Sync: Akses ditolak karena shared secret / API key tidak valid dari IP: ' . $request->ip());
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: Invalid or missing X-Sync-Secret / X-ACS-Key header.',
            ], 401);
        }

        // 2. Validasi Parameter Request
        $request->validate([
            'date'        => 'nullable|date_format:Y-m-d',
            'branch_code' => 'nullable|string|max:50',
            'shift'       => 'nullable|string|in:sore,all,full',
        ]);

        try {
            $date = $request->input('date') ?? Carbon::today('Asia/Jakarta')->toDateString();
            $branchCode = $request->input('branch_code');
            $shift = strtolower($request->input('shift', 'sore'));
            $shiftTimeFilter = ($shift === 'sore');

            $summaries = $this->syncService->getBranchDrawerSummary($date, $branchCode, $shiftTimeFilter);

            return response()->json([
                'status'       => 'success',
                'system'       => 'BukuDig POS & Finance',
                'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                'query'        => [
                    'date'        => $date,
                    'branch_code' => $branchCode ?: 'ALL_BRANCHES',
                    'shift'       => $shift,
                    'window'      => $shiftTimeFilter ? '07:00:00 - 17:15:00 WIB' : '00:00:00 - 23:59:59 WIB',
                ],
                'data'         => $summaries,
            ], 200);

        } catch (\Exception $e) {
            Log::error('ACS Sync Error saat mengambil drawer summary: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Internal server error saat mengolah data laci kasir: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verifikasi token otentikasi dari header.
     */
    protected function verifyAuthorization(Request $request): bool
    {
        $serverKey = config('services.sync.secret') ?: env('ACS_SYNC_SECRET_KEY') ?: env('SYNC_SECRET_KEY');

        // Jika kunci belum diset di .env server, tolak demi keamanan
        if (empty($serverKey)) {
            Log::emergency('ACS Sync: SYNC_SECRET_KEY / ACS_SYNC_SECRET_KEY belum dikonfigurasi di environment server!');
            return false;
        }

        $incomingSecret = $request->header('X-Sync-Secret')
            ?: $request->header('X-ACS-Key')
            ?: $request->bearerToken();

        return !empty($incomingSecret) && hash_equals((string) $serverKey, (string) $incomingSecret);
    }
}
