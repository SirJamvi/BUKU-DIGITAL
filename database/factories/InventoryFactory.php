<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Inventory;
use App\Models\Product;

class InventoryFactory extends Factory
{
    /**
     * Nama model yang sesuai dengan factory.
     *
     * @var string
     */
    protected $model = Inventory::class;

    /**
     * Mendefinisikan status default model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'current_stock' => $this->faker->numberBetween(50, 200),
            'min_stock' => $this->faker->numberBetween(10, 25),
            'max_stock' => $this->faker->numberBetween(200, 500),
            'last_updated' => now(),
        ];
    }
}