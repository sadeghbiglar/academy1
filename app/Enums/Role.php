<?php

namespace App\Enums;

/**
 * نقش‌های کاربری آموزشگاه.
 *
 * دسترسی بر پایه نقش است (R7): دبیر نباید لیست حقوق را ببیند و امور مالی
 * نباید نمره را ویرایش کند. نقش‌ها enum هستند نه رشته آزاد تا «ادمین» و
 * «admin» دو رکورد متفاوت نشوند.
 */
enum Role: string
{
    case Admin = 'admin';          // مدیر: دسترسی کامل
    case Deputy = 'deputy';        // معاون آموزشی: دانش‌آموز، استاد، دوره، نمره
    case Finance = 'finance';      // امور مالی: فاکتور، پرداخت، گزارش مالی
    case Teacher = 'teacher';      // دبیر: کلاس‌های خودش، حضور، نمره
    case Reception = 'reception';  // پذیرش: ثبت‌نام، تماس، فاکتور اولیه

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدیر',
            self::Deputy => 'معاون آموزشی',
            self::Finance => 'امور مالی',
            self::Teacher => 'دبیر',
            self::Reception => 'پذیرش',
        };
    }

    /**
     * بخش‌هایی که این نقش در منو می‌بیند.
     * یک منبع واحد برای منو و برای تست — تا UI و مجوز با هم اختلاف نداشته باشند.
     */
    public function sections(): array
    {
        return match ($this) {
            self::Admin => ['dashboard', 'students', 'teachers', 'subjects', 'courses',
                'enrollments', 'schedule', 'attendance', 'grades', 'finance',
                'reports', 'settings', 'users'],
            self::Deputy => ['dashboard', 'students', 'teachers', 'subjects', 'courses',
                'enrollments', 'schedule', 'attendance', 'grades', 'reports'],
            self::Finance => ['dashboard', 'students', 'finance', 'reports', 'reports_finance'],
            self::Teacher => ['dashboard', 'my_courses', 'attendance', 'grades', 'profile'],
            self::Reception => ['dashboard', 'students', 'enrollments', 'finance', 'profile'],
        };
    }

    public function canSee(string $section): bool
    {
        return in_array($section, $this->sections(), true);
    }
}
