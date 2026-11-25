<?php

namespace App\Services\Accounting;

use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\CashFlow;
use App\Models\Transaction;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerDepositService
{
    /**
     * Menambah saldo deposit pelanggan dan mencatat ke arus kas laci/bank riil (Non-P&L).
     */
    public static function addDeposit(
        int $businessId,
        ?int $branchId,
        int $customerId,
        float $amount,
        string $paymentMethod,
        ?int $transactionId,
        ?string $notes,
        int $createdBy,
        ?Carbon $date = null
    ): CustomerDepositLog {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Nominal deposit harus lebih dari 0.");
        }

        $settlementDate = $date ?? now();

        // Validasi periode akuntansi
        AccountingPeriodGuard::ensurePeriodOpen($businessId, $branchId, $settlementDate);

        return DB::transaction(function () use ($businessId, $branchId, $customerId, $amount, $paymentMethod, $transactionId, $notes, $createdBy, $settlementDate) {
            // Lock customer row untuk mencegah race condition double-spend
            $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

            $balanceBefore = (float) ($customer->deposit_balance ?? 0.0);
            $balanceAfter = round($balanceBefore + $amount, 2);

            $customer->update([
                'deposit_balance' => $balanceAfter
            ]);

            $isCash = strtolower($paymentMethod) === 'cash';

            // Dapatkan kategori pendapatan
            $incomeCat = ExpenseCategory::where('business_id', $businessId)
                ->where('type', 'income')
                ->first();
            $categoryId = $incomeCat ? $incomeCat->id : 1;

            $desc = "Penerimaan Deposit Pelanggan (" . ($customer->name ?? 'Pelanggan #' . $customerId) . ")";
            if (!empty($notes)) {
                $desc .= " - " . $notes;
            }

            // Catat ke CashFlow (Titipan Non-P&L)
            $cashFlow = CashFlow::create([
                'business_id'    => $businessId,
                'branch_id'      => $branchId,
                'type'           => 'income',
                'flow_type'      => 'customer_deposit',
                'reference_type' => 'customer_deposit',
                'reference_id'   => $transactionId,
                'category_id'    => $categoryId,
                'payment_method' => $paymentMethod,
                'funding_source' => $isCash ? 'drawer' : 'owner',
                'affects_drawer' => $isCash ? 1 : 0,
                'amount'         => $amount,
                'description'    => $desc,
                'date'           => $settlementDate,
                'created_by'     => $createdBy,
            ]);

            return CustomerDepositLog::create([
                'business_id'    => $businessId,
                'branch_id'      => $branchId,
                'customer_id'    => $customerId,
                'transaction_id' => $transactionId,
                'cash_flow_id'   => $cashFlow->id,
                'type'           => 'credit',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'payment_method' => $paymentMethod,
                'notes'          => $notes,
                'created_by'     => $createdBy,
            ]);
        });
    }

    /**
     * Memotong saldo deposit untuk transaksi penjualan.
     * Tidak mencatat ke CashFlow karena fisik uangnya sudah masuk saat deposit disetor.
     * Mengembalikan nominal riil deposit yang berhasil dipotong ($actualUsed).
     */
    public static function useDeposit(
        int $businessId,
        ?int $branchId,
        int $customerId,
        float $requestedAmount,
        int $transactionId,
        int $createdBy,
        ?string $notes = null
    ): float {
        if ($requestedAmount <= 0) {
            return 0.0;
        }

        return DB::transaction(function () use ($businessId, $branchId, $customerId, $requestedAmount, $transactionId, $createdBy, $notes) {
            $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

            $balanceBefore = (float) ($customer->deposit_balance ?? 0.0);
            $actualUsed = min($balanceBefore, $requestedAmount);

            if ($actualUsed <= 0) {
                return 0.0;
            }

            $actualUsed = round($actualUsed, 2);
            $balanceAfter = round($balanceBefore - $actualUsed, 2);

            $customer->update([
                'deposit_balance' => $balanceAfter
            ]);

            CustomerDepositLog::create([
                'business_id'    => $businessId,
                'branch_id'      => $branchId,
                'customer_id'    => $customerId,
                'transaction_id' => $transactionId,
                'cash_flow_id'   => null,
                'type'           => 'debit',
                'amount'         => $actualUsed,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'payment_method' => 'deposit',
                'notes'          => $notes ?? ("Penggunaan Deposit Transaksi #" . str_pad($transactionId, 6, '0', STR_PAD_LEFT)),
                'created_by'     => $createdBy,
            ]);

            return $actualUsed;
        });
    }

    /**
     * Mengembalikan saldo deposit pelanggan jika transaksi di-void.
     */
    public static function restoreDepositFromVoid(Transaction $transaction, int $voidedBy): void
    {
        $depositUsed = (float) ($transaction->deposit_used ?? 0.0);
        if ($depositUsed <= 0 || empty($transaction->customer_id)) {
            return;
        }

        DB::transaction(function () use ($transaction, $depositUsed, $voidedBy) {
            $customer = Customer::where('id', $transaction->customer_id)->lockForUpdate()->first();
            if (!$customer) {
                return;
            }

            $balanceBefore = (float) ($customer->deposit_balance ?? 0.0);
            $balanceAfter = round($balanceBefore + $depositUsed, 2);

            $customer->update([
                'deposit_balance' => $balanceAfter
            ]);

            CustomerDepositLog::create([
                'business_id'    => $transaction->business_id,
                'branch_id'      => $transaction->branch_id,
                'customer_id'    => $customer->id,
                'transaction_id' => $transaction->id,
                'cash_flow_id'   => null,
                'type'           => 'void_restore',
                'amount'         => $depositUsed,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'payment_method' => 'deposit',
                'notes'          => "Pengembalian Deposit akibat Void Transaksi #" . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
                'created_by'     => $voidedBy,
            ]);
        });
    }
}
