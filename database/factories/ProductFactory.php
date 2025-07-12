<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;
use App\Models\ProductCategory;

class ProductFactory extends Factory
{
    /**
     * Nama model yang sesuai dengan factory.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Mendefinisikan status default model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost_price = $this->faker->numberBetween(1000, 50000);
        $base_price = $cost_price + $this->faker->numberBetween(500, 10000);

        return [
            'name' => $this->faker->words(3, true),
            'category_id' => ProductCategory::factory(),
            'unit' => $this->faker->randomElement(['Pcs', 'Kg', 'Liter', 'Bungkus', 'Porsi']),
            'base_price' => $base_price,
            'cost_price' => $cost_price,
            'description' => $this->faker->paragraph(),
            'sku' => $this->faker->unique()->bothify('SKU-??-####'),
            'barcode' => $this->faker->unique()->ean13(),
            'is_active' => true,
            'is_featured' => $this->faker->boolean(20), // 20% kemungkinan menjadi produk unggulan
        ];
    }
}   