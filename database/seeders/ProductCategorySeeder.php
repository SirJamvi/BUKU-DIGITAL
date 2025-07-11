<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        ProductCategory::create(['name' => 'Minuman Dingin', 'description' => 'Berbagai macam minuman dingin dan es.']);
        ProductCategory::create(['name' => 'Makanan Utama', 'description' => 'Makanan berat untuk makan siang atau malam.']);
        ProductCategory::create(['name' => 'Makanan Ringan', 'description' => 'Camilan dan makanan pendamping.']);
    }
}