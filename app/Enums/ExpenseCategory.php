<?php

namespace App\Enums;

/**
 * دستهٔ هزینه.
 *
 * enum است چون گزارش صورت سود و زیان روی همین دسته‌ها جمع می‌زند. رشتهٔ آزاد
 * یعنی «اجاره»،«اجاره » و «اجاره‌ی محل» سه دستهٔ متفاوت در یک گزارش.
 */
enum ExpenseCategory: string
{
    case Rent = 'rent';             // اجارهٔ محل
    case Salary = 'salary';         // حقوق و دستمزد
    case Utilities = 'utilities';   // آب، برق، گاز، اینترنت
    case Supplies = 'supplies';     // لوازم آموزشی و مصرفی
    case Equipment = 'equipment';   // تجهیزات و اثاثیه
    case Marketing = 'marketing';   // تبلیغات و بازاریابی
    case Maintenance = 'maintenance'; // تعمیرات
    case Tax = 'tax';               // مالیات و عوارض
    case Other = 'other';           // سایر

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'اجاره',
            self::Salary => 'حقوق و دستمزد',
            self::Utilities => 'قبوض و خدمات شهری',
            self::Supplies => 'لوازم آموزشی',
            self::Equipment => 'تجهیزات و اثاثیه',
            self::Marketing => 'تبلیغات',
            self::Maintenance => 'تعمیرات',
            self::Tax => 'مالیات و عوارض',
            self::Other => 'سایر',
        };
    }

    /** دسته‌های ثابت در برابر متغیر — گزارش سود این تفکیک را می‌خواهد. */
    public function isFixed(): bool
    {
        return in_array($this, [self::Rent, self::Salary], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Rent, self::Salary => 'badge-primary',
            self::Utilities, self::Tax => 'badge-info',
            self::Supplies, self::Equipment => 'badge-success',
            self::Marketing => 'badge-warning',
            self::Maintenance => 'badge-accent',
            self::Other => 'badge-ghost',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => ['id' => $c->value, 'name' => $c->label()], self::cases());
    }

    public static function labelFor(mixed $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return is_scalar($value) ? (self::tryFrom((string) $value)?->label() ?? '—') : '—';
    }
}
