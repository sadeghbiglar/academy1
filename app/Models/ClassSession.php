<?php

namespace App\Models;

use App\Enums\WeekDay;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * یک جلسهٔ کلاس: چه روزی، چه ساعتی، کجا.
 *
 * ساعت‌ها دقیقه از نیمه‌شب ذخیره می‌شوند (۱۶:۳۰ = ۹۹۰)، نه رشتهٔ 'HH:MM'.
 * با رشته، '9:00' از '10:00' بزرگ‌تر به نظر می‌رسد و مرتب‌سازی و تشخیص تداخل
 * هر دو غلط می‌شوند.
 */
class ClassSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'week_number',
        'week_day',
        'start_minute',
        'end_minute',
        'room',
        'session_date',
        'is_makeup',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'week_day' => WeekDay::class,
            'week_number' => 'integer',
            'start_minute' => 'integer',
            'end_minute' => 'integer',
            'is_makeup' => 'boolean',
            'session_date' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $session) {
            // جلسهٔ با پایان قبل از شروع یعنی دادهٔ خراب؛ در پایگاه داده هم
            // دنباله می‌شود چون این‌جا تنها جایی است که همهٔ مسیرها را می‌بیند
            if ($session->end_minute <= $session->start_minute) {
                throw ValidationException::withMessages([
                    'end_minute' => 'ساعت پایان باید بعد از ساعت شروع باشد.',
                ]);
            }

            if ($session->start_minute < 0 || $session->start_minute >= 24 * 60) {
                throw ValidationException::withMessages([
                    'start_minute' => 'ساعت شروع معتبر نیست.',
                ]);
            }

            if ($session->week_number < 1 || $session->week_number > 53) {
                throw ValidationException::withMessages([
                    'week_number' => 'شمارهٔ هفته باید بین ۱ تا ۵۳ باشد.',
                ]);
            }
        });
    }

    /** «۱۶:۳۰» — برای نمایش. */
    public function startTimeLabel(): string
    {
        return $this->minuteToLabel($this->start_minute);
    }

    public function endTimeLabel(): string
    {
        return $this->minuteToLabel($this->end_minute);
    }

    /** '۱۶:۳۰–۱۸:۰۰' */
    public function timeRangeLabel(): string
    {
        return $this->startTimeLabel().'–'.$this->endTimeLabel();
    }

    public function durationMinutes(): int
    {
        return $this->end_minute - $this->start_minute;
    }

    /** زمان تقویم باید شمسی باشد (R1). */
    public function sessionDateJalali(): ?string
    {
        return $this->session_date ? jalali_date($this->session_date) : null;
    }

    public function dayLabel(): string
    {
        return $this->week_day?->label() ?? '—';
    }

    /**
     * دو بازهٔ زمانی هم‌پوشانی دارند یا نه.
     *
     * شروع و پایان هر دو بسته‌اند، پس جلسه‌ای که ۱۰:۰۰ شروع و ۱۰:۰۰ تمام
     * می‌شود با جلسه‌ای که ۱۰:۰۰ شروع می‌شود تداخل دارد — وگرنه دانش‌آموز باید
     * در یک لحظه در دو کلاس باشد.
     */
    public function overlapsWith(self $other): bool
    {
        if ($this->week_day !== $other->week_day) {
            return false;
        }

        // دو هفتهٔ متفاوت هیچ‌وقت تداخل ندارند
        if ($this->week_number !== $other->week_number) {
            return false;
        }

        return $this->start_minute <= $other->end_minute
            && $other->start_minute <= $this->end_minute;
    }

    private function minuteToLabel(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
