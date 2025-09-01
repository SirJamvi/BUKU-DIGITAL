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
    }

    /**
     * Menampilkan halaman dashboard admin.
     */
    public function index(): View
    {
        try {
            // Mengambil semua data dashboard dari service
            $dashboardData = $this->dashboardService->getDashboardData();
            
            return view('admin.dashboard.index', $dashboardData);
            
        } catch (\Exception $e) {
            // Log error untuk debugging
            logger()->error('Error fetching dashboard data: ' . $e->getMessage());
            
            // Tampilkan halaman dengan pesan error
            return view('admin.dashboard.index', [
                'error' => 'Tidak dapat memuat data dashboard. Silakan coba lagi nanti.'
            ]);
        }
    }
}