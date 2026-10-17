<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * ثبت‌نام یک دانش‌آموز در یک دوره.
 *
 * ثبت‌نام دو حالت دارد که باید قاطی نشوند:
 *   - «درخواست ثبت‌نام» که وضعیت اولیهٔ هر رکورد است
 *   - «ظرفیت پر است» که نتیجهٔ شمارش است، نه وضعیتی که کاربر انتخاب می‌کند
 * یعنی کارشناس پذیرش نباید بتواند کسی را دستی در صف انتظار بگذارد؛ خودِ
 * سیستم باید تصمیم بگیرد.
 */
class CourseEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'enrolled_at',
        'status',
        'final_fee',
        'discount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'enrolled_at' => 'date',
            'final_fee' => 'integer',
            'discount' => 'integer',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * کلید یکتاییِ ثبت‌نام فعال.
     *
     * با لغو شدن NULL می‌شود، چون NULL در ایندکس یکتا تکرارپذیر است — و همین
     * چیزی است که اجازه می‌دهد فرد بعداً دوباره ثبت‌نام کند.
     */
    protected static function booted(): void
    {
        static::saving(function (self $enrollment) {
            if ($enrollment->discount < 0) {
                throw ValidationException::withMessages([
                    'discount' => 'تخفیف نمی‌تواند منفی باشد.',
                ]);
            }

            $enrollment->active_key = $enrollment->status === EnrollmentStatus::Cancelled
                ? null
                : $enrollment->student_id.':'.$enrollment->course_id;

            if ($enrollment->status === EnrollmentStatus::Confirmed && $enrollment->confirmed_at === null) {
                $enrollment->confirmed_at = now();
            }

            if ($enrollment->status === EnrollmentStatus::Cancelled && $enrollment->cancelled_at === null) {
                $enrollment->cancelled_at = now();
            }
        });
    }

    /** دوره‌هایی که درسِ این دوره را دارند و هنوز ثبت‌نام قطعی نشده‌اند. */
    public function duplicatesSubjectOf(): bool
    {
        return Course::query()
            ->where('subject_id', $this->course->subject_id)
            ->where('id', '!=', $this->course_id)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('student_id', $this->student_id)
                ->where('status', EnrollmentStatus::Confirmed->value))
            ->exists();
    }

    /** مبلغ نهایی: شهریهٔ پایهٔ دوره منهای تخفیف. */
    public function calculateFinalFee(): int
    {
        $base = $this->course?->base_fee ?? 0;

        return max(0, $base - $this->discount);
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }

    public function enrolledAtJalali(): ?string
    {
        return jalali_date($this->enrolled_at);
    }

    /**
     * ثبت‌نام جدید را ثبت می‌کند و خودش تصمیم می‌گیرد جا بگیرد یا نه.
     *
     * ظرفیت را این‌جا بررسی می‌کنیم و نه در فرم، چون دو کارمند پذیرش هم‌زمان
     * دارند ثبت می‌کنند و فرم همیشه ظرفیت را از زمانی نشان می‌دهد که خوانده شده.
     */
    public static function enroll(Student $student, Course $course, array $attributes = []): self
    {
        $status = EnrollmentStatus::Pending;

        if (! $course->status->acceptsEnrollment()) {
            throw ValidationException::withMessages([
                'course_id' => 'ثبت‌نام در این دوره باز نیست.',
            ]);
        }

        if ($course->isFull()) {
            $status = EnrollmentStatus::Waitlisted;
        }

        // active_key یکتا است، پس تکراری بودن از لایهٔ پایگاه داده می‌آید و پیام
        // قابل فهم به آن ترجمه می‌شود
        try {
            return static::create(array_merge($attributes, [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'enrolled_at' => $attributes['enrolled_at'] ?? now()->toDateString(),
                'status' => $status,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'student_id' => 'این دانش‌آموز قبلاً در این دوره ثبت‌نام شده است.',
            ]);
        }
    }

    /**
     * آیا ثبت‌نام در این دوره با ثبت‌نام‌های فعالِ دیگر دانش‌آموز تداخل دارد؟
     *
     * دو دوره با روز و ساعت یکسان یعنی دانش‌آموز باید در یک لحظه دو جا باشد.
     * ثبت‌نام‌های خودِ همین دانش‌آموز بررسی می‌شوند، نه ثبت‌نام کل آموزشگاه —
     * تداخل بین دو دانش‌آموز یک مشکل تقویم است، مانع ثبت‌نام هیچ‌کدام نیست.
     */
    public function conflictsWithExisting(): bool
    {
        $existingCourseIds = CourseEnrollment::query()
            ->where('student_id', $this->student_id)
            ->where('id', '!=', $this->id ?? 0)
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->pluck('course_id')
            ->all();

        if ($existingCourseIds === []) {
            return false;
        }

        $mine = $this->course->sessions()->get();

        $theirs = ClassSession::query()
            ->whereIn('course_id', $existingCourseIds)
            ->get();

        foreach ($mine as $session) {
            foreach ($theirs as $other) {
                if ($session->overlapsWith($other)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** تبدیل رزرو به ثبت‌نام قطعی، با بررسی دوبارهٔ ظرفیت. */
    public function confirm(): self
    {
        // تداخل زمانی در لحظهٔ تأیید بررسی می‌شود نه هنگام رزرو: بین آن دو ممکن
        // است دانش‌آموز در دورهٔ دیگری ثبت‌نام کرده باشد
        if ($this->conflictsWithExisting()) {
            throw ValidationException::withMessages([
                'status' => 'زمان این دوره با یکی از دوره‌های ثبت‌نام‌شدهٔ این دانش‌آموز تداخل دارد.',
            ]);
        }

        if ($this->course->isFull(exceptEnrollmentId: $this->getKey())) {
            throw ValidationException::withMessages([
                'status' => 'ظرفیت دوره پر است.',
            ]);
        }

        $this->update(['status' => EnrollmentStatus::Confirmed]);

        return $this->refresh();
    }

    public function cancel(?string $reason = null): self
    {
        $this->update(array_filter([
            'status' => EnrollmentStatus::Cancelled,
            'notes' => $reason ?: $this->notes,
        ]));

        return $this->refresh();
    }
}
