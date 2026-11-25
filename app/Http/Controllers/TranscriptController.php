<?php

namespace App\Http\Controllers;

use App\Models\Student;
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
}
