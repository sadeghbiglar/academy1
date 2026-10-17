<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'student_id' => Student::factory(),
            'status' => AttendanceStatus::Present,
        ];
    }

    public function status(AttendanceStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function absent(): static
    {
        return $this->status(AttendanceStatus::Absent);
    }

    public function excused(): static
    {
        return $this->status(AttendanceStatus::Excused);
    }
}
