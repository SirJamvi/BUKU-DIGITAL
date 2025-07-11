<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FundAllocationRequest;
use App\Services\Admin\FundAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FundAllocationController extends Controller
{
    protected FundAllocationService $fundAllocationService;

    public function __construct(FundAllocationService $fundAllocationService)
    {
        $this->fundAllocationService = $fundAllocationService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(): View
    {
        $allocationData = $this->fundAllocationService->getCurrentAllocation();
        return view('admin.fund-allocation.index', compact('allocationData'));
    }

    public function settings(): View
    {
        $settings = $this->fundAllocationService->getAllocationSettings();
        return view('admin.fund-allocation.settings', compact('settings'));
    }

    public function updateSettings(FundAllocationRequest $request): RedirectResponse
    {
        try {
            $this->fundAllocationService->updateAllocationSettings($request->validated());
            return redirect()->route('admin.fund-allocation.settings')->with('success', 'Pengaturan alokasi dana berhasil diperbarui.');
        } catch (\Exception $e) {
            logger()->error('Error updating fund allocation settings: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui pengaturan alokasi dana.');
        }
    }

    public function history(): View
    {
        $history = $this->fundAllocationService->getAllocationHistory();
        return view('admin.fund-allocation.history', compact('history'));
    }
}