<?php

namespace Database\Factories;

use App\Models\TransactionDetail;
use App\Models\Transaction;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionDetailFactory extends Factory
{
    protected $model = TransactionDetail::class;

    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'product_id'     => Product::factory(),
            'quantity'       => 1,
            'cost_price'     => 10000,
            'unit_price'     => 15000,
            'total_price'    => 15000,
        ];
    }
}
