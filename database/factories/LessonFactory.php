<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Models\Group;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'date' => today(),
            'start_time' => '09:00',
            'end_time' => '10:30',
            'status' => LessonStatus::Planned,
        ];
    }
}
