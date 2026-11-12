<?php

namespace App\Services;

use App\Enums\WeekDay;
use App\Models\ClassSession;
use App\Support\ClassOccurrence;
use Illuminate\Support\Collection;

/**
 * ساخت تاریخ‌های واقعیِ جلسات برای یک بازهٔ میلادی.
 *
 * دو منبع دارد و هر دو لازم‌اند:
 *   - جلسهٔ تاریخ‌دار: دقیقاً همان یک روز.
 *   - الگوی هفتگی (بدون تاریخ): هر هفته در همان روزِ هفته، از تاریخ شروع
 *     دوره به بعد. دوره‌ای که هنوز شروع نشده در تقویم نیست.
 *
 * شمارهٔ هفته در این محاسبه شرکت نمی‌کند، برخلاف ClassSession::overlapsWith:
 * آنجا «هفتهٔ ۳ دوره» با «هفتهٔ ۳ دورهٔ دیگر» مقایسه می‌شود — سطح برنامهٔ
 * درسی. این‌جا اما یک تاریخ مشخص در میان است و تاریخ، شمارهٔ هفته ندارد.
 */
class CalendarBuilder
{
    /**
     * همهٔ وقوع‌های بازه.
     *
     * @param  string|null  $from  تاریخ میلادی ISO
     * @param  string|null  $to  تاریخ میلادی ISO
     * @return Collection<int, ClassOccurrence>
     */
    public function forRange(?string $from = null, ?string $to = null, ?int $teacherId = null): Collection
    {
        $sessions = ClassSession::query()
            ->with(['course.teacher'])
            ->when($teacherId, fn ($query) => $query->whereHas(
                'course',
                fn ($course) => $course->where('teacher_id', $teacherId)
            ))
            ->get();

        return $sessions
            ->flatMap(fn (ClassSession $session) => $this->occurrencesOf($session, $from, $to))
            ->sortBy(fn (ClassOccurrence $occurrence) => [$occurrence->date, $occurrence->startMinute()])
            ->values();
    }

    /** وقوع‌های یک ماه شمسی. */
    public function forJalaliMonth(int $jalaliYear, int $jalaliMonth, ?int $teacherId = null): Collection
    {
        [$from, $to] = $this->jalaliMonthRange($jalaliYear, $jalaliMonth);

        return $this->forRange($from, $to, $teacherId);
    }

    /**
     * شبکهٔ ماه: ردیف‌ها هفته‌اند، هر هفته از شنبه تا جمعه، با خانهٔ پرکنندهٔ null.
     *
     * @return list<list<array{date: string, day: int, jalali: string, is_today: bool, occurrences: Collection<int, ClassOccurrence>}|null>>
     */
    public function grid(int $jalaliYear, int $jalaliMonth, ?int $teacherId = null): array
    {
        [$from, $to] = $this->jalaliMonthRange($jalaliYear, $jalaliMonth);

        $occurrences = $this->forRange($from, $to, $teacherId)->groupBy('date');
        $today = date('Y-m-d');

        $first = new \DateTimeImmutable($from);
        $days = jalali_month_days($jalaliYear, $jalaliMonth);

        // هفتهٔ شمسی از شنبه شروع می‌شود؛ PHP شنبه را ۶ می‌دهد (Carbon::SATURDAY)
        $offset = ((int) $first->format('w') + 1) % 7;

        $cells = array_fill(0, $offset, null);

        for ($day = 1; $day <= $days; $day++) {
            $date = (new \DateTimeImmutable($from))->modify('+'.($day - 1).' days')->format('Y-m-d');

            $cells[] = [
                'date' => $date,
                'day' => $day,
                'jalali' => jalali_date($date),
                'is_today' => $date === $today,
                'occurrences' => $occurrences->get($date, collect()),
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return array_chunk($cells, 7);
    }

    /**
     * بازهٔ میلادیِ یک ماه شمسی (شامل).
     *
     * @return array{0: string, 1: string}
     */
    public function jalaliMonthRange(int $jalaliYear, int $jalaliMonth): array
    {
        $from = miladi_date(sprintf('%04d/%02d/01', $jalaliYear, $jalaliMonth));
        $to = miladi_date(sprintf(
            '%04d/%02d/%02d',
            $jalaliYear,
            $jalaliMonth,
            jalali_month_days($jalaliYear, $jalaliMonth),
        ));

        return [$from, $to];
    }

    /** @return Collection<int, ClassOccurrence> */
    private function occurrencesOf(ClassSession $session, ?string $from, ?string $to): Collection
    {
        $course = $session->course;

        if ($course === null) {
            return collect();
        }

        // جلسهٔ تاریخ‌دار: همان یک روز، و فقط اگر داخل بازه باشد
        if ($session->session_date !== null) {
            $date = $session->session_date->format('Y-m-d');

            if ($this->inRange($date, $from, $to)) {
                return collect([new ClassOccurrence($date, $session, $course)]);
            }

            return collect();
        }

        $startsAt = $course->starts_at?->format('Y-m-d');
        $result = collect();

        foreach ($this->datesBetween($from, $to) as $date) {
            // دورهٔ شروع‌نشده جلسه‌ای ندارد؛ «هنوز شروع نشده» با «تمام شده» فرق
            // دارد چون دوره تاریخ پایان ندارد
            if ($startsAt !== null && $date < $startsAt) {
                continue;
            }

            if (WeekDay::fromDate(new \DateTimeImmutable($date)) !== $session->week_day) {
                continue;
            }

            $result->push(new ClassOccurrence($date, $session, $course));
        }

        return $result;
    }

    /**
     * تاریخ‌های میلادی یک بازه، قدم‌به‌قدم.
     *
     * @return list<string>
     */
    private function datesBetween(?string $from, ?string $to): array
    {
        $from ??= date('Y-m-d');
        $to ??= $from;

        if ($to < $from) {
            return [];
        }

        $dates = [];
        $cursor = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);

        while ($cursor <= $end) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }

    private function inRange(string $date, ?string $from, ?string $to): bool
    {
        if ($from !== null && $date < $from) {
            return false;
        }

        return ! ($to !== null && $date > $to);
    }
}
