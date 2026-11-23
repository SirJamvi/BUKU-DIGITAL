<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashierShiftClosing;
use App\Services\Accounting\AccountingPeriodGuard;
use App\Services\Acs\AcsCashDrawerSyncService;
use App\Services\Kasir\CashierClosingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcsCashDrawerSyncController extends Controller
{
    protected AcsCashDrawerSyncService $syncService;
    protected CashierClosingService $closingService;

    public function __construct(
        AcsCashDrawerSyncService $syncService,
        CashierClosingService $closingService
    ) {
        $this->syncService = $syncService;
        $this->closingService = $closingService;
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
     * Endpoint Sinkronisasi Laporan Tutup Shift & Handover dari ACS (resdigaz_attendance).
     * Menerapkan idempotency berbasis `external_reference` (misal 'ACS-SUB-123').
     *
     * Route: POST /api/v1/sync/acs/shift-closing
     * Header: X-Sync-Secret / X-ACS-Key / Authorization: Bearer
     */
    public function storeShiftClosing(Request $request): JsonResponse
    {
        // 1. Verifikasi Keamanan API Key / Shared Secret
        $authorized = $this->verifyAuthorization($request);
        if (!$authorized) {
            Log::warning('ACS Sync Shift Closing: Akses ditolak dari IP: ' . $request->ip());
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: Invalid or missing X-Sync-Secret / X-ACS-Key header.',
            ], 401);
        }

        // 2. Validasi Payload
        $request->validate([
            'external_reference'   => 'required|string|max:100',
            'business_id'          => 'required|integer',
            'branch_id'            => 'required|integer',
            'user_id'              => 'required|integer',
            'date'                 => 'required|date_format:Y-m-d',
            'shift_code'           => 'nullable|string|max:30',
            'actual_cash_counted'  => 'required|numeric|min:0',
            'cash_handover_amount' => 'nullable|numeric|min:0',
            'handover_receiver'    => 'nullable|string|max:150',
            'notes'                => 'nullable|string',
            'submitted_at'         => 'nullable|string',
        ]);

        $externalReference = trim($request->input('external_reference'));

        // 3. Cek Idempotency: Jika sudah pernah diproses, kembalikan 200 tanpa insert ulang
        $existing = CashierShiftClosing::where('external_reference', $externalReference)->first();
        if ($existing) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan tutup shift sudah pernah diproses (idempotent).',
                'data'    => [
                    'closing_id'                 => $existing->id,
                    'external_reference'         => $existing->external_reference,
                    'expected_drawer_cash'       => (float) $existing->expected_drawer_cash,
                    'actual_cash_counted'        => (float) $existing->actual_cash_counted,
                    'variance'                   => (float) $existing->variance,
                    'cash_handover_amount'       => (float) $existing->cash_handover_amount,
                    'ending_cash_for_next_shift' => (float) $existing->ending_cash_for_next_shift,
                    'handover_receiver'          => $existing->handover_receiver,
                    'date'                       => $existing->date ? Carbon::parse($existing->date)->toDateString() : null,
                    'is_idempotent'              => true,
                ],
            ], 200);
        }

        try {
            $closingData = DB::transaction(function () use ($request, $externalReference) {
                $businessId         = (int) $request->input('business_id');
                $branchId           = (int) $request->input('branch_id');
                $userId             = (int) $request->input('user_id');
                $date               = $request->input('date');
                $shiftCode          = $request->input('shift_code', 'default');
                $actualCashCounted  = round((float) $request->input('actual_cash_counted'), 2);
                $cashHandoverAmount = round((float) $request->input('cash_handover_amount', 0), 2);
                $handoverReceiver   = $request->input('handover_receiver');
                $notes              = $request->input('notes');

                // a) Validasi periode akuntansi masih terbuka
                AccountingPeriodGuard::ensurePeriodOpen($businessId, $branchId, $date);

                // b) Hitung & simpan closing melalui CashierClosingService
                // Dilakukan sebelum handover dicatat agar expected_drawer_cash & variance identik dengan Blind Count di ACS
                $closing = $this->closingService->closeDailyShift([
                    'business_id'                => $businessId,
                    'branch_id'                  => $branchId,
                    'user_id'                    => $userId,
                    'date'                       => $date,
                    'shift_code'                 => $shiftCode,
                    'actual_cash_counted'        => $actualCashCounted,
                    'ending_cash_for_next_shift' => $actualCashCounted,
                    'notes'                      => $notes,
                    'closed_by'                  => $userId,
                ]);

                // c) Jika cash_handover_amount > 0, catat mutasi kas penyerahan ke Owner/Bank
                $totalHandover = (float) $closing->cash_handover_amount;
                if ($cashHandoverAmount > 0) {
                    $this->closingService->recordCashHandover(
                        $businessId,
                        $branchId,
                        $userId,
                        $cashHandoverAmount,
                        $notes ? "Tutup Shift: {$notes}" : 'Tutup Shift ACS',
                        'shift_closing',
                        $externalReference,
                        $date
                    );

                    $totalHandover += $cashHandoverAmount;
                }

                $endingCash = max(0.0, round($actualCashCounted - $cashHandoverAmount, 2));

                // d) Update record cashier_shift_closings
                $closing->update([
                    'external_reference'         => $externalReference,
                    'handover_receiver'          => $handoverReceiver,
                    'cash_handover_amount'       => $totalHandover,
                    'ending_cash_for_next_shift' => $endingCash,
                ]);

                return $closing->fresh();
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan tutup shift dan serah terima kas berhasil disinkronkan ke Buku Digital.',
                'data'    => [
                    'closing_id'                 => $closingData->id,
                    'external_reference'         => $closingData->external_reference,
                    'expected_drawer_cash'       => (float) $closingData->expected_drawer_cash,
                    'actual_cash_counted'        => (float) $closingData->actual_cash_counted,
                    'variance'                   => (float) $closingData->variance,
                    'cash_handover_amount'       => (float) $closingData->cash_handover_amount,
                    'ending_cash_for_next_shift' => (float) $closingData->ending_cash_for_next_shift,
                    'handover_receiver'          => $closingData->handover_receiver,
                    'date'                       => $closingData->date ? Carbon::parse($closingData->date)->toDateString() : null,
                    'is_idempotent'              => false,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('ACS Sync Error saat simpan shift closing: ' . $e->getMessage(), [
                'external_reference' => $externalReference,
                'trace'              => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memproses sinkronisasi tutup shift: ' . $e->getMessage(),
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
