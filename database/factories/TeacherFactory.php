<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'national_code' => fake()->numerify('##########'),
            'father_name' => fake()->firstName(),
            'birth_date' => fake()->dateTimeBetween('-55 years', '-28 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),

            'mobile' => fake()->numerify('091########'),
            'phone' => fake()->numerify('024########'),
            'email' => fake()->unique()->safeEmail(),

            'subject' => fake()->randomElement(['ریاضی', 'فیزیک', 'شیمی', 'ادبیات', 'زبان انگلیسی', 'زیست‌شناسی', 'ریاضی فیزیک', 'اقتصاد']),
            'hire_date' => fake()->dateTimeBetween('-15 years', '-1 year')->format('Y-m-d'),
        ];
    }
}
