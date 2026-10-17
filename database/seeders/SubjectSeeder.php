<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Subject;
use Illuminate\Database\Seeder;

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

        Course::factory()->count(self::COURSE_COUNT)->create();

        $this->command?->info('Seeded '.Subject::count().' subjects, '.Course::count().' courses.');
    }
}
