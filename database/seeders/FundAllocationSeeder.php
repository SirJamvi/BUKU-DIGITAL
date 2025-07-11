<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FundAllocationSetting;

class FundAllocationSeeder extends Seeder
{
    public function run(): void
    {
        $defaultAllocations = config('business.fund_allocation', []);
        $sort = 1;

        foreach ($defaultAllocations as $key => $percentage) {
            FundAllocationSetting::create([
                'allocation_name' => ucfirst(str_replace('_', ' ', $key)),
                'percentage' => $percentage,
                'category' => $key,
                'is_default' => true,
                'is_active' => true,
                'created_by' => 1, // Dibuat oleh Admin pertama
                'sort_order' => $sort++,
            ]);
        }
    }
}