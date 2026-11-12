<?php

namespace App\Enums;

/**
 * وضعیت پرداخت فیش حقوقی.
 *
 * «در انتظار پرداخت» با «پرداخت‌شده» جدا نگه داشته می‌شوند چون فیشِ صادرشده
 * هنوز پولی است که پرداخت نشده؛ اگر هر دو «تمام» حساب شوند، گزارشِ ماهانهٔ
 * بدهی به اساتید از بین می‌رود.
 */
enum PayrollStatus: string
{
    case Pending = 'pending';   // در انتظار پرداخت
    case Paid = 'paid';         // پرداخت‌شده

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار پرداخت',
            self::Paid => 'پرداخت‌شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'badge-warning',
            self::Paid => 'badge-success',
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
}
