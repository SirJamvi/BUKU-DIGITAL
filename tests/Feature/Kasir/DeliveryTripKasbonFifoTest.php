<?php

namespace Tests\Feature\Kasir;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Kasir\DashboardService as KasirDashboardService;
use App\Services\ProofUploadService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DeliveryTripKasbonFifoTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;
    protected Branch $branch;
    protected User $admin;
    protected User $kasir;
    protected User $driver;
    protected Customer $efan;
    protected Product $product15kg;
    protected Product $product20kg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'      => 'Admin Trip',
            'email'     => 'admin-trip@test.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->business = Business::create([
            'name'     => 'Adin Crystal Test',
            'owner_id' => $this->admin->id,
        ]);
        $this->admin->update(['business_id' => $this->business->id]);

        $this->branch = Branch::create([
            'business_id' => $this->business->id,
            'name'        => 'Cabang Pusat',
            'code'        => 'CB-01',
            'is_main'     => true,
            'is_active'   => true,
            'manager_id'  => $this->admin->id,
        ]);
        $this->admin->update(['branch_id' => $this->branch->id]);

        $this->kasir = User::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'name'        => 'Kasir Trip',
            'email'       => 'kasir-trip@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'kasir',
            'is_active'   => true,
        ]);
        $this->kasir->syncBranches([$this->branch->id], $this->branch->id);

        $this->driver = User::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'name'        => 'Driver Budi',
            'email'       => 'driver-budi@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'driver',
            'is_active'   => true,
        ]);
        $this->driver->syncBranches([$this->branch->id], $this->branch->id);

        $this->efan = Customer::create([
            'business_id'     => $this->business->id,
            'name'            => 'Efan',
            'phone'           => '081234567890',
            'join_date'       => now()->toDateString(),
            'deposit_balance' => 0,
            'total_purchases' => 0,
            'status'          => 'active',
            'created_by'      => $this->admin->id,
        ]);

        $prodCat = ProductCategory::create([
            'business_id' => $this->business->id,
            'name'        => 'Es Kristal Test',
            'is_active'   => true,
            'created_by'  => $this->admin->id,
        ]);

        $this->product15kg = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $prodCat->id,
            'sku'         => 'TEST-15KG',
            'name'        => 'Es Kristal 15kg',
            'cost_price'  => 7000,
            'base_price'  => 15000,
            'unit'        => 'Ball',
            'is_active'   => true,
            'is_service'  => false,
            'created_by'  => $this->admin->id,
        ]);

        $this->product20kg = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $prodCat->id,
            'sku'         => 'TEST-20KG',
            'name'        => 'Es Kristal 20kg',
            'cost_price'  => 9000,
            'base_price'  => 20000,
            'unit'        => 'Ball',
            'is_active'   => true,
            'is_service'  => false,
            'created_by'  => $this->admin->id,
        ]);

        ExpenseCategory::create([
            'business_id'   => $this->business->id,
            'name'          => 'Pendapatan Penjualan',
            'type'          => 'income',
            'category_type' => 'Operasional',
            'is_cogs'       => 0,
            'is_active'     => 1,
            'created_by'    => $this->admin->id,
        ]);
    }

    protected function createMorningKasbon(float $amount = 15000): Transaction
    {
        $tx = Transaction::create([
            'business_id'            => $this->business->id,
            'branch_id'              => $this->branch->id,
            'type'                   => 'sale',
            'customer_id'            => $this->efan->id,
            'driver_id'              => $this->driver->id,
            'delivery_trip_code'     => 'TRIP-CB-01-PAGI',
            'total_amount'           => $amount,
            'deposit_used'           => 0,
            'net_payable_amount'     => $amount,
            'payment_method'         => 'kasbon',
            'initial_payment_method' => 'kasbon',
            'payment_status'         => 'pending',
            'order_status'           => 'delivered',
            'delivery_status'        => 'delivered',
            'status'                 => 'completed',
            'transaction_date'       => now()->setTime(7, 0),
            'created_by'             => $this->kasir->id,
        ]);

        $tx->details()->create([
            'product_id'  => $this->product15kg->id,
            'quantity'    => 1,
            'cost_price'  => 7000,
            'unit_price'  => $amount,
            'total_price' => $amount,
        ]);

        return $tx;
    }

    protected function createAfternoonTripOrder(float $amount = 20000, string $tripCode = 'TRIP-CB-01-SIANG'): Transaction
    {
        $tx = Transaction::create([
            'business_id'            => $this->business->id,
            'branch_id'              => $this->branch->id,
            'type'                   => 'sale',
            'customer_id'            => $this->efan->id,
            'driver_id'              => $this->driver->id,
            'delivery_trip_code'     => $tripCode,
            'total_amount'           => $amount,
            'deposit_used'           => 0,
            'net_payable_amount'     => $amount,
            'payment_method'         => 'kasbon',
            'initial_payment_method' => 'kasbon',
            'payment_status'         => 'pending',
            'order_status'           => 'delivering',
            'delivery_status'        => 'delivering',
            'status'                 => 'completed',
            'transaction_date'       => now()->setTime(13, 0),
            'created_by'             => $this->kasir->id,
        ]);

        $tx->details()->create([
            'product_id'  => $this->product20kg->id,
            'quantity'    => 1,
            'cost_price'  => 9000,
            'unit_price'  => $amount,
            'total_price' => $amount,
        ]);

        return $tx;
    }

    public function test_efan_50k_cash_settles_morning_kasbon_afternoon_trip_and_deposits_15k(): void
    {
        $morningKasbon = $this->createMorningKasbon(15000);
        $afternoonTrip = $this->createAfternoonTripOrder(20000, 'TRIP-EFAN-50K');

        $response = $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.completeDeliveryTrip'), [
                'trip_code'       => 'TRIP-EFAN-50K',
                'payments'        => [$afternoonTrip->id => 'cash'],
                'order_settled'   => [$afternoonTrip->id => '1'],
                'settled_kasbons' => [$afternoonTrip->id => [$morningKasbon->id]],
                'deposits'        => [$afternoonTrip->id => 15000],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $morningKasbon->refresh();
        $afternoonTrip->refresh();
        $this->efan->refresh();

        $this->assertEquals('paid', $morningKasbon->payment_status);
        $this->assertEquals('cash', $morningKasbon->payment_method);
        $this->assertEquals('paid', $afternoonTrip->payment_status);
        $this->assertEquals('delivered', $afternoonTrip->order_status);
        $this->assertEquals(15000.0, (float) $this->efan->deposit_balance);
        $this->assertEquals(35000.0, (float) $this->efan->total_purchases);

        $this->assertEquals(
            35000.0,
            (float) CashFlow::where('flow_type', 'debt_settlement')
                ->where('payment_method', 'cash')
                ->where('affects_drawer', 1)
                ->sum('amount')
        );
        $this->assertEquals(
            15000.0,
            (float) CashFlow::where('flow_type', 'customer_deposit')
                ->where('payment_method', 'cash')
                ->where('affects_drawer', 1)
                ->sum('amount')
        );
        $this->assertEquals(1, CustomerDepositLog::where('type', 'credit')->where('amount', 15000)->count());

        app(BranchContext::class)->setBranchId($this->branch->id);
        $dashboard = app(KasirDashboardService::class)->getDashboardData($this->kasir->id);
        $this->assertEquals(35000.0, (float) $dashboard['pelunasanCashAmount']);
        $this->assertEquals(15000.0, (float) $dashboard['depositCashAmount']);
        $this->assertEquals(50000.0, (float) $dashboard['myNetCashToday']);
        $this->assertEquals(0.0, (float) $dashboard['totalKasbonAmount']);
    }

    public function test_efan_25k_cash_fifo_settles_morning_kasbon_keeps_afternoon_kasbon_and_deposits_10k(): void
    {
        $morningKasbon = $this->createMorningKasbon(15000);
        $afternoonTrip = $this->createAfternoonTripOrder(20000, 'TRIP-EFAN-25K');

        $response = $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.completeDeliveryTrip'), [
                'trip_code'       => 'TRIP-EFAN-25K',
                'payments'        => [$afternoonTrip->id => 'cash'],
                'order_settled'   => [$afternoonTrip->id => '0'],
                'settled_kasbons' => [$afternoonTrip->id => [$morningKasbon->id]],
                'deposits'        => [$afternoonTrip->id => 10000],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $morningKasbon->refresh();
        $afternoonTrip->refresh();
        $this->efan->refresh();

        $this->assertEquals('paid', $morningKasbon->payment_status);
        $this->assertEquals('pending', $afternoonTrip->payment_status);
        $this->assertEquals('kasbon', $afternoonTrip->payment_method);
        $this->assertEquals('delivered', $afternoonTrip->order_status);
        $this->assertEquals(10000.0, (float) $this->efan->deposit_balance);

        $this->assertEquals(
            15000.0,
            (float) CashFlow::where('flow_type', 'debt_settlement')->sum('amount')
        );
        $this->assertEquals(
            10000.0,
            (float) CashFlow::where('flow_type', 'customer_deposit')->sum('amount')
        );

        app(BranchContext::class)->setBranchId($this->branch->id);
        $dashboard = app(KasirDashboardService::class)->getDashboardData($this->kasir->id);
        $this->assertEquals(25000.0, (float) $dashboard['myNetCashToday']);
        $this->assertEquals(20000.0, (float) $dashboard['totalKasbonAmount']);
    }

    public function test_non_cash_delivery_trip_requires_proof_and_attaches_to_both_orders_and_old_kasbon(): void
    {
        $morningKasbon = $this->createMorningKasbon(15000);
        $afternoonTrip = $this->createAfternoonTripOrder(20000, 'TRIP-EFAN-TF');

        // Tanpa bukti transfer harus gagal validasi
        $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.completeDeliveryTrip'), [
                'trip_code'       => 'TRIP-EFAN-TF',
                'payments'        => [$afternoonTrip->id => 'transfer-bank'],
                'order_settled'   => [$afternoonTrip->id => '1'],
                'settled_kasbons' => [$afternoonTrip->id => [$morningKasbon->id]],
                'deposits'        => [$afternoonTrip->id => 15000],
            ])
            ->assertSessionHasErrors("payment_proofs.{$afternoonTrip->id}");

        $proof = UploadedFile::fake()->image('bukti-trip-tf.jpg');

        $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.completeDeliveryTrip'), [
                'trip_code'       => 'TRIP-EFAN-TF',
                'payments'        => [$afternoonTrip->id => 'transfer-bank'],
                'order_settled'   => [$afternoonTrip->id => '1'],
                'settled_kasbons' => [$afternoonTrip->id => [$morningKasbon->id]],
                'deposits'        => [$afternoonTrip->id => 15000],
                'payment_proofs'  => [$afternoonTrip->id => $proof],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $morningKasbon->refresh();
        $afternoonTrip->refresh();
        $this->efan->refresh();

        $this->assertNotEmpty($afternoonTrip->payment_proof);
        $this->assertEquals($afternoonTrip->payment_proof, $morningKasbon->payment_proof);
        $this->assertEquals(15000.0, (float) $this->efan->deposit_balance);

        // Bersihkan file bukti dummy
        ProofUploadService::deleteProof($afternoonTrip->payment_proof);

        $this->assertEquals(0.0, (float) CashFlow::where('affects_drawer', 1)->sum('amount'));
        $this->assertEquals(35000.0, (float) CashFlow::where('flow_type', 'debt_settlement')->where('affects_drawer', 0)->sum('amount'));
        $this->assertEquals(15000.0, (float) CashFlow::where('flow_type', 'customer_deposit')->where('affects_drawer', 0)->sum('amount'));
    }

    public function test_delivery_receipt_displays_previous_kasbon_and_combined_collectible(): void
    {
        $this->createMorningKasbon(15000);
        $this->createAfternoonTripOrder(20000, 'TRIP-EFAN-SJ');

        $response = $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get(route('kasir.pos.deliveryReceipt', 'TRIP-EFAN-SJ'));

        $response->assertStatus(200);
        $response->assertSee('15.000');
        $response->assertSee('35.000');
        $response->assertSee('Total Tagih Gabungan');
    }
}
