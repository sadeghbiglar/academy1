<?php

namespace App\Enums;

/**
 * وضعیت ثبت‌نام دانش‌آموز در دوره.
 *
 * تفاوت pending و confirmed مهم است: ثبت‌نام که فقط رزرو جا است تا وقتی خانواده
 * شهریه را ندهد نباید در ظرفیت حساب شود یا در کارنامه بیاید.
 *
 * cancelled با حذف فرق دارد — لغو یعنی «این آدم آمد و برگشت» و در سابقه
 * می‌ماند، حذف یعنی «اصلاً نبود» و برای داده‌ای که اشتباه ثبت شده است.
 */
enum EnrollmentStatus: string
{
    case Pending = 'pending';         // رزرو، هنوز تأیید نشده
    case Confirmed = 'confirmed';     // قطعی
    case Waitlisted = 'waitlist';     // صف انتظار — ظرفیت پر بود
    case Cancelled = 'cancelled';     // لغو شده

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار تأیید',
            self::Confirmed => 'قطعی',
            self::Waitlisted => 'صف انتظار',
            self::Cancelled => 'لغو شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'badge-success',
            self::Pending => 'badge-warning',
            self::Waitlisted => 'badge-info',
            self::Cancelled => 'badge-error',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['id' => $s->value, 'name' => $s->label()], self::cases());
    }

    public static function labelFor(mixed $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return is_scalar($value) ? (self::tryFrom((string) $value)?->label() ?? '—') : '—';
    }

    /**
     * آیا این ثبت‌نام جایی از ظرفیت دوره را اشغال می‌کند؟
     *
     * نه pending (هنوز نپرداخته) و نه waitlisted (فعلاً جایی ندارد). اگر این
     * تابع اشتباه باشد، دوره پرتر از ظرفیتش نشان داده می‌شود.
     */
    public function occupiesSeat(): bool
    {
        return $this === self::Confirmed;
    }

    /** ثبت‌نامی که باید در آمار فعال دیده شود. */
    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }
}
