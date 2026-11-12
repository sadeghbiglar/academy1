<?php

namespace App\Support;

use App\Enums\WeekDay;
use App\Models\ClassSession;
use App\Models\Course;

/**
 * یک وقوع مشخصِ یک جلسه: «این دوره، در این تاریخ، از این ساعت تا این ساعت».
 *
 * جلسه در پایگاه داده دو شکل دارد: الگوی هفتگی (روز و ساعت، بدون تاریخ) یا
 * جلسهٔ تاریخ‌دار (جلسهٔ جبرانی و جلسهٔ برگزارشده). تقویم و تعارض‌یاب هر دو
 * به «تاریخ + بازهٔ دقیقه» نیاز دارند، پس این وسط‌لایه هر دو شکل را به یک
 * چیز واحد تبدیل می‌کند — بدون آن، هر کدام جدول تکرارِ خودش را می‌نوشت و
 * بالاخره یکی از دو تقویم دروغ می‌گفت.
 */
final class ClassOccurrence
{
    /** @param  string  $date  تاریخ میلادی ISO (Y-m-d) */
    public function __construct(
        public readonly string $date,
        public readonly ClassSession $session,
        public readonly Course $course,
    ) {}

    public function startMinute(): int
    {
        return $this->session->start_minute;
    }

    public function endMinute(): int
    {
        return $this->session->end_minute;
    }

    /**
     * هم‌زمانی دو وقوع: یک روز + بازه‌های هم‌پوشان.
     *
     * شروع و پایان بسته‌اند، مثل ClassSession::overlapsWith: جلسه‌ای که
     * ۱۰:۰۰ تمام می‌شود با جلسهٔ ۱۰:۰۰ شروع‌شده تداخل دارد، وگرنه دانش‌آموز
     * باید در یک لحظه در دو کلاس باشد.
     */
    public function overlaps(self $other): bool
    {
        if ($this->date !== $other->date) {
            return false;
        }

        return $this->startMinute() <= $other->endMinute()
            && $other->startMinute() <= $this->endMinute();
    }

    /** «۰۹:۰۰–۱۱:۰۰» با ارقام فارسی، برای نمایش. */
    public function timeLabel(): string
    {
        return to_persian_digits(
            sprintf('%02d:%02d–%02d:%02d',
                intdiv($this->startMinute(), 60), $this->startMinute() % 60,
                intdiv($this->endMinute(), 60), $this->endMinute() % 60,
            )
        );
    }

    public function dayOfWeek(): WeekDay
    {
        // تاریخ میلادی است، پس محاسبه روز هفته همین‌جا انجام می‌شود؛
        // تبدیل به شمسی برای فهمیدن روز هفته لازم نیست
        return WeekDay::fromDate(new \DateTimeImmutable($this->date));
    }

    public function title(): string
    {
        return $this->course->title;
    }

    public function room(): ?string
    {
        $room = $this->session->room;

        return $room === null || trim($room) === '' ? null : $room;
    }

    public function teacherName(): ?string
    {
        $teacher = $this->course->teacher;

        return $teacher ? trim($teacher->first_name.' '.$teacher->last_name) : null;
    }

    /** تاریخ شمسی برای نمایش. */
    public function dateJalali(): ?string
    {
        return jalali_date($this->date);
    }
}
