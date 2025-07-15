<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FundAllocationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FundAllocationController extends Controller
{
    protected FundAllocationService $fundAllocationService;

    public function __construct(FundAllocationService $fundAllocationService)
    {
        $this->fundAllocationService = $fundAllocationService;
    }

    /**
     * Menampilkan dashboard alokasi dana
     */
    public function index(): View
    {
        // Panggil service untuk mendapatkan data yang sudah dihitung
        $allocationData = $this->fundAllocationService->getCurrentAllocation();
        
        // Kirim data ke view
        return view('admin.fund-allocation.index', compact('allocationData'));
    }

    /**
     * [DIPERBARUI] Menampilkan halaman pengaturan alokasi
     * Sekarang akan otomatis membuat pengaturan default jika belum ada.
     */
    public function settings(): View
    {
        // Panggil method untuk memastikan pengaturan default ada untuk bisnis ini
        $this->fundAllocationService->createDefaultAllocationSettings();

        // Setelah dipastikan ada, ambil datanya
        $settings = $this->fundAllocationService->getAllocationSettings();
        
        return view('admin.fund-allocation.settings', compact('settings'));
    }

    /**
     * Memperbarui pengaturan alokasi
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:fund_allocation_settings,id',
            'settings.*.percentage' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $this->fundAllocationService->updateAllocationSettings($request->all());
            
            return redirect()->route('admin.fund-allocation.settings')
                ->with('success', 'Pengaturan alokasi dana berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->route('admin.fund-allocation.settings')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Menampilkan riwayat alokasi
     */
    public function history(): View
    {
        $history = $this->fundAllocationService->getAllocationHistory();
        return view('admin.fund-allocation.history', compact('history'));
    }

    /**
     * Memproses alokasi dana
     */
    public function processAllocation(Request $request): RedirectResponse
    {
        $request->validate([
            'owner_profit_id' => 'required|exists:owner_profits,id',
        ]);

        try {
            $this->fundAllocationService->processAllocation($request->owner_profit_id);
            
            return redirect()->route('admin.fund-allocation.index')
                ->with('success', 'Alokasi dana berhasil diproses.');
        } catch (\Exception $e) {
            return redirect()->route('admin.fund-allocation.index')
                ->with('error', $e->getMessage());
        }
    }
}