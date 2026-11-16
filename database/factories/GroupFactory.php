<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'Ingliz-'.fake()->unique()->numberBetween(1, 999),
            'subject' => 'English',
            'teacher_percent' => 40,
            'days' => [1, 3, 5],
            'start_time' => '09:00',
            'end_time' => '10:30',
            'start_date' => '2026-01-01',
            'status' => 'active',
        ];
    }

    /**
     * Give the group a monthly price (effective from the given month).
     */
    public function priced(int $monthlyPrice = 600_000, int $lessonsPerMonth = 12, string $from = '2026-01-01'): static
    {
        return $this->afterCreating(fn (Group $group) => $group->prices()->create([
            'monthly_price' => $monthlyPrice,
            'lessons_per_month' => $lessonsPerMonth,
            'effective_from' => Carbon::parse($from)->startOfMonth(),
        ]));
    }
}
