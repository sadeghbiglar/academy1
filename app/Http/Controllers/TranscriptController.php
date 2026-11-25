<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Student;
use App\Services\CourseTranscript;
use App\Services\Transcript;
use Illuminate\Contracts\View\View;

/**
 * کارنامهٔ رسمی چاپی.
 *
 * جدا از پنل است، مثل چاپ فاکتور و فیش حقوقی: منو، سایه و دکمه‌ها روی کاغذ
 * نمی‌آیند. کارنامه‌ای که نیمه‌اش منوی پنل باشد، سند رسمی نیست.
 */
class TranscriptController extends Controller
{
    public function print(Student $student, Transcript $transcript): View
    {
        // کد دانش‌آموزی روی پروندهٔ آموزشی است؛ بدون این بارگذاری، view برای
        // هر دانش‌آموزِ بدون پرونده یک کوئری اضافه می‌زند
        $student->load('profile');

        return view('students.print.transcript', [
            'doc' => $transcript->build($student),
        ]);
    }

    /**
     * کارنامهٔ نهایی یک دوره: سطرِ هر ثبت‌نامِ قطعی با ریزنمرات، میانگین،
     * ساعت حضور و وضعیت قبولی.
     */
    public function printCourse(Course $course, CourseTranscript $transcript): View
    {
        // درس و استاد روی سند می‌روند؛ بدون بارگذاری، هر سطر یک کوئری اضافه می‌زند
        $course->load('subject', 'teacher');

        return view('courses.print.transcript', [
            'doc' => $transcript->build($course),
        ]);
    }
}
