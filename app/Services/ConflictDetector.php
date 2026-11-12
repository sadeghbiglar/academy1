<?php

namespace App\Services;

use App\Enums\ConflictType;
use App\Enums\EnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Support\ClassOccurrence;
use Illuminate\Support\Collection;

/**
 * تعارض‌یاب زمان‌بندی (شماره 47) — قاعدهٔ R6.
 *
 * سه قاعده، همه روی وقوع‌های واقعی (تاریخ + ساعت):
 *   ۱. استاد در دو کلاس همزمان
 *   ۲. یک اتاق میزبان دو کلاس همزمان
 *   ۳. دانش‌آموز در دو دورهٔ همزمان، بر پایهٔ ثبت‌نام قطعی
 *
 * برخلاف EnrollmentEligibility که «آیا ثبت‌نام باید رد شود؟» را از سطح
 * برنامهٔ هفتگی می‌پرسد، این سرویس «در این بازه چه اتفاقی می‌افتد؟» را از
 * روی تاریخ می‌پرسد. به همین دلیل جلسه‌ای که بعد از تأییدِ ثبت‌نام ساخته
 * یا جابه‌جا شده — یعنی دقیقاً چیزی که دروازه ندیده — همین‌جا پیدا می‌شود.
 */
class ConflictDetector
{
    public function __construct(private CalendarBuilder $calendar = new CalendarBuilder) {}

    /**
     * همهٔ تعارض‌های بازهٔ میلادی.
     *
     * @param  string|null  $from  تاریخ میلادی ISO
     * @param  string|null  $to  تاریخ میلادی ISO
     * @param  int|null  $teacherId  فقط کلاس‌های این استاد (نمای دبیر)
     * @return Collection<int, array{type: ConflictType, label: string, date: ?string, dates: Collection<int, string>, left: ClassOccurrence, right: ClassOccurrence, student: ?Student, message: string}>
     */
    public function detect(?string $from = null, ?string $to = null, ?int $teacherId = null): Collection
    {
        $occurrences = $this->calendar->forRange($from, $to, $teacherId);
        $confirmed = $this->confirmedStudentsByCourse();

        // یک تعارض = یک زوج جلسهٔ شکسته‌شده، همراه فهرست همهٔ روزهایی که در
        // آن رخ می‌دهد. پنج شنبهٔ تداخل‌دار پنج هشدار جدا نیستند؛ پنج روز از
        // یک برنامهٔ خراب‌اند
        return $this->pairs($occurrences)
            ->flatMap(fn (array $pair) => $this->classify($pair[0], $pair[1], $confirmed))
            ->groupBy(fn (array $conflict) => $this->key($conflict))
            ->map(fn (Collection $group) => $this->merge($group))
            ->values();
    }

    /** تعارض‌های یک ماه شمسی — همان چیزی که صفحهٔ تقویم نشان می‌دهد. */
    public function forJalaliMonth(int $jalaliYear, int $jalaliMonth, ?int $teacherId = null): Collection
    {
        [$from, $to] = $this->calendar->jalaliMonthRange($jalaliYear, $jalaliMonth);

        return $this->detect($from, $to, $teacherId);
    }

