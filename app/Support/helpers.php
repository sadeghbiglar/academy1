<?php

use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

/*
|--------------------------------------------------------------------------
| نمایش تاریخ شمسی
|--------------------------------------------------------------------------
| ذخیره‌سازی همیشه میلادی است (استاندارد Laravel و قابل مرتب‌سازی/فیلتر در
| پایگاه داده). تبدیل به شمسی فقط در لایه نمایش انجام می‌شود تا هیچ‌جا تاریخ
| به رشته شمسی ذخیره نشود و بازه‌های تاریخی (whereBetween) درست کار کند.
*/

if (! function_exists('jalali_date')) {
    /**
     * نمایش شمسی هر مقدار تاریخ‌پذیر.
     *
     * @param  string|int|null  $date  رشته میلادی، timestamp یا شیء تاریخ
     */
    function jalali_date(string|int|DateTimeInterface|null $date, string $format = 'Y/m/d'): ?string
    {
        if (blank($date)) {
            return null;
        }

        if ($date instanceof DateTimeInterface) {
            return Jalalian::fromDateTime($date)->format($format);
        }

        if (is_int($date)) {
            // createFromTimestamp, not '@'.$date: the '@' form is parsed as UTC and
            // shifts the shamsi day by one depending on the server timezone.
            return Jalalian::fromCarbon(Carbon::createFromTimestamp($date, date_default_timezone_get()))->format($format);
        }

        return Jalalian::fromDateTime($date)->format($format);
    }
}

if (! function_exists('jalali_date_time')) {
    /**
     * نمایش شمسی تاریخ و ساعت — برای لاگ‌ها و زمان ثبت رخداد.
     */
    function jalali_date_time(string|int|DateTimeInterface|null $date, string $format = 'Y/m/d H:i'): ?string
    {
        return jalali_date($date, $format);
    }
}

if (! function_exists('miladi_date')) {
    /**
     * تبدیل تاریخ شمسی ورودی کاربر به میلادی قابل ذخیره.
     * ورودی کاربر هم ارقام فارسی و هم ارقام لاتین و هم جداکننده‌های متنوع را می‌پذیرد.
     *
     * @throws InvalidArgumentException اگر تاریخ شمسی معتبر نباشد
     */
    function miladi_date(?string $date, string $format = 'Y-m-d'): ?string
    {
        if (blank($date)) {
            return null;
        }

        $normalized = normalize_jalali_input($date);

        if (! preg_match('#^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$#', $normalized, $m)) {
            throw new InvalidArgumentException("قالب تاریخ شمسی نامعتبر است: {$date} (باید به شکل ۱۴۰۳/۰۵/۱۲ باشد)");
        }

        [, $year, $month, $day] = $m;

        $year = (int) $year;
        $month = (int) $month;
        $day = (int) $day;

        $maxDay = jalali_month_days($year, $month);

        if ($day > $maxDay) {
            throw new InvalidArgumentException(
                "روز {$day} در ماه {$month} سال {$year} وجود ندارد (بیشترین روز: {$maxDay})"
            );
        }

        return (new Jalalian($year, $month, $day))->toCarbon()->format($format);
    }
}

/*
|--------------------------------------------------------------------------
| ارقام فارسی
|--------------------------------------------------------------------------
| کاربر ایرانی «۱۲۳۴» یا «۱۲٬۵۰۰» تایپ می‌کند. ذخیره باید همیشه لاتین باشد،
| نمایش همیشه فارسی. تبدیل باید پیش از اعتبارسنجی انجام شود تا قاعده روی
| مقدار درست اجرا شود نه روی ورودی خام.
*/

if (! function_exists('to_english_digits')) {
    /**
     * اربانی/فارسی/عربی → لاتین. جداکننده‌های هزارگان و ممیز حفظ می‌شوند.
     */
    function to_english_digits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}

