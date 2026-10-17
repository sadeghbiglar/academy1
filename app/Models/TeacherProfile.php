<?php

namespace App\Models;

use App\Enums\Status;
use Database\Factories\TeacherProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * نیم‌رخ پرسنلی استاد.
 *
 * جدا از خود Teacher چون این داده‌ها مالکیت و سرنوشت متفاوتی دارند: نام و کد ملی
 * تقریباً تغییرناپذیر است، ولی سمت، وضعیت استخدام و شمارهٔ بیمه در طول همکاری
 * عوض می‌شوند. یک جدول، به‌روزرسانی بیمه را با ویرایش ردیف استاد قاطی می‌کند.
 */
class TeacherProfile extends Model
{
    /** @use HasFactory<TeacherProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'employee_code',
        'position',
        'status',
        'started_at',
        'ended_at',
        'insurance_number',
    ];

    /** پیش‌فرض سمت مدل، نه فقط پایگاه داده — وگرنه شیء درست بعد از create() وضعیت نمی‌داند. */
    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(TeacherExperience::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile) {
            if ($profile->ended_at !== null && $profile->started_at !== null
                && $profile->ended_at->lt($profile->started_at)) {
                throw ValidationException::withMessages([
                    'ended_at' => 'تاریخ پایان همکاری نمی‌تواند قبل از شروع آن باشد.',
                ]);
            }

            // همکاریِ فعال که تاریخ پایان دارد یعنی در گزارش حقوق هم فعال
            // شمرده می‌شود و هم بازنشسته — که انتخاب تاریخ پایان را بی‌اثر می‌کند
            if ($profile->status === Status::Active && $profile->ended_at !== null) {
                throw ValidationException::withMessages([
                    'ended_at' => 'استاد فعال نمی‌تواند تاریخ پایان همکاری داشته باشد.',
                ]);
            }

            // بازنشسته بدون تاریخ پایان یعنی حقوقش تا ابد قطع نمی‌شود؛ مرخصی
            // استثناست چون همکاری تمام نشده و فقط تعلیق است
            if ($profile->status === Status::Inactive && $profile->ended_at === null) {
                throw ValidationException::withMessages([
                    'ended_at' => 'استاد بازنشسته باید تاریخ پایان همکاری داشته باشد.',
                ]);
            }
        });
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }

    public function startedAtJalali(): ?string
    {
        return jalali_date($this->started_at);
    }

    public function endedAtJalali(): ?string
    {
        return jalali_date($this->ended_at);
    }

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }

    /** همکاری تمام شده: نه فقط غیرفعال، بلکه با تاریخ پایان — گزارش حقوق به تاریخ نیاز دارد نه به برچسب. */
    public function isRetired(): bool
    {
        return ! $this->isActive() && $this->ended_at !== null;
    }

    /** نیم‌رخی که تاریخ پایان دارد ولی وضعیت فعال است — ناسازگاری داده‌ای که باید دیده شود. */
    public function hasInconsistentDates(): bool
    {
        return $this->ended_at !== null && $this->isActive();
    }
}
