<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(): View
    {
        try {
            $data = $this->dashboardService->getDashboardData();
            return view('admin.dashboard.index', $data);
        } catch (\Exception $e) {
            logger()->error('Error fetching dashboard data: ' . $e->getMessage());
            return view('admin.errors.500', ['message' => 'Tidak dapat memuat data dashboard.']);
        }
    }
}
