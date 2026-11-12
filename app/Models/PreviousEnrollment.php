<?php

namespace App\Models;

use App\Enums\PreviousStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سوابق تحصیلی قبلیِ یک دانش‌آموز (رونویس از مدرسهٔ دیگر).
 *
 * نام جدول «enrollments_previous» است نه «previous_enrollments» چون نامِ
 * جدول بخشی از قرارداد پروژه (issue 38) است و مدل با $table خودش را با آن
 * هماهنگ می‌کند.
 *
 * سال تحصیلی شمسی ذخیره می‌شود (۱۴۰۳): برچسبِ سال است نه روزِ تقویم، پس
 * قاعدهٔ «ذخیرهٔ میلادی» برایش صدق نمی‌کند و نمایش‌اش هم ارقام فارسی است.
 */
class PreviousEnrollment extends Model
{
    use HasFactory;

    /** نامِ توافق‌شدهٔ جدول در این پروژه. */
    protected $table = 'enrollments_previous';

    protected $fillable = [
        'student_id',
        'school',
        'academic_year',
        'grade',
        'previous_grades',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'academic_year' => 'integer',
            'status' => PreviousStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }

    public function statusColor(): string
    {
        return $this->status?->color() ?? 'badge-ghost';
    }

    /** سال به ارقام فارسی برای نمایش. */
    public function yearLabel(): string
    {
        return to_persian_digits($this->academic_year);
    }

    /** برچسب فارسی پایه؛ مقدار ناشناخته همان مقدار خام است نه خطای رندر. */
    public function gradeLabel(): string
    {
        foreach (self::levels() as $level) {
            if ($level['id'] === (string) $this->grade) {
                return $level['name'];
            }
        }

        return (string) $this->grade;
    }

    /**
     * پایه‌های نظام آموزشی (۱۲ پایه) با برچسب فارسی.
     *
     * منبع واحد برای select و نمایش، مثل types() در Document — اگر برچسب فقط
     * در ویو بود، جدول و فهرست به‌مرور با هم اختلاف می‌افتادند.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function levels(): array
    {
        $ordinals = [
            1 => 'اول', 2 => 'دوم', 3 => 'سوم', 4 => 'چهارم',
            5 => 'پنجم', 6 => 'ششم', 7 => 'هفتم', 8 => 'هشتم',
            9 => 'نهم', 10 => 'دهم', 11 => 'یازدهم', 12 => 'دوازدهم',
        ];

        return array_map(
            fn (int $n) => ['id' => (string) $n, 'name' => 'پایهٔ '.$ordinals[$n]],
            range(1, 12),
        );
    }

    /**
     * ثبت یک رونویس.
     *
     * @param  int  $academicYear  سال تحصیلی شمسی، پیش‌تر با parse_integer از ورودی کاربر
     */
    public static function record(
        Student $student,
        string $school,
        int $academicYear,
        string $grade,
        ?string $previousGrades,
        PreviousStatus $status,
    ): self {
        return static::create([
            'student_id' => $student->id,
            'school' => $school,
            'academic_year' => $academicYear,
            'grade' => $grade,
            'previous_grades' => $previousGrades,
            'status' => $status,
        ]);
    }
}
