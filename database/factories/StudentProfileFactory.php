<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Student;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'status' => Status::Enrolling,
            'enrolled_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'sheba' => 'IR'.fake()->numerify('0##################'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => Status::Active]);
    }

    public function status(Status $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
