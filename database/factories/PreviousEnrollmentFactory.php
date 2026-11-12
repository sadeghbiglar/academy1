<?php

namespace Database\Factories;

use App\Enums\PreviousStatus;
use App\Models\PreviousEnrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreviousEnrollment>
 */
class PreviousEnrollmentFactory extends Factory
{
    protected $model = PreviousEnrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'school' => fake()->randomElement([
                'دبستان شهید فهمیده',
                'دبیرستان علامه طباطبایی',
                'مدرسهٔ استعدادهای درخشان',
            ]),
            'academic_year' => fake()->numberBetween(1395, 1403),
            'grade' => (string) fake()->numberBetween(1, 12),
            'previous_grades' => fake()->randomElement([null, 'ریاضی ۱۸، علوم ۱۷']),
            'status' => fake()->randomElement(array_column(PreviousStatus::options(), 'id')),
        ];
    }
}
