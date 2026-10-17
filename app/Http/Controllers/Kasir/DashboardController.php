<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Kasir\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\CashierOpeningBalance;

class DashboardController extends Controller
{
    /**
     * @var DashboardService
     */
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Menampilkan dashboard utama untuk kasir.
     * Sesuai SOP, dashboard kasir menampilkan ringkasan penjualan harian mereka.
     *
     * @return View
     */
    public function index(): View
    {
        try {
            $kasirId = Auth::id();
            $data = $this->dashboardService->getDashboardData($kasirId);
            
            return view('kasir.dashboard.index', $data);
        } catch (\Exception $e) {
            logger()->error('Error fetching kasir dashboard data: ' . $e->getMessage());
            return view('kasir.errors.500', ['message' => 'Tidak dapat memuat data dashboard.']);
        }
    }

    /**
     * Menyimpan atau memperbarui Modal Awal Laci (Kas Kemarin) untuk kasir hari ini.
     */
    public function setOpeningBalance(Request $request): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'notes'  => 'nullable|string|max:255',
        ]);

        $businessId = Auth::user()->business_id;
        $userId = Auth::id();
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        CashierOpeningBalance::updateOrCreate(
            [
                'business_id' => $businessId,
                'user_id'     => $userId,
                'date'        => $today,
            ],
            [
                'amount' => $request->amount,
                'notes'  => $request->notes,
            ]
        );

        return redirect()->back()->with('success', 'Modal Awal Laci (Kas Kemarin) sebesar Rp ' . number_format($request->amount, 0, ',', '.') . ' berhasil disimpan.');
    }
}