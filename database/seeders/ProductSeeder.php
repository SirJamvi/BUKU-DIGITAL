<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'name' => 'Es Kristal', 'category_id' => 1, 'unit' => 'Gelas', 'base_price' => 5000, 'cost_price' => 2000,
            'sku' => 'MD001', 'is_active' => true
        ])->inventory()->create(['current_stock' => 100, 'min_stock' => 20]);

        Product::create([
            'name' => 'Nasi Gudeg Komplit', 'category_id' => 2, 'unit' => 'Porsi', 'base_price' => 25000, 'cost_price' => 15000,
            'sku' => 'MU001', 'is_active' => true
        ])->inventory()->create(['current_stock' => 50, 'min_stock' => 10]);
        
        Product::create([
            'name' => 'Kerupuk Udang', 'category_id' => 3, 'unit' => 'Bungkus', 'base_price' => 2000, 'cost_price' => 500,
            'sku' => 'MR001', 'is_active' => true
        ])->inventory()->create(['current_stock' => 200, 'min_stock' => 50]);
    }
}