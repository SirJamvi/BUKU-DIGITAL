<?php

namespace Tests\Unit\Services;

use App\Services\Inventory\InventoryCostService;
use PHPUnit\Framework\TestCase;

class InventoryCostServiceTest extends TestCase
{
    /**
     * Test weightedAverage saat stok awal 0 -> HPP sama dengan harga beli.
     * Uji: stok 0, beli 50 @12.000 -> HPP 12.000.
     */
    public function test_weighted_average_initial_purchase_from_zero_stock(): void
    {
        $result = InventoryCostService::weightedAverage(0, 0, 50, 12000);
        $this->assertEquals(12000.0, $result);
    }

    /**
     * Test weightedAverage saat stok berjalan ada -> HPP rata-rata tertimbang dibulatkan 2 desimal.
     * Uji: stok 50 @12.000, beli 80 @13.000 -> HPP 12.615,38.
     * Perhitungan: (50 * 12000 + 80 * 13000) / (50 + 80) = (600000 + 1040000) / 130 = 1640000 / 130 = 12615.3846... -> 12615.38
     */
    public function test_weighted_average_subsequent_purchase_with_existing_stock(): void
    {
        $result = InventoryCostService::weightedAverage(50, 12000, 80, 13000);
        $this->assertEquals(12615.38, $result);
    }

    /**
     * Test weightedAverage saat stok awal minus -> HPP langsung me-reset ke harga beli baru.
     * Uji: stok -5 @10.000, beli 50 @12.000 -> HPP 12.000.
     */
    public function test_weighted_average_negative_stock_resets_to_purchase_price(): void
    {
        $result = InventoryCostService::weightedAverage(-5, 10000, 50, 12000);
        $this->assertEquals(12000.0, $result);
    }

    /**
     * Test weightedAverage saat qty beli 0 -> HPP tetap HPP lama.
     */
    public function test_weighted_average_zero_incoming_quantity_preserves_cost(): void
    {
        $result = InventoryCostService::weightedAverage(100, 15000, 0, 12000);
        $this->assertEquals(15000.0, $result);
    }
}

