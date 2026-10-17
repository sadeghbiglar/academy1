<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| حضور و غیاب.
|
| یک سطر به ازای هر دانش‌آموز در هر جلسهٔ کلاس. جلسه نه «روز» بلکه
| ClassSession است تا ساعت ورود و ترم درست به همان جلسه بچسبد و کلاس جبرانی
| از کلاس عادی جدا بماند.
|
| یکتایی روی (session, student) در خود پایگاه داده است، نه در فرم: فرم گروهی
| «همه حاضر» روی ۲۰ نفر اجرا می‌شود و دکمهٔ دوباره‌زدن آن نباید ۲۰ رکورد
| تکراری بسازد.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->string('status', 20)->default('present');

            // دقیقه از نیمه‌شب، مثل زمان‌بندی. ساعت ورود برای «با تأخیر ۱۵ دقیقه»
            // لازم است و یک تاریخ جدا برایش معنا ندارد
            $table->unsignedSmallInteger('arrival_minute')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['class_session_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
