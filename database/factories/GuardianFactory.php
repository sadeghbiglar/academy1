<?php

namespace Database\Factories;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'first_name' => fake()->firstNameMale(),
            'last_name' => fake()->lastName(),
            'relation' => 'father',
            'mobile' => fake()->numerify('091########'),
            'is_primary' => false,
        ];
    }

    /** سرپرست اصلی. یک دانش‌آموز فقط یکی می‌تواند داشته باشد. */
    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
