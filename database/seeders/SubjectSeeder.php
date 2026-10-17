<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    /**
     * سرفصل‌های واقعی یک آموزشگاه، با ظرفیت سالانه.
     *
     * داده نمونهٔ `fake()` به درد نمی‌خورد: نه با چیزی که آموزشگاه واقعاً
     * تدریس می‌کند شبیه است، نه ظرفیتش قابل اتکاست.
     */
    private const CATALOG = [
        ['MATH', 'ریاضی', 120],
        ['PHY', 'فیزیک', 100],
        ['CHEM', 'شیمی', 96],
        ['BIO', 'زیست‌شناسی', 96],
        ['ENG', 'زبان انگلیسی', 120],
        ['LIT', 'ادبیات فارسی', 80],
        ['ARAB', 'زبان عربی', 60],
        ['REL', 'دینی', 40],
    ];

    private const COURSE_COUNT = 24;

    public function run(): void
    {
        $subjects = [];

        foreach (self::CATALOG as [$code, $name, $hours]) {
            $subjects[] = Subject::query()->firstOrCreate(
                ['code' => $code.'-GEN'],
                ['name' => $name.' (عمومی)', 'annual_hours' => $hours, 'is_active' => true],
            );
        }

        // تخصص استادها بعد از ساخته‌شدن درس‌ها: تخصص به درس ارجاع می‌دهد،
        // پس زنجیرهٔ seeder باید همین ترتیب را نگه دارد
        $linked = $this->linkSpecialties();

        $this->command?->info('Linked '.$linked.' teacher-subject pairs.');

        Course::factory()->count(self::COURSE_COUNT)->create();

        $this->command?->info('Seeded '.Subject::count().' subjects, '.Course::count().' courses.');
    }

    /**
     * هر استاد یک تا سه تخصص بین درس‌های فعال آموزشگاه، و شمار جفت‌هایی که
     * واقعاً ساخته شد.
     *
     * تخصصِ تصادفی و بی‌ربط به رشتهٔ متنی استاد در دادهٔ نمونه گمراه‌کننده است؛
     * ولی الگوریتم قاعده‌ای ندارد که درسِ درست را حدس بزند، پس ترجیحاً به
     * همان رشتهٔ متنی نزدیک می‌شویم وگرنه از فهرست فعال انتخاب می‌کنیم.
     *
     * اجرای دوباره چیزی نمی‌افزاید: استادی که تخصص دارد رد می‌شود، وگرنه هر بار
     * db:seed لیست تخصص‌ها را تکرار و گزارش را غلط می‌کند.
     */
    private function linkSpecialties(): int
    {
        $teachers = Teacher::query()->get();

        if ($teachers->isEmpty()) {
            return 0;
        }

        $subjects = Subject::active()->get();
        $used = DB::table('teacher_subjects')->pluck('teacher_id')->all();
        $linked = 0;

        foreach ($teachers as $teacher) {
            if (in_array($teacher->id, $used, true)) {
                continue;
            }

            $matches = $subjects->filter(
                fn (Subject $subject) => $teacher->subject !== null
                    && str_contains($subject->name, explode(' ', $teacher->subject)[0])
            );

            $pool = $matches->isNotEmpty() ? $matches : $subjects;

            $ids = $pool->random(min($pool->count(), fake()->numberBetween(1, 3)))->pluck('id')->all();

            $teacher->subjects()->sync($ids);
            $linked += count($ids);
        }

        return $linked;
    }
}
