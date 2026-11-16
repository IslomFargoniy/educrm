<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'group_id' => Group::factory(),
            'joined_at' => '2026-01-01',
            'status' => EnrollmentStatus::Active,
        ];
    }

    public function trial(int $lessons = 1): static
    {
        return $this->state(['status' => EnrollmentStatus::Trial, 'trial_lessons' => $lessons]);
    }
}
