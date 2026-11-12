<?php

namespace App\Enums;

/**
 * نوع تعارض زمان‌بندی (شماره 47).
 *
 * سه قاعدهٔ R6 در یک جا نام‌گذاری می‌شوند تا پیام، رنگ تقویم و تست همگی
 * از یک منبع بخوانند؛ رشتهٔ آزاد «teacher» در یک فایل و «استاد» در فایل دیگر
 * به‌مرور از هم فاصله می‌گیرند.
 */
enum ConflictType: string
{
    /** یک استاد در دو کلاس همزمان */
    case Teacher = 'teacher';

    /** یک اتاق میزبان دو کلاس همزمان */
    case Room = 'room';

    /** یک دانش‌آموز در دو دوره همزمان (بر پایهٔ ثبت‌نام قطعی) */
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'تداخل استاد',
            self::Room => 'اتاق تکراری',
            self::Student => 'تداخل دانش‌آموز',
        };
    }
}
