<?php

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class StudentSeeder extends Seeder
{
    /** تعداد کل دانش‌آموزان نمونه. */
    private const COUNT = 20;

    /** نسبت وضعیت‌ها در یک آموزشگاه واقعی — همه «فعال» غیرواقعی است. */
    private const STATUS_MIX = [
        [Status::Active, 14],
        [Status::Enrolling, 3],
        [Status::OnLeave, 1],
        [Status::Graduated, 1],
        [Status::Dropped, 1],
    ];

    public function run(): void
    {
        $existing = Student::count();

        if ($existing >= self::COUNT) {
            $this->command?->info("Students already seeded, skipping ({$existing} rows).");

            return;
        }

        $new = Student::factory()->count(self::COUNT - $existing)->create();

        // روی ردیف‌های از قبل موجود هم پروفایل ساخته می‌شود، وگرنه نصب نیمه‌کاره
        // دانش‌آموزِ بدون پرونده تولید می‌کند
        $orphans = Student::query()->whereDoesntHave('profile')->get();

        $students = $new->concat($orphans)->unique('id')->values();

        $this->seedProfiles($students);
        $this->seedGuardians($students);

        $this->command?->info('Seeded '.Student::count().' students, '
            .StudentProfile::count().' profiles, '.Guardian::count().' guardians.');
    }

    /** توزیع وضعیت به ترتیب، تا داده نمونه واقع‌نما باشد نه همه‌یک‌شکل. */
    private function seedProfiles(Collection $students): void
    {
        $queue = collect();

        foreach (self::STATUS_MIX as [$status, $count]) {
            $queue = $queue->concat(array_fill(0, $count, $status));
        }

        $students->each(function (Student $student, int $index) use ($queue) {
            if ($student->profile()->exists()) {
                return;
            }

            $status = $queue[$index % $queue->count()] ?? Status::Active;

            StudentProfile::factory()->create([
                'student_id' => $student->id,
                'status' => $status,

                // کد داخلی فقط برای دانش‌آموزی که پرونده‌اش کامل است
                'student_code' => in_array($status, [Status::Active, Status::OnLeave], true)
                    ? '1404-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)
                    : null,
            ]);
        });
    }

    /** هر دانش‌آموز یک سرپرست اصلی، و گاهی یک سرپرست دوم. */
    private function seedGuardians(Collection $students): void
    {
        $students->each(function (Student $student) {
            if ($student->guardians()->exists()) {
                return;
            }

            Guardian::factory()->primary()->create([
                'student_id' => $student->id,
                'last_name' => $student->last_name,
                'relation' => 'father',
            ]);

            if (fake()->boolean(25)) {
                Guardian::factory()->create([
                    'student_id' => $student->id,
                    'last_name' => $student->last_name,
                    'relation' => 'mother',
                ]);
            }
        });
    }
}
