<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Inventory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Services\Accounting\CogsCalculator;
use App\Services\Kasir\TransactionService;
use App\Services\Admin\InventoryService as AdminInventoryService;
use App\Http\Controllers\Kasir\InventoryController as KasirInventoryController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class CogsAndInventoryProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup default session
        Session::start();
    }

    /**
     * Uji 1: Product::isService() bernilai true untuk ongkir / ongkos kirim / unit 'Jalan'
     */
    public function test_product_is_service_detection(): void
    {
        $service1 = new Product(['name' => 'Ongkos Kirim Area 1', 'unit' => 'Trip']);
        $service2 = new Product(['name' => 'Biaya Ongkir Luar Kota', 'unit' => 'Trip']);
        $service3 = new Product(['name' => 'Jasa Angkut', 'unit' => 'Jalan']);
        $service4 = new Product(['name' => 'Custom Delivery', 'unit' => 'jalan']);
        $nonService = new Product(['name' => 'Es Kristal 20 KG', 'unit' => 'Ball']);

        $this->assertTrue($service1->isService());
        $this->assertTrue($service2->isService());
        $this->assertTrue($service3->isService());
        $this->assertTrue($service4->isService());
        $this->assertFalse($nonService->isService());
    }

    /**
     * Uji 2: CogsCalculator
     * - HPP tidak berubah saat master berubah
     * - Ongkir tidak masuk HPP
     * - Baris NULL menaikkan missing_cogs_count & missing_cogs_revenue
     * - strict=true menghasilkan 0 untuk NULL
     */
    public function test_cogs_calculator_rules(): void
    {
        $businessId = 1;

        // Produk fisik
        $productPhysical = Product::create([
            'business_id' => $businessId,
            'name' => 'Es Kristal 20 KG',
            'cost_price' => 15000,
            'selling_price' => 20000,
            'unit' => 'Ball',
            'is_active' => true,
        ]);

        // Produk jasa
        $productDelivery = Product::create([
            'business_id' => $businessId,
            'name' => 'Ongkos Kirim',
            'cost_price' => 0,
            'selling_price' => 10000,
            'unit' => 'Jalan',
            'is_active' => true,
        ]);

        // Transaksi 1: Fisik dengan snapshot 12.000 + Ongkir
        $trx1 = Transaction::create([
            'business_id' => $businessId,
            'type' => 'sale',
            'total_amount' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'transaction_date' => now(),
        ]);

        // Detail 1: Fisik qty 2, snapshot cost_price 12.000 (bukan 15.000)
        TransactionDetail::create([
            'transaction_id' => $trx1->id,
            'product_id' => $productPhysical->id,
            'quantity' => 2,
            'cost_price' => 12000,
            'unit_price' => 20000,
            'total_price' => 40000,
        ]);

        // Detail 2: Ongkir qty 1
        TransactionDetail::create([
            'transaction_id' => $trx1->id,
            'product_id' => $productDelivery->id,
            'quantity' => 1,
            'cost_price' => 0,
            'unit_price' => 10000,
            'total_price' => 10000,
        ]);

        // Transaksi 2: Transaksi lama dengan cost_price NULL
        $trx2 = Transaction::create([
            'business_id' => $businessId,
            'type' => 'sale',
            'total_amount' => 20000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'transaction_date' => now(),
        ]);

        TransactionDetail::create([
            'transaction_id' => $trx2->id,
            'product_id' => $productPhysical->id,
            'quantity' => 1,
            'cost_price' => null, // NULL snapshot
            'unit_price' => 20000,
            'total_price' => 20000,
        ]);

        // Ubah master cost_price menjadi 30.000
        $productPhysical->update(['cost_price' => 30000]);

        // Uji non-strict (default): fallback ke master saat NULL
        Config::set('accounting.strict_cogs', false);
        $transactions = Transaction::whereIn('id', [$trx1->id, $trx2->id])->with('details.product')->get();
        $calc1 = CogsCalculator::calculateForTransactions($transactions);

        // Trx 1 COGS = 2 * 12000 = 24.000 (tidak terpengaruh master yang berubah jadi 30.000)
        // Trx 2 COGS = 1 * 30000 (fallback master) = 30.000
        // Total COGS = 54.000
        $this->assertEquals(24000.0, $transactions->firstWhere('id', $trx1->id)->cogs);
        $this->assertEquals(1, $calc1['missing_cogs_count']);
        $this->assertEquals(20000.0, $calc1['missing_cogs_revenue']);
        $this->assertEquals(54000.0, $calc1['total_cogs']);

        // Uji strict_cogs = true: NULL menghasilkan COGS 0
        Config::set('accounting.strict_cogs', true);
        $transactionsStrict = Transaction::whereIn('id', [$trx1->id, $trx2->id])->with('details.product')->get();
        $calcStrict = CogsCalculator::calculateForTransactions($transactionsStrict);

        // Trx 1 COGS = 24.000
        // Trx 2 COGS = 0 (karena strict)
        // Total COGS = 24.000
        $this->assertEquals(24000.0, $calcStrict['total_cogs']);
        $this->assertEquals(1, $calcStrict['missing_cogs_count']);
    }

    /**
     * Uji 3: Pecah Ball source == target ditolak pada Service
     */
    public function test_pecah_ball_source_equals_target_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Produk sumber dan target pecahan tidak boleh sama.');

        $service = new AdminInventoryService();
        $service->breakUnit([
            'business_id' => 1,
            'from_product_id' => 11,
            'to_product_id' => 11, // SAMA
            'quantity_to_break' => 1,
            'result_quantity' => 2,
        ]);
    }

    /**
     * Uji 4: TransactionService update mempertahankan snapshot lama per baris FIFO
     */
    public function test_transaction_service_update_preserves_snapshot_fifo_queue(): void
    {
        $businessId = 1;
        $user = User::factory()->create(['business_id' => $businessId]);
        $this->actingAs($user);

        $product = Product::create([
            'business_id' => $businessId,
            'name' => 'Es Kristal 20 KG',
            'cost_price' => 25000, // Master saat ini 25.000
            'selling_price' => 30000,
            'unit' => 'Ball',
            'is_active' => true,
        ]);

        Inventory::create([
            'business_id' => $businessId,
            'product_id' => $product->id,
            'current_stock' => 100,
            'minimum_stock' => 10,
        ]);

        $trx = Transaction::create([
            'business_id' => $businessId,
            'type' => 'sale',
            'total_amount' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'transaction_date' => now(),
            'created_by' => $user->id,
        ]);

        // Dua detail untuk produk yang sama dengan snapshot berbeda (12.000 dan 13.000)
        TransactionDetail::create([
            'transaction_id' => $trx->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 12000,
            'unit_price' => 25000,
            'total_price' => 25000,
        ]);

        TransactionDetail::create([
            'transaction_id' => $trx->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 13000,
            'unit_price' => 25000,
            'total_price' => 25000,
        ]);

        $service = new TransactionService();

        // Update transaksi: qty baris pertama diubah jadi 3, baris kedua tetap 1
        $updateData = [
            'total_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3, // qty naik
                    'unit_price' => 25000,
                    'total_price' => 75000,
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 25000,
                    'total_price' => 25000,
                ],
            ]
        ];

        $updatedTrx = $service->updateTransaction($trx->fresh(), $updateData);
        $details = $updatedTrx->details()->orderBy('id')->get();

        // Verifikasi: detail pertama tetap 12.000, detail kedua tetap 13.000, BUKAN master 25.000
        $this->assertEquals(12000.0, (float) $details[0]->cost_price);
        $this->assertEquals(13000.0, (float) $details[1]->cost_price);
    }

    /**
     * Uji 5: storeStock
     * - confirm=0: flash warning_confirmation, HPP tidak berubah
     * - confirm=1: HPP moving average 17142.08 dari stok 1 @ 12852.46 + 5 @ 18000, notes '[KONFIRMASI DITERIMA]'
     */
    public function test_kasir_inventory_controller_store_stock_deviation_and_confirmation(): void
    {
        $businessId = 1;
        $user = User::factory()->create(['business_id' => $businessId, 'role' => 'kasir']);
        $this->actingAs($user);

        $supplier = Supplier::create([
            'business_id' => $businessId,
            'name' => 'Pabrik Es Sejahtera',
            'is_active' => true,
        ]);

        $product = Product::create([
            'business_id' => $businessId,
            'name' => 'Es Kristal 20 KG',
            'cost_price' => 12852.46,
            'selling_price' => 20000,
            'unit' => 'Ball',
            'is_active' => true,
        ]);

        $inventory = Inventory::create([
            'business_id' => $businessId,
            'product_id' => $product->id,
            'current_stock' => 1,
            'minimum_stock' => 5,
        ]);

        // Hubungkan supplier dengan pivot
        $product->suppliers()->attach($supplier->id, [
            'business_id' => $businessId,
            'purchase_price' => 12852.46,
            'is_primary' => true,
        ]);

        $controller = new KasirInventoryController();

        // 1. Request dengan confirm = 0 dan harga beli 18.000 (lonjakan > 40% dari 12.852,46)
        $requestNoConfirm = new Request([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 5,
            'purchase_price' => 18000,
            'payment_method' => 'cash',
            'confirm' => 0,
        ]);
        $requestNoConfirm->setUserResolver(fn() => $user);

        $response1 = $controller->storeStock($requestNoConfirm);

        // HPP tidak boleh berubah
        $this->assertEquals(12852.46, (float) $product->fresh()->cost_price);
        $this->assertTrue(session()->has('warning_confirmation'));

        // 2. Request dengan confirm = 1 (konfirmasi kasir diterima)
        $requestConfirmed = new Request([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 5,
            'purchase_price' => 18000,
            'payment_method' => 'cash',
            'confirm' => 1,
        ]);
        $requestConfirmed->setUserResolver(fn() => $user);

        $response2 = $controller->storeStock($requestConfirmed);

        // Rumus: ((1 * 12852.46) + (5 * 18000)) / (1 + 5) = (12852.46 + 90000) / 6 = 102852.46 / 6 = 17142.0766... -> 17142.08
        $this->assertEquals(17142.08, (float) $product->fresh()->cost_price);

        // Cek stock_movements notes memuat '[KONFIRMASI DITERIMA]'
        $movement = DB::table('stock_movements')
            ->where('product_id', $product->id)
            ->where('business_id', $businessId)
            ->latest('id')
            ->first();

        $this->assertNotNull($movement);
        $this->assertStringContainsString('[KONFIRMASI DITERIMA]', $movement->notes);
    }
}
