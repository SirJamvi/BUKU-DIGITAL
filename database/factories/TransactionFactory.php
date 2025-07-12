<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Customer;

class TransactionFactory extends Factory
{
    /**
     * Nama model yang sesuai dengan factory.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Mendefinisikan status default model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'sale',
            'customer_id' => Customer::factory(),
            'total_amount' => $this->faker->numberBetween(10000, 500000),
            'payment_method' => $this->faker->randomElement(['Cash', 'QRIS', 'Debit']),
            'payment_status' => 'paid',
            'status' => 'completed',
            'transaction_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::where('role', 'kasir')->inRandomOrder()->first()->id ?? User::factory(),
        ];
    }
}