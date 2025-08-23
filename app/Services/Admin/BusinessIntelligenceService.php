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

        $topCustomers = Customer::where('business_id', $businessId)
            ->orderByDesc('total_purchases')
            ->limit(5)
            ->get();
            
        $topProducts = Transaction::join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(transaction_details.quantity) as total_sold'))
            ->where('transactions.type', 'sale')
            ->where('transactions.business_id', $businessId)
            ->groupBy('products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        $profitMargin = Product::where('business_id', $businessId)
            ->where('base_price', '>', 0)
            ->select('name', DB::raw('((base_price - cost_price) / base_price) * 100 as margin'))
            ->orderByDesc('margin')
            ->limit(5)
            ->get();

        return [
            'top_customers' => $topCustomers,
            'top_selling_products' => $topProducts,
            'highest_margin_products' => $profitMargin,
        ];
    }
}