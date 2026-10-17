<?php

namespace App\Models;

use Database\Factories\TeacherExperienceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * یک سابقهٔ شغلی در سوابق استاد.
 *
 * یک ردیف = یک بازه در یک جا. سوابق تاریخی‌اند و از رکورد استاد جدا می‌شوند تا
 * حذف یک سابقه، پروندهٔ خود استاد را تخریب نکند.
 */
class TeacherExperience extends Model
{
    /** @use HasFactory<TeacherExperienceFactory> */
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'title',
        'organization',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $experience) {
            // سابقه‌ای که پیش از شروعش تمام شده یعنی ترتیب زمانی جابه‌جا شده و
            // «سابقهٔ فعلی» (آخرین شروع‌شده) دیگر معنای خود را ندارد
            if ($experience->ended_at !== null && $experience->ended_at->lt($experience->started_at)) {
                throw ValidationException::withMessages([
                    'ended_at' => 'تاریخ پایان سابقه نمی‌تواند قبل از شروع آن باشد.',
                ]);
            }
        });
    }

    public function startedAtJalali(): ?string
    {
        return jalali_date($this->started_at);
    }

    public function endedAtJalali(): ?string
    {
        return jalali_date($this->ended_at);
    }

    /** سابقه‌ای که تاریخ پایان ندارد و هنوز ادامه دارد. */
    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    /** عنوان + محل، بدون مدیریت حالت «خالی» در هر view. */
    public function label(): string
    {
        return collect([$this->title, $this->organization])
            ->filter()
            ->implode(' — ');
    }

    public function periodLabel(): string
    {
        return $this->startedAtJalali().' تا '.($this->isCurrent() ? 'اکنون' : $this->endedAtJalali());
    }
}
