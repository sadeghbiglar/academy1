<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        $grade = fake()->numberBetween(7, 12);

        return [
            // نام نباید unique باشد: فهرست کوتاه و ترکیب «نام + پایه» تکرار
            // می‌شود و faker بعد از ۱۰۰۰۰ تلاش OverflowException می‌دهد.
            // یکتایی روی کام است، نه روی نام
            'name' => fake()->randomElement([
                'ریاضی', 'فیزیک', 'شیمی', 'زیست', 'زبان انگلیسی', 'ادبیات فارسی',
                'عربی', 'دینی', 'جغرافیا', 'اقتصاد', 'فناوری', 'آزمایشگاه',
            ]).' '.$grade,

            'code' => 'SUB-'.fake()->unique()->numerify('####'),

            'grade_level' => $grade,
            'annual_hours' => fake()->numberBetween(40, 160),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
