<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BusinessIntelligenceService
{
    /**
     * Menyediakan wawasan bisnis berdasarkan data untuk bisnis saat ini.
     */
    public function getBusinessInsights(): array
    {
        $businessId = Auth::user()->business_id;

        $context = app(\App\Support\BranchContext::class);

        $topCustomers = Customer::where('business_id', $businessId)
            ->orderByDesc('total_purchases')
            ->limit(5)
            ->get();

        $topProductsQuery = Transaction::join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(transaction_details.quantity) as total_sold'))
            ->where('transactions.type', 'sale')
            ->where('transactions.business_id', $businessId)
            ->where('products.name', 'NOT LIKE', '%ongkos kirim%')
            ->where('products.name', 'NOT LIKE', '%ongkir%');

        $context->applyTo($topProductsQuery, 'transactions');

        $topProducts = $topProductsQuery->groupBy('products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        if ($context->isBranch() && $context->getBranchId() !== null) {
            $branchId = $context->getBranchId();
            $profitMargin = Product::leftJoin('inventory', function ($join) use ($branchId) {
                    $join->on('products.id', '=', 'inventory.product_id')
                         ->where('inventory.branch_id', '=', $branchId);
                })
                ->where('products.business_id', $businessId)
                ->where('products.is_active', true)
                ->where('products.base_price', '>', 0)
                ->where('products.name', 'NOT LIKE', '%ongkos kirim%')
                ->where('products.name', 'NOT LIKE', '%ongkir%')
                ->select(
                    'products.name',
                    DB::raw('ROUND(((products.base_price - COALESCE(NULLIF(inventory.cost_price, 0), products.cost_price, 0)) / products.base_price) * 100, 2) as margin')
                )
                ->orderByDesc('margin')
                ->limit(5)
                ->get();
        } else {
            // Mode Pusat: margin dihitung dari rata-rata tertimbang HPP seluruh cabang aktif
            $products = Product::where('business_id', $businessId)
                ->where('is_active', true)
                ->where('base_price', '>', 0)
                ->where('name', 'NOT LIKE', '%ongkos kirim%')
                ->where('name', 'NOT LIKE', '%ongkir%')
                ->with(['inventories' => function ($q) {
                    $q->withoutGlobalScope('branch')
                      ->whereHas('branch', fn($b) => $b->where('is_active', true));
                }])
                ->get();

            $profitMargin = $products->map(function ($p) {
                $totalStock = (float) $p->inventories->sum('current_stock');
                $totalVal = (float) $p->inventories->sum(fn($inv) => (float) $inv->current_stock * (float) $inv->cost_price);
                if ($totalStock > 0 && $totalVal > 0) {
                    $cogs = $totalVal / $totalStock;
                } else {
                    $cogs = (float) ($p->inventories->avg('cost_price') ?: ($p->cost_price ?? 0));
                }
                $basePrice = (float) $p->base_price;
                $margin = $basePrice > 0 ? round((($basePrice - $cogs) / $basePrice) * 100, 2) : 0.0;
                return (object) ['name' => $p->name, 'margin' => $margin];
            })->sortByDesc('margin')->values()->take(5);
        }

        return [
            'top_customers' => $topCustomers,
            'top_selling_products' => $topProducts,
            'highest_margin_products' => $profitMargin,
        ];
    }
}