<?php

namespace App\Enums;

/**
 * وضعیت حضور و غیاب.
 *
 * «غیبت» و «موجه» دو چیز متفاوت‌اند: اولی جریمهٔ مالی دارد، دومی ندارد. اگر
 * این دو یکی شوند، خانواده‌ای که بیماری داشته جریمه می‌شود و کسی که نیامده
 * معاف — یعنی سامانه دقیقاً برعکسِ واقعیت عمل می‌کند.
 *
 * «تأخیر» هم جدا است چون در بیشتر آموزشگاه‌ها حضورِ با تأخیر یعنی حاضر ولی
 * جریمهٔ جزئی.
 */
enum AttendanceStatus: string
{
    case Present = 'present';   // حاضر
    case Absent = 'absent';     // غایب
    case Late = 'late';         // تأخیر
    case Excused = 'excused';   // غیبت موجه

    public function label(): string
    {
        return match ($this) {
            self::Present => 'حاضر',
            self::Absent => 'غایب',
            self::Late => 'تأخیر',
            self::Excused => 'غیبت موجه',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'badge-success',
            self::Late => 'badge-warning',
            self::Excused => 'badge-info',
            self::Absent => 'badge-error',
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

    /** آیا این دانش‌آموز سر کلاس بوده است؟ تأخیر هم «بله» است. */
    public function attended(): bool
    {
        return $this === self::Present || $this === self::Late;
    }

    /** آیا این وضعیت روی صورت‌سود مالی اثر دارد؟ */
    public function affectsFee(): bool
    {
        return $this === self::Absent;
    }

    /** جلسه‌های موجه در محاسبهٔ درصد حضور به حساب می‌آیند. */
    public function countsTowardRate(): bool
    {
        return $this !== self::Excused;
    }
}
