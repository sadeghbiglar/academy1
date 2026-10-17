<?php

namespace App\Enums;

/**
 * روز هفته.
 *
 * enum نه عدد ۱..۷ و نه رشتهٔ آزاد: «شنبه» و «shanbeh» و «۰» نباید سه رکورد
 * متفاوت برای یک روز بسازند، و ۰ یکشنبه در برابر ۱ شنبه دقیقاً همان دستهٔ
 * اشتباهی است که به تقویم اشتباه منجر می‌شود.
 *
 * مقدار ذخیره‌شده همان شمارهٔ استاندارد ISO است تا محاسبهٔ هفته و تبدیل تاریخ
 * بدون جدول نگاشت اضافه انجام شود.
 */
enum WeekDay: int
{
    case Saturday = 1;
    case Sunday = 2;
    case Monday = 3;
    case Tuesday = 4;
    case Wednesday = 5;
    case Thursday = 6;
    case Friday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Saturday => 'شنبه',
            self::Sunday => 'یکشنبه',
            self::Monday => 'دوشنبه',
            self::Tuesday => 'سه‌شنبه',
            self::Wednesday => 'چهارشنبه',
            self::Thursday => 'پنجشنبه',
            self::Friday => 'جمعه',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Saturday => 'ش',
            self::Sunday => 'ی',
            self::Monday => 'د',
            self::Tuesday => 'س',
            self::Wednesday => 'چ',
            self::Thursday => 'پ',
            self::Friday => 'ج',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $day) => ['id' => $day->value, 'name' => $day->label()], self::cases());
    }

    public static function labelFor(mixed $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return is_numeric($value) ? (self::tryFrom((int) $value)?->label() ?? '—') : '—';
    }

    /**
     * روز هفتهٔ یک تاریخ، بدون تبدیل به شمسی.
     *
     * PHP روز را ISO می‌دهد: شنبه = 6 و یکشنبه = 7. تقویم فارسی شنبه = 1 است.
     * فرمول (N + 1) % 7 + 1 این چرخش را انجام می‌دهد؛ N + 1 تنها ساده به‌نظر
     * می‌رسد و شنبه را ۷ (جمعه) می‌کند.
     */
    public static function fromDate(\DateTimeInterface $date): self
    {
        return self::from(((int) $date->format('N') + 1) % 7 + 1);
    }
}
