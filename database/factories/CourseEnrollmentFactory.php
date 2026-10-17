<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseEnrollment>
 */
class CourseEnrollmentFactory extends Factory
{
    protected $model = CourseEnrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'enrolled_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'status' => EnrollmentStatus::Pending,
            'discount' => 0,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function waitlisted(): static
    {
        return $this->state(fn () => ['status' => EnrollmentStatus::Waitlisted]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function withFee(int $fee): static
    {
        return $this->state(fn () => ['final_fee' => $fee]);
    }
}
