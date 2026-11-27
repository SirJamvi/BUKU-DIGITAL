<?php

namespace App\Services\Accounting;

use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\UserActivityLog;
use App\Support\BranchCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        ?Carbon $date = null,
        ?int $cashFlowCreatedBy = null,
        ?bool $affectsDrawerOverride = null
    ): CustomerDepositLog {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Nominal deposit harus lebih dari 0.");
        }

        $settlementDate = $date ?? now();

        // Validasi periode akuntansi
        AccountingPeriodGuard::ensurePeriodOpen($businessId, $branchId, $settlementDate);

        return DB::transaction(function () use (
            $businessId,
            $branchId,
            $customerId,
            $amount,
            $paymentMethod,
            $transactionId,
            $notes,
            $createdBy,
            $settlementDate,
            $cashFlowCreatedBy,
            $affectsDrawerOverride
        ) {
            // Lock customer row untuk mencegah race condition double-spend
            $customer = Customer::withoutGlobalScopes()
                ->where('id', $customerId)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = (float) ($customer->deposit_balance ?? 0.0);
            $balanceAfter = round($balanceBefore + $amount, 2);

            $customer->update([
                'deposit_balance' => $balanceAfter,
            ]);

            $isCash = strtolower($paymentMethod) === 'cash';
            $affectsDrawer = $affectsDrawerOverride !== null
                ? ($affectsDrawerOverride ? 1 : 0)
                : ($isCash ? 1 : 0);

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
                'is_cogs'        => false,
                'payment_method' => $paymentMethod,
                'funding_source' => $affectsDrawer ? 'drawer' : 'owner',
                'affects_drawer' => $affectsDrawer,
                'amount'         => $amount,
                'description'    => $desc,
                'date'           => $settlementDate,
                'created_by'     => $cashFlowCreatedBy ?? $createdBy,
            ]);

            $log = CustomerDepositLog::create([
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

            return $log;
        });
    }

    /**
     * Menambah Saldo Awal / Migrasi Titipan Lama pelanggan oleh Admin
     * tanpa mencatat CashFlow baru agar tidak mengganggu kas laci / mutasi hari ini.
     */
    public static function addOpeningDeposit(
        int $businessId,
        ?int $branchId,
        int $customerId,
        float $amount,
        ?string $notes,
        int $createdBy,
        ?Carbon $date = null
    ): CustomerDepositLog {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Nominal saldo awal deposit harus lebih dari 0.");
        }

        $targetDate = $date ?? now();
        AccountingPeriodGuard::ensurePeriodOpen($businessId, $branchId, $targetDate);

        return DB::transaction(function () use ($businessId, $branchId, $customerId, $amount, $notes, $createdBy, $targetDate) {
            $customer = Customer::withoutGlobalScopes()
                ->where('id', $customerId)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = (float) ($customer->deposit_balance ?? 0.0);
            $balanceAfter = round($balanceBefore + $amount, 2);

            $customer->update([
                'deposit_balance' => $balanceAfter,
            ]);

            $fullNotes = '[Saldo Awal / Titipan Lama]' . (!empty($notes) ? ' ' . trim($notes) : '');

            $log = CustomerDepositLog::create([
                'business_id'    => $businessId,
                'branch_id'      => $branchId,
                'customer_id'    => $customerId,
                'transaction_id' => null,
                'cash_flow_id'   => null,
                'type'           => 'credit',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'payment_method' => 'opening_balance',
                'notes'          => $fullNotes,
                'created_by'     => $createdBy,
            ]);

            if ($targetDate->format('Y-m-d') !== now()->format('Y-m-d')) {
                $log->timestamps = false;
                $log->created_at = $targetDate;
                $log->updated_at = $targetDate;
                $log->save();
            }

            UserActivityLog::create([
                'business_id' => $businessId,
                'branch_id'   => $branchId,
                'user_id'     => $createdBy,
                'action'      => 'add_opening_deposit',
                'module'      => 'customer_deposit',
                'details'     => "Saldo Awal Deposit #{$log->id} ({$customer->name}) +Rp " . number_format($amount, 0, ',', '.') . " | {$fullNotes}",
                'ip_address'  => request()?->ip(),
            ]);

            BranchCache::flush($businessId);

            return $log;
        });
    }

    /**
     * Membatalkan (Void) setoran deposit yang salah input oleh kasir/admin menggunakan jurnal pembalik (Contra-Entry)
     * sesuai pedoman finance FMCG (Zero Hard-Delete, audit trail utuh, anti-saldo minus, dan kunci periode tutup buku).
     */
    public static function reverseDeposit(
        int $businessId,
        int $depositLogId,
        string $reason,
        int $adminUserId,
        bool $logActivity = true
    ): CustomerDepositLog {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new \InvalidArgumentException("Alasan pembatalan deposit wajib diisi.");
        }

        return DB::transaction(function () use ($businessId, $depositLogId, $cleanReason, $adminUserId, $logActivity) {
            $originalLog = CustomerDepositLog::withoutGlobalScopes()
                ->where('id', $depositLogId)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($originalLog->type !== 'credit') {
                throw new \RuntimeException("Hanya mutasi setoran deposit (kredit) yang dapat dibatalkan oleh Admin.");
            }

            if ($originalLog->isReversed()) {
                throw new \RuntimeException("Setoran deposit #{$originalLog->id} sudah pernah dibatalkan sebelumnya.");
            }

            $originalCashFlow = $originalLog->cash_flow_id
                ? CashFlow::withoutGlobalScopes()->lockForUpdate()->find($originalLog->cash_flow_id)
                : null;

            $periodDate = ($originalCashFlow && $originalCashFlow->date)
                ? Carbon::parse($originalCashFlow->date)
                : ($originalLog->created_at ?? now());

            AccountingPeriodGuard::ensurePeriodOpen(
                $businessId,
                $originalLog->branch_id ? (int) $originalLog->branch_id : null,
                $periodDate
            );

            $customer = Customer::withoutGlobalScopes()
                ->where('id', $originalLog->customer_id)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = round((float) ($customer->deposit_balance ?? 0.0), 2);
            $reverseAmount = round((float) $originalLog->amount, 2);

            if ($balanceBefore + 0.001 < $reverseAmount) {
                $recentDebits = CustomerDepositLog::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->where('customer_id', $customer->id)
                    ->where('type', 'debit')
                    ->where('id', '>', $originalLog->id)
                    ->whereNotNull('transaction_id')
                    ->orderBy('id', 'desc')
                    ->limit(5)
                    ->pluck('transaction_id')
                    ->map(fn ($txId) => '#' . str_pad((string) $txId, 6, '0', STR_PAD_LEFT))
                    ->unique()
                    ->values()
                    ->all();

                $txHint = !empty($recentDebits)
                    ? ' Transaksi pemakaian terkait: ' . implode(', ', $recentDebits) . '.'
                    : '';

                throw new \RuntimeException(
                    "Pembatalan ditolak (Anti Saldo Minus): Saldo deposit {$customer->name} saat ini Rp "
                    . number_format($balanceBefore, 0, ',', '.')
                    . " lebih kecil dari nominal setoran yang akan dibatalkan (Rp "
                    . number_format($reverseAmount, 0, ',', '.')
                    . "). Deposit ini sudah terpakai untuk belanja/kasbon — void transaksi pemakaiannya terlebih dahulu."
                    . $txHint
                );
            }

            $balanceAfter = round(max(0.0, $balanceBefore - $reverseAmount), 2);
            $customer->update([
                'deposit_balance' => $balanceAfter,
            ]);

            $contraCashFlow = null;
            if ($originalCashFlow) {
                $origMethod = $originalCashFlow->payment_method ?: ($originalLog->payment_method ?: 'cash');
                $origAffectsDrawer = $originalCashFlow->affects_drawer ? 1 : 0;

                $contraCashFlow = CashFlow::create([
                    'business_id'    => (int) $originalCashFlow->business_id,
                    'branch_id'      => $originalCashFlow->branch_id ?? $originalLog->branch_id,
                    'type'           => 'expense',
                    'flow_type'      => 'deposit_refund',
                    'reference_type' => 'customer_deposit_reversal',
                    'reference_id'   => (string) $originalLog->id,
                    'category_id'    => $originalCashFlow->category_id ?: 1,
                    'is_cogs'        => false,
                    'payment_method' => $origMethod,
                    'funding_source' => $originalCashFlow->funding_source ?: ($origAffectsDrawer ? 'drawer' : 'owner'),
                    'affects_drawer' => $origAffectsDrawer,
                    'amount'         => $reverseAmount,
                    'description'    => "Pembatalan / Void Deposit #{$originalLog->id} ({$customer->name}) oleh Admin: {$cleanReason}",
                    'date'           => $originalCashFlow->date,
                    // Atribusikan ke kasir pembuat setoran asal agar saldo laci kasir & shift closing kembali impas (Rp 0)
                    'created_by'     => $originalCashFlow->created_by ?: ($originalLog->created_by ?: $adminUserId),
                ]);
            }

            $contraLog = CustomerDepositLog::create([
                'business_id'     => $businessId,
                'branch_id'       => $originalLog->branch_id,
                'customer_id'     => $customer->id,
                'transaction_id'  => $originalLog->transaction_id,
                'cash_flow_id'    => $contraCashFlow?->id,
                'type'            => 'refund',
                'amount'          => $reverseAmount,
                'balance_before'  => $balanceBefore,
                'balance_after'   => $balanceAfter,
                'payment_method'  => $originalLog->payment_method,
                'notes'           => "[VOID Deposit #{$originalLog->id}] {$cleanReason}",
                'reverses_log_id' => $originalLog->id,
                'created_by'      => $adminUserId,
            ]);

            $originalLog->update([
                'reversed_at'        => now(),
                'reversed_by'        => $adminUserId,
                'reversal_reason'    => $cleanReason,
                'reversed_by_log_id' => $contraLog->id,
            ]);

            if ($logActivity) {
                UserActivityLog::create([
                    'business_id' => $businessId,
                    'branch_id'   => $originalLog->branch_id,
                    'user_id'     => $adminUserId,
                    'action'      => 'void_customer_deposit',
                    'module'      => 'customer_deposit',
                    'details'     => "Void Deposit #{$originalLog->id} -> #{$contraLog->id} ({$customer->name}) Rp " . number_format($reverseAmount, 0, ',', '.') . " | Alasan: {$cleanReason}",
                    'ip_address'  => request()?->ip(),
                ]);
            }

            BranchCache::flush($businessId);

            return $contraLog;
        });
    }

    /**
     * Koreksi 1 langkah atomik: membatalkan setoran deposit yang salah lalu membuat setoran pengganti yang benar
     * sambil mempertahankan cabang, tanggal buku, dan atribusi laci kasir asalnya.
     *
     * @return array{reversal_log: CustomerDepositLog, new_log: CustomerDepositLog}
     */
    public static function correctDeposit(
        int $businessId,
        int $depositLogId,
        float $newAmount,
        string $newPaymentMethod,
        string $reason,
        int $adminUserId
    ): array {
        $cleanReason = trim($reason);
        if ($newAmount <= 0) {
            throw new \InvalidArgumentException("Nominal deposit koreksi harus lebih dari 0.");
        }
        if ($cleanReason === '') {
            throw new \InvalidArgumentException("Alasan koreksi deposit wajib diisi.");
        }

        return DB::transaction(function () use ($businessId, $depositLogId, $newAmount, $newPaymentMethod, $cleanReason, $adminUserId) {
            $originalLog = CustomerDepositLog::withoutGlobalScopes()
                ->where('id', $depositLogId)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $originalCashFlow = $originalLog->cash_flow_id
                ? CashFlow::withoutGlobalScopes()->find($originalLog->cash_flow_id)
                : null;

            $newMethod = strtolower(trim($newPaymentMethod));
            $origMethod = strtolower((string) ($originalLog->payment_method ?? ''));
            $roundedNewAmount = round($newAmount, 2);

            if (abs($roundedNewAmount - (float) $originalLog->amount) < 0.01 && $newMethod === $origMethod) {
                throw new \InvalidArgumentException("Nominal dan metode pembayaran koreksi sama dengan data asal.");
            }

            $periodDate = ($originalCashFlow && $originalCashFlow->date)
                ? Carbon::parse($originalCashFlow->date)
                : ($originalLog->created_at ?? now());

            $reversalLog = self::reverseDeposit(
                $businessId,
                $depositLogId,
                "Koreksi menjadi Rp " . number_format($roundedNewAmount, 0, ',', '.') . ": {$cleanReason}",
                $adminUserId,
                false
            );

            $replacementNote = "[Koreksi dari Deposit #{$originalLog->id}] {$cleanReason}";

            if ($newMethod === 'opening_balance') {
                $newLog = self::addOpeningDeposit(
                    $businessId,
                    $originalLog->branch_id ? (int) $originalLog->branch_id : null,
                    (int) $originalLog->customer_id,
                    $roundedNewAmount,
                    $replacementNote,
                    $adminUserId,
                    $periodDate
                );
            } else {
                // Pertahankan atribusi kasir & status laci agar selisih kas laci kasir tetap sinkron
                $origCashFlowCreator = $originalCashFlow?->created_by ?: ($originalLog->created_by ?: $adminUserId);
                $wasCashOwnerOnly = $originalCashFlow
                    && strtolower((string) $originalCashFlow->payment_method) === 'cash'
                    && !(bool) $originalCashFlow->affects_drawer;

                $newAffectsDrawer = ($newMethod === 'cash') && !$wasCashOwnerOnly;

                $newLog = self::addDeposit(
                    $businessId,
                    $originalLog->branch_id ? (int) $originalLog->branch_id : null,
                    (int) $originalLog->customer_id,
                    $roundedNewAmount,
                    $newMethod,
                    $originalLog->transaction_id ? (int) $originalLog->transaction_id : null,
                    $replacementNote,
                    $adminUserId,
                    $periodDate,
                    (int) $origCashFlowCreator,
                    $newAffectsDrawer
                );
            }

            UserActivityLog::create([
                'business_id' => $businessId,
                'branch_id'   => $originalLog->branch_id,
                'user_id'     => $adminUserId,
                'action'      => 'correct_customer_deposit',
                'module'      => 'customer_deposit',
                'details'     => "Koreksi Deposit #{$originalLog->id} -> #{$newLog->id} Rp " . number_format((float) $originalLog->amount, 0, ',', '.') . ' menjadi Rp ' . number_format($roundedNewAmount, 0, ',', '.') . " | Alasan: {$cleanReason}",
                'ip_address'  => request()?->ip(),
            ]);

            BranchCache::flush($businessId);

            return [
                'reversal_log' => $reversalLog,
                'new_log'      => $newLog,
            ];
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
            $customer = Customer::withoutGlobalScopes()
                ->where('id', $customerId)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = (float) ($customer->deposit_balance ?? 0.0);
            $actualUsed = min($balanceBefore, $requestedAmount);

            if ($actualUsed <= 0) {
                return 0.0;
            }

            $actualUsed = round($actualUsed, 2);
            $balanceAfter = round($balanceBefore - $actualUsed, 2);

            $customer->update([
                'deposit_balance' => $balanceAfter,
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
            $customer = Customer::withoutGlobalScopes()->where('id', $transaction->customer_id)->lockForUpdate()->first();
            if (!$customer) {
                return;
            }

            $balanceBefore = (float) ($customer->deposit_balance ?? 0.0);
            $balanceAfter = round($balanceBefore + $depositUsed, 2);

            $customer->update([
                'deposit_balance' => $balanceAfter,
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