if (! function_exists('to_persian_digits')) {
    /**
     * لاتین → فارسی برای نمایش. ارقام نمایش‌داده‌شده هیچ‌وقت در دیتابیس یا
     * محاسبات شرکت نمی‌کنند؛ فقط در لایه view صدا زده می‌شود.
     */
    function to_persian_digits(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return strtr((string) $value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',

            // جداکننده‌ها هم فارسی می‌شوند. «۲,۰۰۰,۰۰۰» با کامای لاتین وسطش
            // نیمه‌فارسی به نظر می‌رسد؛ و در RTL جای ممیز هم غلط دیده می‌شود
            ',' => '٬',
            '.' => '٫',
        ]);
    }
}

if (! function_exists('normalize_jalali_input')) {
    /**
     * پاکسازی ورودی تاریخ شمسی: ارقام فارسی → لاتین، حذف فاصله و نیم‌فاصله،
     * تبدیل خط تیره/نقطه به اسلش. قالب‌های رایج کاربر را یکسان می‌کند.
     */
    function normalize_jalali_input(string $value): string
    {
        $value = to_english_digits($value);

        // نیم‌فاصله و بی‌عرضه حذف می‌شوند (تایپ با کیبورد فارسی)، اما جداکننده‌های
        // تاریخ به اسلش تبدیل می‌شوند — حذفشان «۱۴۰۳ ۰۵ ۱۲» را به «۱۴۰۳۰۵۱۲»
        // تبدیل می‌کند که دیگر قابل تفسیر نیست.
        $value = strtr($value, [
            "\u{200C}" => '', "\u{200F}" => '', "\u{00A0}" => '',
            '-' => '/', '.' => '/', '\\' => '/', ' ' => '/',
        ]);

        return trim(preg_replace('#/+#', '/', $value), '/');
    }
}

if (! function_exists('parse_number')) {
    /**
     * تبدیل ورودی عددی کاربر به عدد.
     * «۱۲٬۵۰۰»، «12,500»، «۱۲۵۰۰» همه به 12500 تبدیل می‌شوند.
     * رشته خالی یا فقط نشانه‌های نامعتبر → null (نه صفر؛ صفر یعنی مقدار واقعی صفر).
     */
    function parse_number(mixed $value): ?float
    {
        if ($value === null || is_bool($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = to_english_digits((string) $value);
        // حذف جداکننده هزارگان فارسی/عربی، کاما و فاصله؛ ممیز نقطه و اسلش حفظ می‌شود
        $normalized = strtr($normalized, ['٬' => '', '،' => '', ',' => '', ' ' => '']);
        $normalized = rtrim($normalized, '-');

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}

if (! function_exists('parse_integer')) {
    /**
     * مشابه parse_number ولی خروجی صحیح — برای کد ملی، کد پستی، شماره موبایل.
     */
    function parse_integer(mixed $value): ?int
    {
        $number = parse_number($value);

        return $number === null ? null : (int) round($number);
    }
}

if (! function_exists('jalali_month_name')) {
    /**
     * نام فارسی ماه شمسی — برای عنوان تقویم و فیلترهای گزارش.
     */
    function jalali_month_name(int $month): string
    {
        return [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ][$month] ?? '';
    }
}

if (! function_exists('jalali_month_days')) {
    /**
     * تعداد روزهای یک ماه شمسی — برای ساخت تقویم انتخابگر تاریخ.
     *
     * شش ماه اول ۳۱ روز، پنج ماه بعد (۷ تا ۱۱) ۳۰ روز، و اسفند ۲۹ روز —
     * ۳۰ روز فقط در سال کبیسه.
     */
    function jalali_month_days(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("ماه {$month} معتبر نیست (باید بین ۱ تا ۱۲ باشد)");
        }

        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        return jalali_is_leap($year) ? 30 : 29;
    }
}

if (! function_exists('jalali_is_leap')) {
    /**
     * آیا سال شمسی کبیسه است؟ (چرخه ۳۳ ساله)
     */
    function jalali_is_leap(int $year): bool
    {
        return (new Jalalian($year, 1, 1))->isLeapYear();
    }
}

if (! function_exists('is_valid_jalali_date')) {
    /**
     * اعتبارسنجی تاریخ شمسی بدون پرتاب exception.
     */
    function is_valid_jalali_date(?string $date): bool
    {
        try {
            miladi_date($date);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
