<?php

namespace App\Enums;

/**
 * وضعیت فارغ‌التحصیلی در سوابق تحصیلی قبلی.
 *
 * «در حال تحصیل» لازم است نه فقط بله/خیر: دانش‌آموزی که از مدرسه‌ای منتقل
 * می‌شود ممکن است سال قبل را تمام کرده باشد ولی هنوز فارغ‌التحصیل نشده باشد،
 * و «ترک تحصیل» هم با «هنوز تمام نشده» یکی نیست — این دو برای ارزیابیِ پایهٔ
 * ورود و گزارش‌های آموزشی معنای متفاوتی دارند.
 */
enum PreviousStatus: string
{
    case Graduated = 'graduated';     // فارغ‌التحصیل
    case Continuing = 'continuing';   // در حال تحصیل
    case DroppedOut = 'dropped_out';  // ترک تحصیل

    public function label(): string
    {
        return match ($this) {
            self::Graduated => 'فارغ‌التحصیل',
            self::Continuing => 'در حال تحصیل',
            self::DroppedOut => 'ترک تحصیل',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Graduated => 'badge-success',
            self::Continuing => 'badge-info',
            self::DroppedOut => 'badge-error',
        };
    }

    /** گزینه‌های select با ساختاری که x-select می‌خواهد. */
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
}
