<?php

namespace App\Enums;

/**
 * روش پرداخت.
 *
 * هر روش یک شمارهٔ پیگیری دارد مگر نقدی: «کارت به کارت» بدون شمارهٔ پیگیری یعنی
 * یک پرداخت غیرقابل‌پیگیری که موقع اختلاف با بانک هیچ سندی ندارد.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';                 // نقدی
    case Card = 'card';                 // کارت به کارت
    case Pos = 'pos';                   // کارتخوان
    case Transfer = 'transfer';         // انتقال بانکی
    case Cheque = 'cheque';             // چک

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدی',
            self::Card => 'کارت به کارت',
            self::Pos => 'کارتخوان',
            self::Transfer => 'انتقال بانکی',
            self::Cheque => 'چک',
        };
    }

    public function requiresReference(): bool
    {
        return $this !== self::Cash;
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'badge-ghost',
            self::Pos => 'badge-success',
            self::Card, self::Transfer => 'badge-info',
            self::Cheque => 'badge-warning',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $m) => ['id' => $m->value, 'name' => $m->label()], self::cases());
    }

    public static function labelFor(mixed $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return is_scalar($value) ? (self::tryFrom((string) $value)?->label() ?? '—') : '—';
    }
}
