<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| سوابق تحصیلی قبلی (رونویس) — issue 38.
|
| دانش‌آموزی که از مدرسه‌ای دیگر می‌آید، کارنامهٔ قبلی‌اش باید به پرونده بچسبد
| وگرنه «پایهٔ قبلی» و «نمرات» فقط در کاغذ خانواده می‌ماند و ارزیابیِ سطحِ
| پایهٔ فعلی حدس می‌شود.
|
| نکته‌ها:
|   - academic_year سالِ تحصیلی شمسی است (۱۴۰۳)، نه تاریخ. برچسب سال است، نه
|     روزی از تقویم؛ مقایسهٔ روزبه‌روزی رویش معنا ندارد و قاعدهٔ «ذخیرهٔ
|     میلادی» برایش صدق نمی‌کند.
|   - previous_grades nullable است: کارنامهٔ انتقالی بعضی مدارس همراه نیست و
|     نباید نبودنش جلوی ثبتِ خودِ رونویس را بگیرد.
|   - نام مدرسه آزاد است (متن) چون فهرستِ مدارسِ کشور در سامانه نیست و
|     ساختنِ آن کار این issue نیست؛ پایه اما select است چون همه ۱۲ پایه را
|     نظام آموزشی تعریف کرده و متنِ آزاد «پایه» را گزارش‌پذیر نمی‌کند.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments_previous', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->string('school', 150);
            $table->unsignedSmallInteger('academic_year');
            $table->string('grade', 20);
            $table->text('previous_grades')->nullable();
            $table->string('status', 20);

            $table->timestamps();

            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments_previous');
    }
};
