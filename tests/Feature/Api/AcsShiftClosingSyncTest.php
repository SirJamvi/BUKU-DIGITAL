<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
use App\Models\CashierShiftClosing;
use App\Models\User;
use App\Services\Admin\FinancialService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcsShiftClosingSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;
    protected Branch $branch;
    protected User $cashier;
    protected string $syncSecret = 'test-acs-sync-secret-key-123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.sync.secret' => $this->syncSecret]);

        $this->business = Business::create(['name' => 'Bisnis Es Kristal Test']);
        $this->branch = Branch::create([
            'business_id' => $this->business->id,
            'name'        => 'Cabang Pusat Test',
            'code'        => 'CB-PUSAT',
            'is_main'     => true,
            'is_active'   => true,
        ]);

        $this->cashier = User::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'name'        => 'Kasir Test',
            'email'       => 'kasir.test@example.com',
            'password'    => bcrypt('password'),
            'role'        => 'kasir',
            'is_active'   => true,
        ]);
    }

    public function test_unauthorized_access_rejected_when_sync_secret_missing_or_invalid(): void
    {
        $response = $this->postJson('/api/v1/sync/acs/shift-closing', [
            'external_reference' => 'ACS-SUB-101',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'status'  => 'error',
            'message' => 'Unauthorized: Invalid or missing X-Sync-Secret / X-ACS-Key header.',
        ]);
    }

    public function test_sync_shift_closing_with_cash_handover_success_and_idempotent(): void
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        // 1. Setup saldo awal Rp 200.000
        CashierOpeningBalance::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'user_id'     => $this->cashier->id,
            'amount'      => 200000,
            'date'        => $today,
        ]);

        // 2. Setup penjualan tunai Rp 500.000
        CashFlow::create([
            'business_id'    => $this->business->id,
            'branch_id'      => $this->branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'category_id'    => null,
            'amount'         => 500000,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'description'    => 'Penjualan tunai meja',
            'date'           => $today,
            'created_by'     => $this->cashier->id,
        ]);

        // Expected drawer cash seharusnya = 200.000 + 500.000 = 700.000
        // Kasir menghitung fisik Rp 655.000 (tekor Rp 45.000)
        // Disetor ke Owner Rp 455.000 -> sisa kas di laci untuk besok = 655.000 - 455.000 = 200.000

        $payload = [
            'external_reference'   => 'ACS-SUB-999',
            'business_id'          => $this->business->id,
            'branch_id'            => $this->branch->id,
            'user_id'              => $this->cashier->id,
            'date'                 => $today,
            'shift_code'           => 'sore',
            'actual_cash_counted'  => 655000,
            'cash_handover_amount' => 455000,
            'handover_receiver'    => 'Pak Hendra (Owner)',
            'notes'                => 'Penutupan shift sore ACS',
        ];

        // 3. Request pertama
        $response1 = $this->withHeaders([
            'X-Sync-Secret' => $this->syncSecret,
        ])->postJson('/api/v1/sync/acs/shift-closing', $payload);

        $response1->assertStatus(200);
        $response1->assertJsonPath('status', 'success');
        $response1->assertJsonPath('data.expected_drawer_cash', 700000);
        $response1->assertJsonPath('data.actual_cash_counted', 655000);
        $response1->assertJsonPath('data.variance', -45000);
        $response1->assertJsonPath('data.cash_handover_amount', 455000);
        $response1->assertJsonPath('data.ending_cash_for_next_shift', 200000);
        $response1->assertJsonPath('data.is_idempotent', false);

        // Verifikasi tabel cashier_shift_closings
        $this->assertDatabaseHas('cashier_shift_closings', [
            'external_reference'         => 'ACS-SUB-999',
            'expected_drawer_cash'       => 700000,
            'actual_cash_counted'        => 655000,
            'variance'                   => -45000,
            'cash_handover_amount'       => 455000,
            'ending_cash_for_next_shift' => 200000,
            'handover_receiver'          => 'Pak Hendra (Owner)',
        ]);

        // Verifikasi tabel cash_flow mencatat handover shift closing
        $this->assertDatabaseHas('cash_flow', [
            'flow_type'      => 'cash_handover',
            'reference_type' => 'shift_closing',
            'reference_id'   => 'ACS-SUB-999',
            'amount'         => 455000,
            'affects_drawer' => 1,
            'funding_source' => 'drawer',
        ]);

        $this->assertEquals(1, CashierShiftClosing::where('external_reference', 'ACS-SUB-999')->count());
        $this->assertEquals(1, CashFlow::where('reference_id', 'ACS-SUB-999')->count());

        // 4. Request kedua (Idempotency check)
        $response2 = $this->withHeaders([
            'X-Sync-Secret' => $this->syncSecret,
        ])->postJson('/api/v1/sync/acs/shift-closing', $payload);

        $response2->assertStatus(200);
        $response2->assertJsonPath('data.is_idempotent', true);
        $response2->assertJsonPath('data.ending_cash_for_next_shift', 200000);

        // Pastikan tidak ada data ganda
        $this->assertEquals(1, CashierShiftClosing::where('external_reference', 'ACS-SUB-999')->count());
        $this->assertEquals(1, CashFlow::where('reference_id', 'ACS-SUB-999')->count());

        // 5. Verifikasi Laba Rugi FinancialService tetap utuh (handover Rp 455.000 BUKAN beban P&L)
        $financialService = app(FinancialService::class);
        $report = $financialService->getIncomeStatementReport([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'start_date'  => $today,
            'end_date'    => $today,
        ]);

        $this->assertEquals(0, $report['total_expense'], 'Beban operasional P&L tidak boleh terpotong oleh cash handover.');
    }
}
