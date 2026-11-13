<?php

namespace Tests\Unit\Services;

use App\Support\BranchCache;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BranchCacheTest extends TestCase
{
    public function test_branch_cache_key_generation_consolidated(): void
    {
        $context = app(BranchContext::class);
        $context->setConsolidated(10);

        Cache::shouldReceive('rememberForever')
            ->with('biz_10_cache_ver', \Mockery::type('Closure'))
            ->andReturn(1);

        $key = BranchCache::key('dashboard_perf', '2026-11');
        $this->assertEquals('biz_10_v1_all_dashboard_perf_2026-11', $key);
    }

    public function test_branch_cache_key_generation_branch_scope(): void
    {
        $context = app(BranchContext::class);
        $context->setBranch(5, 10);

        Cache::shouldReceive('rememberForever')
            ->with('biz_10_cache_ver', \Mockery::type('Closure'))
            ->andReturn(2);

        $key = BranchCache::key('sales_today', 'today');
        $this->assertEquals('biz_10_v2_b5_sales_today_today', $key);
    }
}

