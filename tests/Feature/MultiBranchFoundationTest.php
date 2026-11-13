<?php

namespace Tests\Feature;

use App\Exceptions\BranchRequiredException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\TemporaryOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiBranchFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $admin;
    protected User $cashier;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Bisnis Tenant
        $this->business = Business::create([
            'name' => 'Bisnis Multi Cabang Test',
        ]);

        // 2. Setup Cabang Pusat (A) dan Cabang Kedua (B)
        $this->branchA = Branch::create([
            'business_id' => $this->business->id,
            'name'        => 'Cabang Jakarta Pusat',
            'code'        => 'PUSAT',
            'is_main'     => true,
            'is_active'   => true,
        ]);

        $this->branchB = Branch::create([
            'business_id' => $this->business->id,
            'name'        => 'Cabang Surabaya',
            'code'        => 'SBY',
            'is_main'     => false,
            'is_active'   => true,
        ]);

        // 3. Setup Admin (Holding / Pusat)
        $this->admin = User::create([
            'business_id' => $this->business->id,
            'name'        => 'Admin Holding',
            'email'       => 'admin@holding.test',
            'password'    => bcrypt('password'),
            'role'        => 'admin',
            'is_active'   => true,
            'branch_id'   => null,
        ]);

        // 4. Setup Kasir (Terikat ke Cabang A)
        $this->cashier = User::create([
            'business_id' => $this->business->id,
            'name'        => 'Kasir Cabang A',
            'email'       => 'kasir@cabanga.test',
            'password'    => bcrypt('password'),
            'role'        => 'kasir',
            'is_active'   => true,
        ]);
        $this->cashier->syncBranches([$this->branchA->id], $this->branchA->id);

        // 5. Setup Customer Shared
        $this->customer = Customer::create([
            'business_id' => $this->business->id,
            'name'        => 'Pelanggan Umum',
            'phone'       => '081234567890',
            'address'     => 'Jl. Merdeka No. 1',
        ]);
    }

    /**
     * Test 1: Isolated Branch Query
     * Kasir di cabang A hanya dapat melihat data cabang A, data cabang B tidak terlihat.
     */
    public function test_isolated_branch_query_staff_sees_only_own_branch_data(): void
    {
        $context = app(BranchContext::class);

        // Buat data transaksi di cabang A dan B via unscoped
        $context->runUnscoped(function () {
            Transaction::create([
                'business_id'      => $this->business->id,
                'branch_id'        => $this->branchA->id,
                'customer_id'      => $this->customer->id,
                'total_amount'     => 50000,
                'payment_method'   => 'cash',
                'status'           => 'completed',
                'transaction_date' => now(),
            ]);

            Transaction::create([
                'business_id'      => $this->business->id,
                'branch_id'        => $this->branchB->id,
                'customer_id'      => $this->customer->id,
                'total_amount'     => 75000,
                'payment_method'   => 'cash',
                'status'           => 'completed',
                'transaction_date' => now(),
            ]);
        });

        // Set konteks ke cabang A
        $context->setBranch($this->branchA->id, $this->business->id);

        $visibleTransactions = Transaction::all();

        $this->assertCount(1, $visibleTransactions);
        $this->assertEquals($this->branchA->id, $visibleTransactions->first()->branch_id);
        $this->assertEquals(50000, (float) $visibleTransactions->first()->total_amount);
    }

    /**
     * Test 2: Consolidated Admin Read
     * Admin dalam mode konsolidasi ('all') dapat membaca seluruh data dari semua cabang.
     */
    public function test_consolidated_admin_can_read_all_branches(): void
    {
        $context = app(BranchContext::class);

        $context->runUnscoped(function () {
            Transaction::create([
                'business_id'      => $this->business->id,
                'branch_id'        => $this->branchA->id,
                'customer_id'      => $this->customer->id,
                'total_amount'     => 10000,
                'payment_method'   => 'cash',
                'status'           => 'completed',
                'transaction_date' => now(),
            ]);

            Transaction::create([
                'business_id'      => $this->business->id,
                'branch_id'        => $this->branchB->id,
                'customer_id'      => $this->customer->id,
                'total_amount'     => 20000,
                'payment_method'   => 'cash',
                'status'           => 'completed',
                'transaction_date' => now(),
            ]);
        });

        // Mode Konsolidasi Pusat
        $context->setConsolidated($this->business->id);

        $allTransactions = Transaction::all();

        $this->assertCount(2, $allTransactions);
    }

    /**
     * Test 3: Consolidated Admin Write Prohibition
     * Menulis data pada tabel ber-branch saat dalam mode konsolidasi harus melempar BranchRequiredException.
     */
    public function test_consolidated_admin_write_prohibition_throws_exception(): void
    {
        $this->expectException(BranchRequiredException::class);

        $context = app(BranchContext::class);
        $context->setConsolidated($this->business->id);

        Transaction::create([
            'business_id'      => $this->business->id,
            'customer_id'      => $this->customer->id,
            'total_amount'     => 30000,
            'payment_method'   => 'cash',
            'status'           => 'completed',
            'transaction_date' => now(),
        ]);
    }

    /**
     * Test 4: Auto-population of branch_id
     * Staf yang aktif di cabang A secara otomatis mengisi branch_id = cabang A saat membuat data.
     */
    public function test_auto_population_of_branch_id_on_staff_create(): void
    {
        $context = app(BranchContext::class);
        $context->setBranch($this->branchA->id, $this->business->id);

        $tx = Transaction::create([
            'business_id'      => $this->business->id,
            'customer_id'      => $this->customer->id,
            'total_amount'     => 45000,
            'payment_method'   => 'cash',
            'status'           => 'completed',
            'transaction_date' => now(),
        ]);

        $this->assertEquals($this->branchA->id, $tx->branch_id);
    }

    /**
     * Test 5: Fail-closed Mode
     * Mode NONE menghasilkan query kosong (1 = 0) dan melempar BranchRequiredException saat menyimpan data baru.
     */
    public function test_fail_closed_mode_returns_empty_and_blocks_writes(): void
    {
        $context = app(BranchContext::class);

        $context->runUnscoped(function () {
            Transaction::create([
                'business_id'      => $this->business->id,
                'branch_id'        => $this->branchA->id,
                'customer_id'      => $this->customer->id,
                'total_amount'     => 50000,
                'payment_method'   => 'cash',
                'status'           => 'completed',
                'transaction_date' => now(),
            ]);
        });

        // Mode NONE
        $context->setNone();

        $this->assertCount(0, Transaction::all());

        $this->expectException(BranchRequiredException::class);
        Transaction::create([
            'business_id'      => $this->business->id,
            'customer_id'      => $this->customer->id,
            'total_amount'     => 25000,
            'payment_method'   => 'cash',
            'status'           => 'completed',
            'transaction_date' => now(),
        ]);
    }

    /**
     * Test 6: Multi-branch Staff Session Switching
     * Staf yang memiliki 2 cabang di pivot dapat berganti cabang aktif via BranchSwitchController.
     */
    public function test_multi_branch_staff_can_switch_active_branch(): void
    {
        // Berikan akses cabang A dan B ke kasir
        $this->cashier->syncBranches([$this->branchA->id, $this->branchB->id], $this->branchA->id);

        $response = $this->actingAs($this->cashier)
            ->get(route('branch.switch', ['branchId' => $this->branchB->id]));

        $response->assertSessionHas('active_branch_id', $this->branchB->id);
    }

    /**
     * Test 7: Unassigned Staff Prevention
     * Staf tanpa cabang di pivot ditolak saat mengakses halaman terproteksi.
     */
    public function test_unassigned_staff_is_rejected_and_logged_out(): void
    {
        $unassignedUser = User::create([
            'business_id' => $this->business->id,
            'name'        => 'Kasir Tanpa Cabang',
            'email'       => 'floating@kasir.test',
            'password'    => bcrypt('password'),
            'role'        => 'kasir',
            'is_active'   => true,
            'branch_id'   => null,
        ]);

        $response = $this->actingAs($unassignedUser)
            ->get(route('kasir.dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Test 8: Stateless API Sanctum with X-Branch-Id Header
     * Request API mobile kasir dengan header X-Branch-Id memvalidasi hak akses dan mengisolasi antrean AI.
     */
    public function test_stateless_api_sanctum_validates_x_branch_id(): void
    {
        Sanctum::actingAs($this->cashier);

        // Buat temporary order di cabang A dan cabang B
        app(BranchContext::class)->runUnscoped(function () {
            TemporaryOrder::create([
                'business_id'  => $this->business->id,
                'branch_id'    => $this->branchA->id,
                'customer_id'  => $this->customer->id,
                'sender_phone' => '081234567890',
                'jumlah_ball'  => 5,
                'status'       => 'validated',
            ]);

            TemporaryOrder::create([
                'business_id'  => $this->business->id,
                'branch_id'    => $this->branchB->id,
                'customer_id'  => $this->customer->id,
                'sender_phone' => '081298765432',
                'jumlah_ball'  => 10,
                'status'       => 'validated',
            ]);
        });

        // 1. Request dengan X-Branch-Id cabang A (berhak) -> hanya dapat data cabang A
        $responseA = $this->getJson('/api/v1/kasir/ai-orders', [
            'X-Branch-Id' => (string) $this->branchA->id,
        ]);

        $responseA->assertStatus(200);
        $responseA->assertJsonCount(1, 'data');
        $this->assertEquals('081234567890', $responseA->json('data.0.sender_phone'));

        // 2. Request dengan X-Branch-Id cabang B (tidak berhak) -> 403 Forbidden
        $responseB = $this->getJson('/api/v1/kasir/ai-orders', [
            'X-Branch-Id' => (string) $this->branchB->id,
        ]);

        $responseB->assertStatus(403);
    }
}

