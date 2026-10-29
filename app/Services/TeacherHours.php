<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Teacher;
use Illuminate\Support\Collection;

/**
 * ساعت کارکرد اساتید در یک بازه — مبنای محاسبهٔ حقوق (شماره 41).
 *
 * بازه همیشه میلادی به این سرویس می‌رسد؛ تبدیل شمسیِ ورودی کار فرم است، نه
 * این‌جا. اگر این سرویس خودش تاریخ شمسی می‌گرفت، هر فراخواننده باید همان
 * تبدیل را دوباره می‌نوشت و یکی‌شان حتماً فراموشش می‌کرد.
 */
class TeacherHours
{
    /**
     * جلسه «برگزارشده» حساب می‌شود، نه جلسه «برنامه‌ریزی‌شده».
     *
     * تنها سندی که می‌گوید جلسه‌ای واقعاً تشکیل شده، ثبتِ حضور دست‌کم یک
     * دانش‌آموز در آن جلسه است. بدون این قاعده، الگوی هفتگیِ هر دوره — هر
     * چقدر هم که تشکیل نشده باشد — به عنوان کارکرد پرداخت می‌شود و مالی با
     * عددی روبه‌رو می‌شود که هیچ ربطی به واقعیت ندارد.
     *
     * جلسهٔ بدون تاریخ هم شمرده نمی‌شود: تاریخ ندارد یعنی نمی‌دانیم کِی بوده،
     * و بازهٔ شمسی را نمی‌توان رویش اعمال کرد.
     *
     * @param  string|null  $from  تاریخ میلادی ISO
     * @param  string|null  $to  تاریخ میلادی ISO
     * @return Collection<int, array{teacher: Teacher, sessions: int, minutes: int, hours: float}>
     */
    public function byTeacher(?string $from = null, ?string $to = null): Collection
    {
        $sessions = ClassSession::query()
            ->whereNotNull('session_date')
            ->whereHas('attendances')
            ->when($from, fn ($q) => $q->whereDate('session_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('session_date', '<=', $to))
            ->with('course')
            ->get();

        // استادِ بی‌دوره هم در گزارش می‌آید با صفر ساعت: سکوت در فهرست حقوق
        // یعنی «این استاد این ماه اصلاً کار نکرد» که با «هنوز محاسبه نشده» فرق دارد
        return Teacher::query()
            ->orderBy('id')
            ->get()
            ->map(function (Teacher $teacher) use ($sessions) {
                $mine = $sessions->filter(
                    fn (ClassSession $session) => $session->course?->teacher_id === $teacher->id
                );

                $minutes = (int) $mine->sum(fn (ClassSession $session) => $session->durationMinutes());

                return [
                    'teacher' => $teacher,
                    'sessions' => $mine->count(),
                    'minutes' => $minutes,
                    'hours' => round($minutes / 60, 2),
                ];
            })
            // بیشترین ساعت اول — مالی اول سراغ کسی می‌رود که بیشتر کار کرده
            ->sortByDesc('minutes')
            ->values();
    }

    /** جمع کل ساعتِ همهٔ استادها در بازه. */
    public function totalHours(Collection $rows): float
    {
        return round($rows->sum('minutes') / 60, 2);
    }
}