    /**
     * زوج‌های ممکنِ وقوع‌ها.
     *
     * زوجِ یک جلسه با خودش تعارض نیست، و زوجی که هم‌پوش نیست اساساً همزمان
     * نیست — پس قبل از هر قاعده‌ای کنار گذاشته می‌شوند.
     *
     * @return Collection<int, array{0: ClassOccurrence, 1: ClassOccurrence}>
     */
    private function pairs(Collection $occurrences): Collection
    {
        $items = $occurrences->values()->all();
        $count = count($items);
        $pairs = collect();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $left = $items[$i];
                $right = $items[$j];

                if ($left->session->id === $right->session->id || ! $left->overlaps($right)) {
                    continue;
                }

                $pairs->push([$left, $right]);
            }
        }

        return $pairs;
    }

    /**
     * هر زوج ممکن است بیش از یک قاعده را بشکند (یک استاد در یک اتاقِ
     * تکراری)، پس خروجی آرایه است نه یک مقدار.
     *
     * @param  array<int, array<int, Student>>  $confirmed  دوره => دانش‌آموزان قطعی
     * @return Collection<int, array{...}>
     */
    private function classify(ClassOccurrence $left, ClassOccurrence $right, array $confirmed): Collection
    {
        $result = collect();

        if ($this->isTeacherConflict($left, $right)) {
            $result->push($this->conflict(ConflictType::Teacher, $left, $right));
        }

        if ($this->isRoomConflict($left, $right)) {
            $result->push($this->conflict(ConflictType::Room, $left, $right));
        }

        $student = $this->sharedStudent($left, $right, $confirmed);

        if ($student !== null) {
            $result->push($this->conflict(ConflictType::Student, $left, $right, $student));
        }

        return $result;
    }

    /** قاعدهٔ ۱: یک استاد، دو کلاس، یک لحظه. */
    private function isTeacherConflict(ClassOccurrence $left, ClassOccurrence $right): bool
    {
        $teacherId = $left->course->teacher_id;

        // دورهٔ بی‌استاد هرگز «یک استاد در دو جا» نیست
        return $teacherId !== null
            && $teacherId === $right->course->teacher_id
            && $left->session->course_id !== $right->session->course_id;
    }

    /** قاعدهٔ ۲: یک اتاق، دو کلاس، یک لحظه. */
    private function isRoomConflict(ClassOccurrence $left, ClassOccurrence $right): bool
    {
        $room = $left->room();

        return $room !== null && $room === $right->room();
    }

    /**
     * قاعدهٔ ۳: دانش‌آموزی که ثبت‌نام قطعی در هر دو دوره دارد.
     *
     * @param  array<int, array<int, Student>>  $confirmed
     */
    private function sharedStudent(ClassOccurrence $left, ClassOccurrence $right, array $confirmed): ?Student
    {
        $leftCourse = $left->session->course_id;

        // دو جلسهٔ یک دوره همیشه «یک دانش‌آموز در دو دوره» به نظر می‌رسند؛
        // این تداخل نیست، تکرارِ همان کلاس است
        if ($leftCourse === $right->session->course_id) {
            return null;
        }

        foreach ($confirmed[$leftCourse] ?? [] as $studentId => $student) {
            if (isset($confirmed[$right->session->course_id][$studentId])) {
                return $student;
            }
        }

        return null;
    }

    /**
     * دوره => دانش‌آموزانی که ثبت‌نام قطعی در آن دارند.
     *
     * @return array<int, array<int, Student>>
     */
    private function confirmedStudentsByCourse(): array
    {
        $map = [];

        $enrollments = CourseEnrollment::query()
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->with('student')
            ->get();

        foreach ($enrollments as $enrollment) {
            if ($enrollment->student === null) {
                continue;
            }

            $map[$enrollment->course_id][$enrollment->student_id] = $enrollment->student;
        }

        return $map;
    }

    /**
     * یک وقوعِ شکسته‌شده — هنوز بدون پیام؛ پیام پس از یکی‌شدنِ همهٔ روزها
     * ساخته می‌شود.
     */
    private function conflict(ConflictType $type, ClassOccurrence $left, ClassOccurrence $right, ?Student $student = null): array
    {
        return [
            'type' => $type,
            'label' => $type->label(),
            'date' => $left->date,
            'dates' => collect([$left->date]),
            'left' => $left,
            'right' => $right,
            'student' => $student,
            'message' => '',
        ];
    }

    /** کلید یکتای یک تعارض: نوع + دو جلسه + دانش‌آموز (اگر باشد). */
    private function key(array $conflict): string
    {
        return implode(':', [
            $conflict['type']->value,
            $conflict['left']->session->id,
            $conflict['right']->session->id,
            $conflict['student']->id ?? '',
        ]);
    }

    /** همهٔ روزهای یک زوج در یک رکورد جمع می‌شوند و پیام از همان ساخته می‌شود. */
    private function merge(Collection $group): array
    {
        $conflict = $group->first();

        $conflict['dates'] = $group->pluck('date')->unique()->sort()->values();
        $conflict['date'] = $conflict['dates']->count() === 1 ? $conflict['dates']->first() : null;
        $conflict['message'] = $this->message($conflict);

        return $conflict;
    }

    /**
     * پیام فارسیِ خودکفای تعارض.
     *
     * مدیر آن را در تقویم می‌خواند و باید بدون کلیکِ دیگر بفهمد کی، کجا و
     * بین چه کسانی است — تک‌روز با تاریخ شمسی، چندرُوزه با شمارهٔ روزها.
     */
    private function message(array $conflict): string
    {
        $when = $conflict['date'] !== null
            ? to_persian_digits(jalali_date($conflict['date']))
            : to_persian_digits($conflict['dates']->count()).' روز';

        $time = $this->overlapLabel($conflict['left'], $conflict['right']);

        return match ($conflict['type']) {
            ConflictType::Teacher => sprintf(
                'استاد «%s» در %s ساعت %s هم‌زمان در «%s» و «%s» است.',
                $conflict['left']->teacherName(), $when, $time,
                $conflict['left']->title(), $conflict['right']->title(),
            ),
            ConflictType::Room => sprintf(
                'اتاق «%s» در %s ساعت %s هم‌زمان میزبان دو کلاس است: «%s» و «%s».',
                $conflict['left']->room(), $when, $time,
                $conflict['left']->title(), $conflict['right']->title(),
            ),
            ConflictType::Student => sprintf(
                'دانش‌آموز «%s» در %s ساعت %s هم‌زمان در «%s» و «%s» ثبت‌نام قطعی دارد.',
                trim($conflict['student']->first_name.' '.$conflict['student']->last_name),
                $when, $time,
                $conflict['left']->title(), $conflict['right']->title(),
            ),
        };
    }

    /** بازهٔ واقعیِ هم‌پوشانی دو وقوع، نه ساعتِ کاملِ هر کدام. */
    private function overlapLabel(ClassOccurrence $left, ClassOccurrence $right): string
    {
        $start = max($left->startMinute(), $right->startMinute());
        $end = min($left->endMinute(), $right->endMinute());

        return to_persian_digits(sprintf(
            '%02d:%02d–%02d:%02d',
            intdiv($start, 60), $start % 60,
            intdiv($end, 60), $end % 60,
        ));
    }
}
