<?php

namespace App\Enums;

/**
 * نقش‌های کاربری آموزشگاه.
 *
 * دسترسی بر پایه نقش است (R7): دبیر نباید لیست حقوق را ببیند و امور مالی
 * نباید نمره را ویرایش کند. نقش‌ها enum هستند نه رشته آزاد تا «ادمین» و
 * «admin» دو رکورد متفاند نشوند.
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
     * بخش‌هایی که این نقش می‌بیند.
     *
     * منو و تست هر دو از همین‌جا می‌خوانند، پس نمی‌توانند با هم اختلاف پیدا
     * کنند. هر چیزی که اینجا نباشد، حتی با URL مستقیم هم باز نمی‌شود —
     * مجوز در middleware مسیر اعمال می‌شود، نه با پنهان کردن لینک.
     */
    public function sections(): array
    {
        return match ($this) {
            self::Admin => Section::cases(),
            // معاون آموزشی به پول دسترسی ندارد و کاربران را هم نمی‌سازد
            self::Deputy => [
                Section::Dashboard, Section::Students, Section::Teachers, Section::Subjects,
                Section::Courses, Section::Enrollments, Section::Schedule, Section::Attendance,
                Section::Grades, Section::Reports, Section::Profile,
            ],
            // امور مالی وضعیت دانش‌آموز را می‌بیند (بدهی)، ولی نمره و برنامه درسش کار او نیست
            self::Finance => [
                Section::Dashboard, Section::Students, Section::Finance, Section::Reports, Section::Profile,
            ],
            // دبیر: کلاس‌های خودش و نمره همان‌ها. نه لیست اساتید، نه حقوق، نه ثبت‌نام
            self::Teacher => [
                Section::Dashboard, Section::MyCourses, Section::Attendance, Section::Grades, Section::Profile,
            ],
            // پذیرش: ثبت‌نام و فاکتور اولیه
            self::Reception => [
                Section::Dashboard, Section::Students, Section::Enrollments, Section::Finance, Section::Profile,
            ],
        };
    }

    public function canSee(string|Section $section): bool
    {
        $key = $section instanceof Section ? $section : Section::tryFrom($section);

        return $key !== null && in_array($key, $this->sections(), true);
    }
}
