<?php

namespace App\Enums;

/**
 * نوع نمره.
 *
 * وزن هر نوع در محاسبهٔ میانگین از همین‌جا می‌آید، پس enum لازم است: نوعی که
 * وزنش تعریف نشده باشد یا غلط حساب می‌شود یا بی‌صدا از میانگین حذف می‌شود.
 */
enum GradeType: string
{
    case Homework = 'homework';   // تکلیف
    case Quiz = 'quiz';           // آزمون کوتاه
    case Midterm = 'midterm';     // میان‌ترم
    case Final = 'final';         // پایان‌ترم
    case Continuous = 'continuous'; // نمرهٔ مستمر

    public function label(): string
    {
        return match ($this) {
            self::Homework => 'تکلیف',
            self::Quiz => 'آزمون کوتاه',
            self::Midterm => 'میان‌ترم',
            self::Final => 'پایان‌ترم',
            self::Continuous => 'نمرهٔ مستمر',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Final, self::Midterm => 'badge-primary',
            self::Quiz => 'badge-info',
            self::Continuous => 'badge-success',
            self::Homework => 'badge-ghost',
        };
    }

    /**
     * وزن نوع در میانگین پایان‌ترم.
     *
     * مستمر و تکلیف بیشترین وزن را دارند چون در طول ترم جمع می‌شوند، و پایان‌ترم
     * با وجود یک‌بار بودن سنگین است.
     */
    public function weight(): float
    {
        return match ($this) {
            self::Continuous => 4.0,
            self::Homework => 3.0,
            self::Quiz => 2.0,
            self::Midterm => 4.0,
            self::Final => 5.0,
        };
    }

    /** آیا این نوع در محاسبهٔ نمرهٔ نهایی دخالت دارد؟ */
    public function countsTowardFinal(): bool
    {
        return true;
    }

    public static function options(): array
    {
        return array_map(fn (self $t) => ['id' => $t->value, 'name' => $t->label()], self::cases());
    }

    public static function labelFor(mixed $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return is_scalar($value) ? (self::tryFrom((string) $value)?->label() ?? '—') : '—';
    }

    /** @return array<int, GradeType> */
    public static function continuous(): array
    {
        return [self::Continuous, self::Homework];
    }
}
