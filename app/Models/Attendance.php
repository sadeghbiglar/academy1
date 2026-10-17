<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حضور و غیاب یک دانش‌آموز در یک جلسه.
 *
 * یکتایی روی (جلسه، دانش‌آموز) در پایگاه داده است: فرم گروهی «همه حاضر» روی
 * کل کلاس اجرا می‌شود و اجرای دوبارهٔ آن نباید رکورد تکراری بسازد.
 */
class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'student_id',
        'status',
        'arrival_minute',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'arrival_minute' => 'integer',
        ];
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }

    public function arrivalLabel(): ?string
    {
        if ($this->arrival_minute === null) {
            return null;
        }

        return sprintf('%02d:%02d', intdiv($this->arrival_minute, 60), $this->arrival_minute % 60);
    }

    public function attended(): bool
    {
        return $this->status?->attended() ?? false;
    }

    /**
     * ثبت حضور برای یک جلسه، با یک سطر به ازای هر دانش‌آموز.
     *
     * updateOrCreate به‌جای create عمدی است: اپراتور ممکن است دکمهٔ «همه حاضر»
     * را روی جلسه‌ای بزند که نیمی‌اش ثبت شده، و آن نیمی نباید رکورد تکراری
     * بسازد یا دست‌کم ثبت‌های دستی‌اش را پاک کند.
     *
     * @param  iterable<int, array{student_id: int, status?: string, arrival_minute?: int|null, notes?: string|null}>  $rows
     * @return int تعداد سطرهای نوشته‌شده
     */
    public static function recordFor(ClassSession $session, iterable $rows): int
    {
        $written = 0;

        foreach ($rows as $row) {
            self::updateOrCreate(
                [
                    'class_session_id' => $session->id,
                    'student_id' => $row['student_id'],
                ],
                [
                    'status' => $row['status'] ?? AttendanceStatus::Present->value,
                    'arrival_minute' => $row['arrival_minute'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ],
            );

            $written++;
        }

        return $written;
    }

    /** «همه حاضر» برای کل دانش‌آموزان ثبت‌نام‌شدهٔ قطعیِ این جلسه. */
    public static function markAllPresent(ClassSession $session): int
    {
        // فقط کسانی که هنوز ثبت نشده‌اند. «همه حاضر» یک دکمهٔ گروهی است و نباید
        // غیبتی را که اپراتور دستی ثبت کرده پاک کند — از دست دادن یک ثبت
        // دستی با یک کلیک، بدترین حالت ممکن است.
        $studentIds = $session->course->enrollments()
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->whereNotIn('student_id', self::query()
                ->where('class_session_id', $session->id)
                ->select('student_id'))
            ->pluck('student_id')
            ->all();

        return self::recordFor($session, array_map(
            fn (int $studentId) => ['student_id' => $studentId],
            $studentIds,
        ));
    }

    /**
     * درصد حضور یک دانش‌آموز در یک دوره.
     *
     * غیبت موجه از صورت‌کسر بیرون می‌ماند: حضوری که هرگز برگزار نشده نباید
     * درصد دانش‌آموز را خراب کند. اگر همهٔ جلسات موجه باشند، تقسیم بر صفر
     * می‌شود و درصد «صفر» یا «صد» دروغ می‌گفت.
     */
    public static function attendanceRateFor(Student $student, Course $course): ?float
    {
        $rows = self::query()
            ->where('student_id', $student->id)
            ->whereHas('classSession', fn ($q) => $q->where('course_id', $course->id))
            ->get();

        $counted = $rows->filter(fn (self $row) => $row->status->countsTowardRate());

        if ($counted->isEmpty()) {
            return null;
        }

        $attended = $counted->filter(fn (self $row) => $row->attended())->count();

        return round($attended / $counted->count() * 100, 2);
    }
}
