<?php

namespace App\Models;

use App\Enums\GradeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * یک نمرهٔ خام.
 *
 * «بیست نمرهٔ تکلیف» بیست سطر Grade است، نه یک ستون. نگهداری فقط نمرهٔ
 * تجمیعی یعنی از دست رفتن ریزنمرات، و کارنامه بدون آن قابل بازسازی نیست.
 */
class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'user_id',
        'type',
        'title',
        'score',
        'max_score',
        'graded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => GradeType::class,
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'graded_at' => 'date',
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

    /** استادی که نمره را ثبت کرده. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $grade) {
            if ((float) $grade->max_score <= 0) {
                throw ValidationException::withMessages([
                    'max_score' => 'نمرهٔ حداکثر باید بیشتر از صفر باشد.',
                ]);
            }

            // نمرهٔ بیشتر از حداکثر یعنی نمرهٔ غلط ثبت شده؛ سیستم نباید آن را
            // بپذیرد و بعد در کارنامه عددی بالاتر از ۲۰ نشان دهد
            if ((float) $grade->score > (float) $grade->max_score) {
                throw ValidationException::withMessages([
                    'score' => 'نمره نمی‌تواند از حداکثر بیشتر باشد.',
                ]);
            }

            if ((float) $grade->score < 0) {
                throw ValidationException::withMessages([
                    'score' => 'نمره نمی‌تواند منفی باشد.',
                ]);
            }
        });
    }

    public function typeLabel(): string
    {
        return $this->type?->label() ?? '—';
    }

    /** درصد این نمره، مثلاً ۸۵٫۰۰ */
    public function percentage(): float
    {
        return round((float) $this->score / (float) $this->max_score * 100, 2);
    }

    public function scoreLabel(): string
    {
        return to_persian_digits(number_format((float) $this->score, 2)).'/'
            .to_persian_digits(number_format((float) $this->max_score, 2));
    }

    public function gradedAtJalali(): ?string
    {
        return $this->graded_at ? jalali_date($this->graded_at) : null;
    }

    /**
     * ثبت یا اصلاح نمرهٔ یک تکلیف مشخص.
     *
     * updateOrCreate با کلید ایندکس یکتا هماهنگ است، پس دکمهٔ «ذخیره» دوباره
     * زده شود نمره را تکرار نمی‌کند.
     */
    public static function record(
        Student $student,
        Course $course,
        GradeType $type,
        string $title,
        float $score,
        ?float $maxScore = null,
        ?User $by = null,
        ?string $gradedAt = null,
    ): self {
        $maxScore ??= 20.0;

        return static::updateOrCreate(
            [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'type' => $type->value,
                'title' => $title,
            ],
            [
                'score' => $score,
                'max_score' => $maxScore,
                'user_id' => $by?->id ?? auth()->id(),
                'graded_at' => $gradedAt,
            ],
        );
    }

    /** نمره‌های یک دانش‌آموز در یک دوره، برای محاسبهٔ میانگین. */
    public static function forStudentInCourse(Student $student, Course $course)
    {
        return static::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->get();
    }
}
