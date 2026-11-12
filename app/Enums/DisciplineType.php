<?php

namespace App\Enums;

/**
 * نوع رخداد انضباطی.
 *
 * «تقدیر» هم مثل «تذکر» رفتار است و باید ثبت شود: اگر فقط تخلفات نگه داشته
 * شوند، پروندهٔ رفتاری دانش‌آموز یک‌طرفه و برای خانواده گمراه‌کننده می‌شود.
 *
 * «غیبت» و «تأخیر» اینجا جدا از AttendanceStatus‌اند: غیبتِ حضور و غیابِ
 * کلاسی است با جریمهٔ مالی، ولی غیبتِ انضباطی یعنی تکرار و نشانهٔ مشکلِ
 * رفتاری — هر دو «absent» خوانده می‌شوند ولی معنایشان یکی نیست.
 */
enum DisciplineType: string
{
    case Warning = 'warning';   // تذکر
    case Praise = 'praise';     // تقدیر
    case Absence = 'absence';   // غیبت
    case Late = 'late';         // تأخیر

    public function label(): string
    {
        return match ($this) {
            self::Warning => 'تذکر',
            self::Praise => 'تقدیر',
            self::Absence => 'غیبت',
            self::Late => 'تأخیر',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Warning => 'badge-warning',
            self::Praise => 'badge-success',
            self::Absence => 'badge-error',
            self::Late => 'badge-info',
        };
    }

    /** گزینه‌های select با ساختاری که x-select می‌خواهد. */
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
}
