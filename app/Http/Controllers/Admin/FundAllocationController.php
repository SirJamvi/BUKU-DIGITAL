<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FundAllocationRequest;
use App\Services\Admin\FundAllocationService;
use App\Services\Admin\FinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FundAllocationController extends Controller
{
    protected FundAllocationService $fundAllocationService;
    protected FinancialService $financialService;

    public function __construct(
        FundAllocationService $fundAllocationService,
        FinancialService $financialService
    ) {
        $this->fundAllocationService = $fundAllocationService;
        $this->financialService = $financialService;
    }

    /**
     * Menampilkan dashboard alokasi dana
     */
    public function index(): View
    {
        $allocationData = $this->fundAllocationService->getCurrentAllocation();
        $financialSummary = $this->financialService->getFinancialSummary();
        
        return view('admin.fund-allocation.index', [
            'allocationData' => $allocationData,
            'financialSummary' => $financialSummary
        ]);
    }

    /**
     * Menampilkan halaman pengaturan alokasi
     */
    public function settings(): View
    {
        $this->fundAllocationService->createDefaultAllocationSettings();
        $settings = $this->fundAllocationService->getAllocationSettings();
        return view('admin.fund-allocation.settings', compact('settings'));
    }

    /**
     * Memperbarui pengaturan alokasi
     */
    public function updateSettings(FundAllocationRequest $request): RedirectResponse
    {
        try {
            $this->fundAllocationService->updateAllocationSettings($request->validated());
            return redirect()->route('admin.fund-allocation.settings')->with('success', 'Pengaturan alokasi dana berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui pengaturan: ' . $e->getMessage());
        }
    }
    
    /**
     * Memproses alokasi dana dari form
     */
    public function processAllocation(Request $request): RedirectResponse
    {
        $request->validate(['owner_profit_ids' => 'required|array']);
        
        try {
            $this->fundAllocationService->processAllocation($request->input('owner_profit_ids'));
            return redirect()->route('admin.fund-allocation.index')->with('success', 'Alokasi dana berhasil diproses.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses alokasi: ' . $e->getMessage());
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
}