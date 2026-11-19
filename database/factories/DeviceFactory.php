<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'Kirish',
            'direction' => 'in',
            'is_active' => true,
            'is_online' => true,
        ];
    }

    public function exit(): static
    {
        return $this->state(['name' => 'Chiqish', 'direction' => 'out']);
    }
}
