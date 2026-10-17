<?php

namespace App\Enums;

use Illuminate\Support\Facades\Route;

/**
 * بخش‌های سامانه.
 *
 * منبع واحدِ «چه چیزی وجود دارد»: برچسب فارسی، نام مسیر، و اینکه در کدام گروه
 * منو می‌نشیند. نقش‌ها به این enum ارجاع می‌دهند، پس افزودن یک بخش یعنی یک
 * جا در همین‌جا — نه یک رشته در چند فایل که با هم اختلاف می‌افتند.
 */
enum Section: string
{
    case Dashboard = 'dashboard';
    case Students = 'students';
    case Teachers = 'teachers';
    case Subjects = 'subjects';
    case Courses = 'courses';
    case MyCourses = 'my_courses';   // «کلاس‌های من» — ویوی دبیر، نه ویوی مدیر
    case Enrollments = 'enrollments';
    case Schedule = 'schedule';
    case Attendance = 'attendance';
    case Grades = 'grades';
    case Finance = 'finance';
    case Reports = 'reports';
    case Settings = 'settings';
    case Users = 'users';
    case Profile = 'profile';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'داشبورد',
            self::Students => 'دانش‌آموزان',
            self::Teachers => 'اساتید',
            self::Subjects => 'دروس',
            self::Courses => 'دوره‌ها',
            self::MyCourses => 'کلاس‌های من',
            self::Enrollments => 'ثبت‌نام',
            self::Schedule => 'زمان‌بندی',
            self::Attendance => 'حضور و غیاب',
            self::Grades => 'نمرات',
            self::Finance => 'مالی',
            self::Reports => 'گزارش‌ها',
            self::Settings => 'تنظیمات',
            self::Users => 'کاربران',
            self::Profile => 'پروفایل من',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Dashboard => '🏠',
            self::Students => '🎒',
            self::Teachers => '👨‍🏫',
            self::Subjects => '📚',
            self::Courses => '📖',
            self::MyCourses => '🗂️',
            self::Enrollments => '✍️',
            self::Schedule => '🗓️',
            self::Attendance => '✅',
            self::Grades => '📝',
            self::Finance => '💰',
            self::Reports => '📊',
            self::Settings => '⚙️',
            self::Users => '🔐',
            self::Profile => '👤',
        };
    }

    /** نام مسیر؛ null یعنی این بخش هنوز ساخته نشده و فعلاً فقط در نقشه راه است. */
    public function routeName(): ?string
    {
        return match ($this) {
            self::Dashboard => 'dashboard',
            self::Students => 'students.index',
            self::Finance => 'finance.index',
            self::Teachers => 'teachers.index',
            self::Users => 'users.index',
            self::Courses => 'courses.index',
            self::Profile => 'profile',
            default => null,
        };
    }

    public function url(): ?string
    {
        $name = $this->routeName();

        return $name && Route::has($name)
            ? route($name)
            : null;
    }

    /** ترتیب نمایش در منو — همان ترتیبی که آموزشگاه با آن کار می‌کند. */
    public static function menuOrder(): array
    {
        return [
            self::Dashboard,
            self::Students,
            self::Teachers,
            self::Subjects,
            self::Courses,
            self::MyCourses,
            self::Enrollments,
            self::Schedule,
            self::Attendance,
            self::Grades,
            self::Finance,
            self::Reports,
            self::Settings,
            self::Users,
            self::Profile,
        ];
    }
}
