<?php

namespace App\Services\Accounting;

use App\Exceptions\PeriodClosedException;
use App\Models\BranchMonthlyClosing;
use App\Models\OwnerProfit;
use Carbon\Carbon;

class AccountingPeriodGuard
{
    /**
     * Memastikan periode pembukuan untuk tanggal tertentu masih terbuka (belum closing bulanan).
     *
     * @param int $businessId
     * @param int|null $branchId
     * @param Carbon|string $date
     * @throws PeriodClosedException
     */
    public static function ensurePeriodOpen(int $businessId, ?int $branchId, $date): void
    {
        $cDate = $date instanceof Carbon ? $date : Carbon::parse($date);
        $month = (int) $cDate->format('m');
        $year = (int) $cDate->format('Y');

        if ($branchId) {
            $isClosed = BranchMonthlyClosing::where('business_id', $businessId)
                ->where('branch_id', $branchId)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->where('status', 'completed')
                ->exists();

            if ($isClosed) {
                throw new PeriodClosedException(
                    "Operasi ditolak: Pembukuan Cabang #{$branchId} untuk periode {$month}/{$year} sudah ditutup (Closing Bulanan). Hubungi Administrator untuk pembukaan kembali."
                );
            }
        } else {
            // Jika tanpa cabang spesifik, periksa konsolidasi bisnis
            $isClosed = OwnerProfit::where('business_id', $businessId)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->where('status', 'completed')
                ->exists();

            if ($isClosed) {
                throw new PeriodClosedException(
                    "Operasi ditolak: Pembukuan Konsolidasi Bisnis untuk periode {$month}/{$year} sudah ditutup. Hubungi Administrator untuk pembukaan kembali."
                );
            }
        }
    }
}
