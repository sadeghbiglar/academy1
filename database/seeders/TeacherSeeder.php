<?php

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\Teacher;
use App\Models\TeacherExperience;
use App\Models\TeacherProfile;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    /**
     * How many teachers to seed.
     */
    private const COUNT = 15;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $existing = Teacher::count();

        if ($existing >= self::COUNT) {
            $this->command?->info("Teachers already seeded, skipping ({$existing} rows).");
        } else {
            Teacher::factory()->count(self::COUNT - $existing)->create();

            $this->command?->info('Seeded '.Teacher::count().' teachers total.');
        }

        // نیم‌رخ و سابقه برای *همهٔ* استادها پر می‌شود، نه فقط تازه‌ساخته‌ها:
        // با زود بازگشت، استادهایی که از اجرای قبلی سر جا مانده‌اند پروندهٔ
        // پرسنلی و سابقه نمی‌گیرند و پروندهٔ ناقص می‌ماند.
        $this->seedProfiles();
        $this->seedExperiences();
    }

    /**
     * نیم‌رخ پرسنلی برای هر استادی که هنوز ندارد.
     *
     * عمداً یکی بازنشسته و یکی در مرخصی هست: اگر همه فعال باشند، مسیرهای
     * گزارشِ غیرفعال و حقوقِ قطع‌شده در دادهٔ نمونه هرگز امتحان نمی‌شوند و
     * اشکالشان وقتی پیدا می‌شود که پایان سال است.
     */
    private function seedProfiles(): void
    {
        $missing = Teacher::doesntHave('profile')->get();

        foreach ($missing as $teacher) {
            TeacherProfile::factory()->create(['teacher_id' => $teacher->id]);
        }

        if ($missing->count() >= 3) {
            $retired = TeacherProfile::factory()->retired()->make();

            $missing[1]->profile->update($retired->only(['status', 'ended_at']));

            $missing[2]->profile->update(['status' => Status::OnLeave]);
        }

        if ($missing->isNotEmpty()) {
            $this->command?->info('Seeded '.$missing->count().' teacher profiles.');
        }
    }

    /**
     * دو سابقه برای هر استادی که هنوز سابقه ندارد: یک تمام‌شده و یک جاری.
     *
     * سابقهٔ جاری ended_at ندارد — ساختن یک تاریخ پایان برای همکاری‌ای که
     * تمام نشده، بعداً «سابقهٔ فعلی» را در گزارش اشتباه می‌کند.
     */
    private function seedExperiences(): void
    {
        $teachers = Teacher::doesntHave('experiences')->get();

        foreach ($teachers as $teacher) {
            TeacherExperience::factory()->create(['teacher_id' => $teacher->id]);
            TeacherExperience::factory()->current()->create(['teacher_id' => $teacher->id]);
        }

        if ($teachers->isNotEmpty()) {
            $this->command?->info('Seeded '.(2 * $teachers->count()).' teacher experiences.');
        }
    }
}
