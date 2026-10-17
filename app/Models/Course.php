<?php

namespace App\Models;

use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * دورهٔ آموزشی.
 *
 * دوره «نمونه‌ای از یک درس با یک استاد در یک بازه» است — ظرفیت و شهریهٔ خودش را
 * دارد. درس، ظرفیت سالانهٔ کل آموزشگاه را نگه می‌دارد.
 */
class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subject_id',
        'teacher_id',
        'capacity',
        'session_count',
        'session_duration',
        'base_fee',
        'status',
        'starts_at',
        'description',
    ];

    /** پیش‌فرض‌های سمت مدل تا شیء درست بعد از create() هم کامل باشد. */
    protected $attributes = [
        'status' => 'open',
        'capacity' => 0,
        'session_count' => 0,
        'session_duration' => 90,
        'base_fee' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'starts_at' => 'date',
            'capacity' => 'integer',
            'session_count' => 'integer',
            'session_duration' => 'integer',
            'base_fee' => 'integer',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $course) {
            // ظرفیت صفر یعنی «بی‌نهایت»، پس منفی معنا ندارد
            if ($course->capacity < 0) {
                throw ValidationException::withMessages([
                    'capacity' => 'ظرفیت نمی‌تواند منفی باشد.',
                ]);
            }

            // شهریهٔ منفی یعنی آموزشگاه به دانش‌آموز بدهکار می‌شود
            if ($course->base_fee < 0) {
                throw ValidationException::withMessages([
                    'base_fee' => 'شهریه نمی‌تواند منفی باشد.',
                ]);
            }

            if ($course->session_duration <= 0) {
                throw ValidationException::withMessages([
                    'session_duration' => 'مدت هر جلسه باید بیشتر از صفر باشد.',
                ]);
            }
        });
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }

    /** مدت کل دوره بر حسب ساعت — چیزی که آموزشگاه واقعاً برنامه‌ریزی می‌کند. */
    public function totalHours(): float
    {
        return $this->session_count * $this->session_duration / 60;
    }

    public function startsAtJalali(): ?string
    {
        return jalali_date($this->starts_at);
    }

    public function scopeStatus(Builder $query, CourseStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof CourseStatus ? $status->value : $status);
    }

    /** فقط دوره‌هایی که هنوز می‌شود در آن ثبت‌نام کرد. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', CourseStatus::Open->value);
    }
}
