<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportApiController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Menyediakan data laporan penjualan dalam format JSON.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sales(Request $request): JsonResponse
    {
        try {
            $reportData = $this->reportService->getSalesReport($request->all());
            return response()->json($reportData);
        } catch (\Exception $e) {
            logger()->error('API Sales Report Error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengambil laporan penjualan.'], 500);
        }
    }

    /**
     * Menyediakan data laporan keuangan dalam format JSON.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function financial(Request $request): JsonResponse
    {
        try {
            $reportData = $this->reportService->getFinancialReport($request->all());
            return response()->json($reportData);
        } catch (\Exception $e) {
            logger()->error('API Financial Report Error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengambil laporan keuangan.'], 500);
        }
    }
}