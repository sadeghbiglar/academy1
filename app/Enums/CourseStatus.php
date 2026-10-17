<?php

namespace App\Enums;

/**
 * وضعیت دوره.
 *
 * «باز» و «بسته» یعنی هنوز می‌شود ثبت‌نام کرد یا نه. «بایگانی‌شده» یعنی
 * دوره تمام شده و از فهرست‌های روزمره بیرون است ولی رکوردش باید بماند چون
 * کارنامه، نمرات و فاکتورهایش به آن ارجاع دارند.
 */
enum CourseStatus: string
{
    case Open = 'open';           // باز به ثبت‌نام
    case Closed = 'closed';       // بسته — پر یا مهلت تمام
    case Archived = 'archived';   // بایگانی‌شده

    public function label(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::Closed => 'بسته',
            self::Archived => 'بایگانی‌شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'badge-success',
            self::Closed => 'badge-warning',
            self::Archived => 'badge-ghost',
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

    /** آیا هنوز می‌شود در این دوره ثبت‌نام کرد؟ */
    public function acceptsEnrollment(): bool
    {
        return $this === self::Open;
    }
}
