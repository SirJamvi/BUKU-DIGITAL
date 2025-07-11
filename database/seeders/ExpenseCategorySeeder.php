<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExpenseCategory;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        ExpenseCategory::create(['name' => 'Bahan Baku', 'type' => 'Operasional']);
        ExpenseCategory::create(['name' => 'Gaji Karyawan', 'type' => 'Operasional']);
        ExpenseCategory::create(['name' => 'Listrik & Air', 'type' => 'Overhead']);
        ExpenseCategory::create(['name' => 'Pemasaran', 'type' => 'Pemasaran']);
    }
}