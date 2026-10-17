<?php

namespace App\Enums;

/**
 * وضعیت‌های چرخهٔ کاری.
 *
 * یک enum برای همه، چون «فعال/غیرفعال»، «پرداخت‌شده/بدهکار» و «ثبت‌نام‌شده/لغو»
 * سه چیز متفاوت‌اند که هرکدام قوانین خودشان را دارند (مثلاً بدهکار را می‌شود
 * پرداخت کرد، غیرفعال را نه). یک ثابت ثابتِ رشته‌ای برای همه، یعنی یک تست که
 * «پرداخت‌شده» معتبر می‌داند، فردا ناخواسته «غیرفعال» را هم معتبر می‌کند.
 */
enum Status: string
{
    // وضعیت عمومی رکورد
    case Active = 'active';           // فعال
    case Inactive = 'inactive';       // غیرفعال

    // وضعیت دانش‌آموز
    case Enrolling = 'enrolling';     // در حال ثبت‌نام
    case Graduated = 'graduated';     // فارغ‌التحصیل
    case Dropped = 'dropped';         // انصراف

    // وضعیت مالی
    case Unpaid = 'unpaid';           // پرداخت‌نشده
    case Partial = 'partial';         // پرداخت جزئی
    case Paid = 'paid';               // پرداخت‌شده
    case Refunded = 'refunded';       // بازگشت وجه

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Inactive => 'غیرفعال',
            self::Enrolling => 'در حال ثبت‌نام',
            self::Graduated => 'فارغ‌التحصیل',
            self::Dropped => 'انصراف',
            self::Unpaid => 'پرداخت‌نشده',
            self::Partial => 'پرداخت جزئی',
            self::Paid => 'پرداخت‌شده',
            self::Refunded => 'بازگشت وجه',
        };
    }

    /** رنگ badge در UI؛ تا وضعیت‌های نمایشی یکدست بمانند. */
    public function color(): string
    {
        return match ($this) {
            self::Active, self::Paid => 'badge-success',
            self::Inactive, self::Dropped, self::Refunded => 'badge-error',
            self::Enrolling, self::Partial => 'badge-warning',
            self::Graduated, self::Unpaid => 'badge-info',
        };
    }

    public static function options(?array $cases = null): array
    {
        $cases ??= self::cases();

        return array_map(
            fn (self $status) => ['id' => $status->value, 'name' => $status->label()],
            $cases,
        );
    }

    public static function rule(?array $cases = null): string
    {
        $cases ??= self::cases();

        return 'in:'.implode(',', array_column($cases, 'value'));
    }

    public static function labelFor(mixed $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return is_scalar($value)
            ? (self::tryFrom((string) $value)?->label() ?? '—')
            : '—';
    }

    /** آیا این وضعیت مالی یعنی بدهکاری هنوز باز است؟ */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::Unpaid, self::Partial], true);
    }
}
