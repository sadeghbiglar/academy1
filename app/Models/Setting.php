<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تنظیمات آموزشگاه، کلید/مقدار.
 *
 * یک مدل ساده که در صفحهٔ تنظیمات هم خوانده می‌شود هم در رسید چاپی. اولی که
 * صفحهٔ تنظیمات ساخته شد، خواندنش از همین‌جا می‌آید.
 */
class Setting extends Model
{
    public const ACADEMY_NAME = 'academy.name';

    public const ACADEMY_PHONE = 'academy.phone';

    public const ACADEMY_ADDRESS = 'academy.address';

    public const ATTENDANCE_WARNING = 'attendance.warning_below';

    protected $fillable = ['key', 'value', 'group'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value, string $group = 'general'): self
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    /** نام آموزشگاه برای سربرگ رسید. نبودنش نباید صفحه را خراب کند. */
    public static function academyName(): string
    {
        return static::get(self::ACADEMY_NAME, 'آموزشگاه') ?? 'آموزشگاه';
    }

    public static function academyPhone(): ?string
    {
        return static::get(self::ACADEMY_PHONE);
    }

    public static function academyAddress(): ?string
    {
        return static::get(self::ACADEMY_ADDRESS);
    }

    /**
     * آستانهٔ هشدار حضور.
     *
     * مقدار خراب در تنظیمات نباید کل گزارش حضور را از کار بیندازد؛ مقدار
     * پیش‌فرض برمی‌گردد.
     */
    public static function attendanceWarning(): float
    {
        $value = static::get(self::ATTENDANCE_WARNING);

        return is_numeric($value) ? (float) $value : 80.0;
    }
}
