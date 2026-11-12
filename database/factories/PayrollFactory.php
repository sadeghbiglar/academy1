<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        $gross = fake()->numberBetween(8, 20) * 1_000_000;
        $status = fake()->randomElement(['pending', 'paid']);

        return [
            'teacher_id' => Teacher::factory(),
            'period_year' => fake()->numberBetween(1402, 1404),
            'period_month' => fake()->numberBetween(1, 12),
            'days_worked' => fake()->numberBetween(10, 26),
            'overtime_hours' => fake()->randomFloat(1, 0, 20),
            'insurance' => (int) round($gross * 0.07),
            'tax' => (int) round($gross * 0.03),
            'gross' => $gross,
            // فیشِ پرداخت‌شده بدون تاریخ پرداخت نیمه‌کاره است
            'status' => $status,
            'paid_at' => $status === 'paid' ? fake()->dateTimeBetween('-3 months', 'now') : null,
        ];
    }
}
