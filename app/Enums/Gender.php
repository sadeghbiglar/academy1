<?php

namespace App\Enums;

/**
 * جنسیت.
 *
 * قبلاً در bladeها و factoryها رشتهٔ آزاد 'male'/'female' نوشته می‌شد؛ یک غلط
 * املایی بی‌سروصدا یک ردیف بی‌جنسیت می‌ساخت که هیچ‌جا نمایش داده نمی‌شد.
 *
 * یک نکته که تست‌ها نشان دادند و برخلاف تصور اولیه بود: cast به enum در این
 * نسخهٔ لاراول هم روی نوشتن و هم روی خواندن، مقدار ناشناخته را با ValueError
 * رد می‌کند. این عمداً خوب است — دادهٔ پرت باید بلند فریاد بزند، نه اینکه
 * بی‌سروصدا به null تبدیل و از چشم کاربر پنهان شود. یعنی هر ورودی از هر راهی
 * باید پیش از رسیدن به این ستون از Gender::rule() رد شود.
 *
 * به همین دلیل labelFor() ورودی غیرشاخه‌دار را تحمل می‌کند: null و آرایه در
 * query یا view کاملاً طبیعی‌اند و نباید صفحه را بترکانند.
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'پسر',
            self::Female => 'دختر',
        };
    }

    /** برای کامپوننت x-select که آرایه id/name می‌خواهد. */
    public static function options(): array
    {
        return array_map(
            fn (self $gender) => ['id' => $gender->value, 'name' => $gender->label()],
            self::cases(),
        );
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** برای قاعدهٔ اعتبارسنجی: Rule::enum موجود است ولی روی نسخهٔ ما نیست. */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** برچسب فارسی، یا خط تیره وقتی مقدار نامعتبر/خالی است. */
    public static function labelFor(mixed $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        // آرایه و null نباید به «Array to string conversion» برسند؛ اگر ورودی
        // واقعاً یک enum از این نوع است، همان برچسب است
        return is_scalar($value)
            ? (self::tryFrom((string) $value)?->label() ?? '—')
            : '—';
    }
}
