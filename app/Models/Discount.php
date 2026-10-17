<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * یک قاعدهٔ تخفیف که هنگام صدور فاکتور انتخاب می‌شود.
 *
 * این «قاعده» است، نه تخفیفِ اعمال‌شده. مبلغی که واقعاً روی فاکتور نشسته
 * همان‌جا ذخیره می‌شود تا تغییر بعدی این قاعده، فاکتورهای قدیمی را جابه‌جا
 * نکند.
 */
class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'value',
        'course_id',
        'student_id',
        'starts_at',
        'expires_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'starts_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // ── دامنه ──────────────────────────────────────────────

    /** آیا این تخفیف به این دانش‌آموز و این دوره می‌خورد؟ */
    public function appliesTo(?Student $student, ?Course $course, ?string $on = null): bool
    {
        if ($this->student_id !== null && $this->student_id !== $student?->id) {
            return false;
        }

        if ($this->course_id !== null && $this->course_id !== $course?->id) {
            return false;
        }

        return $this->isActive($on);
    }

    /**
     * در بازهٔ اعتبار هست؟
     *
     * انقضا در پایان روز است، نه ابتدای آن: تخفیفی که «تا ۳۱ خرداد» معتبر است
     * باید در خودِ ۳۱ خرداد هم اعمال شود. مقایسهٔ تاریخ (نه لحظه) لازم است،
     * چون cast روی date نیمه‌شب می‌دهد.
     */
    public function isActive(?string $on = null): bool
    {
        $date = $on ? Carbon::parse($on)->startOfDay() : today();

        if ($this->starts_at !== null && $this->starts_at->gt($date)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->lt($date)) {
            return false;
        }

        return true;
    }

    public function isExpired(?string $on = null): bool
    {
        return ! $this->isActive($on);
    }

    /** هنوز شروع نشده: قاعده هست ولی امروز قابل استفاده نیست. */
    public function isScheduled(?string $on = null): bool
    {
        $date = $on ? Carbon::parse($on)->startOfDay() : today();

        return $this->starts_at !== null && $this->starts_at->gt($date);
    }

    public function scopeActive(Builder $query, ?string $on = null): Builder
    {
        $date = $on ? Carbon::parse($on)->startOfDay() : today();

        return $query
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $date))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', $date));
    }

    /** تخفیف‌های قابل انتخاب برای یک فاکتور. */
    public static function availableFor(?Student $student, ?Course $course, ?string $on = null)
    {
        return static::query()
            ->active($on)
            ->where(fn ($q) => $q->whereNull('student_id')->orWhere('student_id', $student?->id))
            ->where(fn ($q) => $q->whereNull('course_id')->orWhere('course_id', $course?->id))
            ->get();
    }

    // ── اعمال ──────────────────────────────────────────────

    /** تخفیف این قاعده روی یک مبلغ. */
    public function amountOn(int $amount): int
    {
        return $this->type->applyTo($amount, $this->value);
    }

    /**
     * مجموع چند تخفیف روی یک مبلغ، با سقفِ خود مبلغ.
     *
     * تخفیف‌ها پشت سر هم اعمال می‌شوند و هرکدام باقی‌مانده را می‌بینند. نتیجه
     * هرگز منفی و هرگز بیشتر از خود مبلغ نیست.
     *
     * @param  iterable<Discount>  $discounts
     */
    public static function combinedAmount(iterable $discounts, int $amount): int
    {
        $remaining = $amount;

        foreach ($discounts as $discount) {
            $remaining -= $discount->amountOn($remaining);
        }

        return $amount - max(0, min($amount, $remaining));
    }

    // ── نمایش ──────────────────────────────────────────────

    public function valueLabel(): string
    {
        return $this->type === DiscountType::Percent
            ? to_persian_digits(number_format($this->value)).'٪'
            : to_persian_digits(number_format($this->value)).' تومان';
    }

    public function scopeLabel(): string
    {
        if ($this->student_id !== null && $this->course_id !== null) {
            return 'دانش‌آموز و دوره';
        }

        return match (true) {
            $this->student_id !== null => 'یک دانش‌آموز',
            $this->course_id !== null => 'یک دوره',
            default => 'همه',
        };
    }

    public function expiresAtJalali(): ?string
    {
        return $this->expires_at ? jalali_date($this->expires_at) : null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $discount) {
            if ($discount->value <= 0) {
                throw ValidationException::withMessages([
                    'value' => 'مقدار تخفیف باید بیشتر از صفر باشد.',
                ]);
            }

            // تخفیف درصدی بالای ۱۰۰٪ فاکتور را منفی می‌کند
            if ($discount->type === DiscountType::Percent && $discount->value > 100) {
                throw ValidationException::withMessages([
                    'value' => 'تخفیف درصدی نمی‌تواند بیشتر از ۱۰۰ درصد باشد.',
                ]);
            }

            if ($discount->starts_at !== null
                && $discount->expires_at !== null
                && $discount->expires_at->lt($discount->starts_at)) {
                throw ValidationException::withMessages([
                    'expires_at' => 'تاریخ انقضا نمی‌تواند قبل از تاریخ شروع باشد.',
                ]);
            }
        });
    }
}
