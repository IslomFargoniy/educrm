<?php

namespace Database\Factories;

use App\Enums\CashboxMethod;
use App\Models\Branch;
use App\Models\Cashbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cashbox>
 */
class CashboxFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'Naqd',
            'method' => CashboxMethod::Cash,
            'is_active' => true,
        ];
    }
}
