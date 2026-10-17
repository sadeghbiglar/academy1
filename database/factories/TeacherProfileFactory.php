<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Teacher;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherProfile>
 */
class TeacherProfileFactory extends Factory
{
    protected $model = TeacherProfile::class;

    public function definition(): array
    {
        return [
            'teacher_id' => Teacher::factory(),
            'employee_code' => 'T-'.fake()->unique()->numerify('#####'),
            'position' => fake()->randomElement(['دبیر', 'دبیر ارشد', 'مدرس', 'استادیار', 'کارشناس ارشد']),
            'status' => Status::Active,
            'started_at' => fake()->dateTimeBetween('-12 years', '-1 year')->format('Y-m-d'),
            'insurance_number' => (string) fake()->unique()->numerify('##########'),
        ];
    }

    /** نیم‌رخ کسی که همکاری‌اش تمام شده — وضعیت و تاریخ پایان با هم می‌آیند. */
    public function retired(): static
    {
        return $this->state(fn () => [
            'status' => Status::Inactive,
            'ended_at' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(fn () => ['status' => Status::OnLeave]);
    }

    public function status(Status $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
