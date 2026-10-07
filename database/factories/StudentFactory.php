<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'national_code' => fake()->numerify('##########'),
            'father_name' => fake()->firstName(),
            'birth_date' => fake()->dateTimeBetween('-18 years', '-7 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),

            'mobile' => fake()->numerify('091########'),
            'phone' => fake()->numerify('024########'),
            'email' => fake()->safeEmail(),

            'guardian_name' => fake()->name(),
            'guardian_mobile' => fake()->numerify('091########'),

            'province' => 'زنجان',
            'city' => 'ابهر',
            'address' => fake()->address(),
            'postal_code' => fake()->numerify('##########'),
        ];
    }
}