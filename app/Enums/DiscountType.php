<?php

namespace App\Enums;

/**
 * نوع تخفیف.
 *
 * تخفیف مبلغی و درصدی جدا هستند چون فقط یکی‌شان درست اعمال می‌شود: گرفتن
 * ۱۰٪ از «۲۰۰ هزار تومان کسری» با گرفتن ۱۰ هزار تومان فرق دارد و اشتباه بین
 * این دو، تخفیف اشتباهی به خانواده می‌دهد.
 */
enum DiscountType: string
{
    case Percent = 'percent';   // درصدی، از مبلغ قابل تخفیف
    case Fixed = 'fixed';       // مبلغ ثابت

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'درصدی',
            self::Fixed => 'مبلغ ثابت',
        };
    }

    public function symbol(): string
    {
        return $this === self::Percent ? '٪' : 'تومان';
    }

    /**
     * تخفیف روی یک مبلغ.
     *
     * نتیجه هرگز منفی و هرگز بیشتر از خود مبلغ نمی‌شود: تخفیف ۲۰۰٪ روی
     * ۱۰۰ هزار تومان نباید فاکتور را به یک Request پولی تبدیل کند.
     */
    public function applyTo(int $amount, int $value): int
    {
        $discount = $this === self::Percent
            ? (int) round($amount * $value / 100)
            : $value;

        return max(0, min($discount, $amount));
    }

    public static function options(): array
    {
        return array_map(fn (self $t) => ['id' => $t->value, 'name' => $t->label()], self::cases());
    }
}
