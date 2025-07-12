<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use App\Http\Resources\Admin\DashboardResource;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Menyediakan data ringkasan untuk dasbor admin.
     *
     * @return JsonResponse
     */
    public function summary(): JsonResponse
    {
        try {
            $data = $this->dashboardService->getDashboardData();
            // Menggunakan API Resource untuk memformat output
            return response()->json(new DashboardResource($data));
        } catch (\Exception $e) {
            logger()->error('API Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengambil data dasbor.',
            ], 500);
        }
    }
}