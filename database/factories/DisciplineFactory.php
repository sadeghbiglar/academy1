<?php

namespace Database\Factories;

use App\Enums\DisciplineType;
use App\Models\Discipline;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discipline>
 */
class DisciplineFactory extends Factory
{
    protected $model = Discipline::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(array_column(DisciplineType::options(), 'id')),
            'description' => fake()->randomElement([
                'در کلاس مشارکت فعال داشت',
                'با تأخیر وارد کلاس شد',
                'تکلیف ارائه نشد',
                'تذکر در مورد انضباط کلاس',
            ]),
            'occurred_on' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
