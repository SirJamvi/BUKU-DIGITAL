<?php

namespace App\Services\Admin;

use App\Models\FundAllocationSetting;
use App\Models\FundAllocationHistory;
use App\Models\OwnerProfit;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class FundAllocationService
{
    /**
     * Mengambil data alokasi saat ini.
     *
     * @return array
     */
    public function getCurrentAllocation(): array
    {
        $currentProfit = OwnerProfit::latest()->first();
        $settings = $this->getAllocationSettings();

        return [
            'net_profit' => $currentProfit->net_profit ?? 0,
            'settings' => $settings,
        ];
    }

    /**
     * Mengambil semua pengaturan alokasi dana.
     *
     * @return Collection
     */
    public function getAllocationSettings(): Collection
    {
        return FundAllocationSetting::where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * Memperbarui pengaturan alokasi dana.
     *
     * @param array $data
     * @return void
     */
    public function updateAllocationSettings(array $data): void
    {
        // Validasi total persentase tidak boleh lebih dari 100%
        $totalPercentage = collect($data['settings'])->sum('percentage');
        if ($totalPercentage > 100) {
            throw new \Exception("Total persentase alokasi tidak boleh melebihi 100%.");
        }
        
        foreach ($data['settings'] as $settingData) {
            $setting = FundAllocationSetting::find($settingData['id']);
            if ($setting) {
                $setting->update(['percentage' => $settingData['percentage']]);
            }
        }
    }

    /**
     * Mengambil riwayat alokasi dana dengan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllocationHistory(int $perPage = 20): LengthAwarePaginator
    {
        return FundAllocationHistory::with('createdBy')->latest()->paginate($perPage);
    }
}