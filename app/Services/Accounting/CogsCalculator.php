<?php

namespace App\Services\Accounting;

use App\Models\Product;

class CogsCalculator
{
    /**
     * Menghitung total income, COGS/HPP, dan gross profit untuk sekumpulan transaksi penjualan.
     * Mengabaikan produk jasa (Product::isService()).
     * Menghitung jumlah baris detail yang belum memiliki snapshot HPP (missing_cogs_count).
     *
     * @param iterable $transactions
     * @return array
     */
    public static function calculateForTransactions(iterable $transactions): array
    {
        $strict = (bool) config('accounting.strict_cogs', false);

        $totalIncome = 0.0;
        $totalCogs = 0.0;
        $totalGrossProfit = 0.0;
        $missingCogsCount = 0;
        $missingCogsRevenue = 0.0;
        $inventoryCostCache = [];

        foreach ($transactions as $transaction) {
            $transactionCogs = 0.0;

            if ($transaction->details) {
                foreach ($transaction->details as $detail) {
                    $product = $detail->product;

                    // Abaikan produk jasa (misal: ongkos kirim)
                    if ($product && $product->isService()) {
                        continue;
                    }

                    $rawCost = $detail->cost_price;
                    if ($rawCost === null) {
                        $missingCogsCount++;
                        $missingCogsRevenue += (float) ($detail->total_price ?? 0);

                        // Fallback chain: 1. inventory.cost_price cabang transaksi -> 2. products.cost_price
                        $branchCost = null;
                        $branchId = $transaction->branch_id;
                        $productId = $detail->product_id;

                        if ($branchId && $productId) {
                            $cacheKey = "{$branchId}_{$productId}";
                            if (!array_key_exists($cacheKey, $inventoryCostCache)) {
                                $inv = \App\Models\Inventory::withoutGlobalScope('branch')
                                    ->where('branch_id', $branchId)
                                    ->where('product_id', $productId)
                                    ->first();
                                $inventoryCostCache[$cacheKey] = ($inv && (float) $inv->cost_price > 0)
                                    ? (float) $inv->cost_price
                                    : null;
                            }
                            $branchCost = $inventoryCostCache[$cacheKey];
                        }

                        if ($branchCost !== null) {
                            $costPrice = $branchCost;
                        } else {
                            $costPrice = $strict ? 0.0 : (float) (optional($product)->cost_price ?? 0);
                        }
                    } else {
                        // Jika terisi (termasuk 0), gunakan nilai snapshot apa adanya
                        $costPrice = (float) $rawCost;
                    }

                    $transactionCogs += ($costPrice * (float) $detail->quantity);
                }
            }

            $transactionGross = (float) $transaction->total_amount - $transactionCogs;
            $transaction->cogs = $transactionCogs;
            $transaction->gross_profit = $transactionGross;

            $totalCogs += $transactionCogs;
            $totalGrossProfit += $transactionGross;
            $totalIncome += (float) $transaction->total_amount;
        }

        return [
            'total_income'         => $totalIncome,
            'total_cogs'           => $totalCogs,
            'total_gross_profit'   => $totalGrossProfit,
            'missing_cogs_count'   => $missingCogsCount,
            'missing_cogs_revenue' => $missingCogsRevenue,
        ];
    }
}
