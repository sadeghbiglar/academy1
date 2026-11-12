<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * فیش حقوقی یک استاد برای یک ماه شمسی.
 *
 * خالصِ پرداختی ستونی است که کاربر نمی‌نویسد: در هوکِ saving از ناخالص و
 * کسورات ساخته می‌شود تا فیش هیچ‌وقت با ستون‌هایش نخوانَد.
 */
class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'period_year',
        'period_month',
        'days_worked',
        'overtime_hours',
        'insurance',
        'tax',
        'gross',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'days_worked' => 'integer',
            'overtime_hours' => 'decimal:1',
            'insurance' => 'integer',
            'tax' => 'integer',
            'gross' => 'integer',
            'net' => 'integer',
            'status' => PayrollStatus::class,
            'paid_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $payroll) {
            // خالص همیشه از بقیهٔ ستون‌ها ساخته می‌شود، حتی اگر کسی مستقیم
            // مقداری هم فرستاده باشد (که نباید بفرستد: net fillable نیست)
            $net = (int) $payroll->gross - (int) $payroll->insurance - (int) $payroll->tax;

            if ($net < 0) {
                // کسورات از ناخالص بیشتر است: فیشِ منفی نه معنا دارد نه قابل پرداخت
                throw ValidationException::withMessages([
                    'net' => 'جمع کسورات از مبلغ ناخالص بیشتر است.',
                ]);
            }

            $payroll->net = $net;
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** «آبان ۱۴۰۴» — ماهِ شمسی با نام فارسی. */
    public function periodLabel(): string
    {
        return jalali_month_name($this->period_month).' '.to_persian_digits($this->period_year);
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }

    public function statusColor(): string
    {
        return $this->status?->color() ?? 'badge-ghost';
    }

    /** مبلغ با ارقام فارسی و جداکنندهٔ هزارگان؛ واحدش را خودِ نما می‌گوید. */
    public function moneyLabel(int $amount): string
    {
        return to_persian_digits(number_format($amount));
    }

    public function grossLabel(): string
    {
        return $this->moneyLabel((int) $this->gross);
    }

    public function netLabel(): string
    {
        return $this->moneyLabel((int) $this->net);
    }

    public function paidAtJalali(): ?string
    {
        return $this->paid_at ? jalali_date($this->paid_at) : null;
    }
}
