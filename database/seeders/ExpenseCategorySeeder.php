<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Reklama', 'Ijara', 'Kommunal', 'Remont', 'Xodimlar oyligi', "Xo'jalik", 'Boshqa'] as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
