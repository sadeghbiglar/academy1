<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| ریزنمرات.
|
| یک سطر به ازای هر نمرهٔ خام: دانش‌آموز، دوره، نوع، عنوان. «بیست نمرهٔ تکلیف»
| بیست سطر است، نه یک ستون.
|
| یکتایی (دانش‌آموز، دوره، نوع، عنوان) عمدی است: بدون آن، دکمهٔ «ذخیره» دوباره
| زده می‌شود و همان نمره دوبار می‌نشیند، و میانگین دو برابر می‌شود. عنوان در
| کلید هست چون یک دانش‌آموز ممکن است دو آزمون کوتاه در یک ترم داشته باشد و
| لزوماً تکراری نباشند.
|
| نمره و حداکثر عمدی DECIMAL‌اند نه FLOAT: محاسبهٔ میانگین با اعشار ممی‌تواند به
| خطای گرد کردن بخورد و نمره‌ای که ۱۹٫۵ است ۱۹٫۴۹ شود.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // چه کسی نمره را ثبت کرده؛ nullOnDelete چون نمره سند آموزشگاه است
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 20);
            $table->string('title');

            $table->decimal('score', 6, 2);
            $table->decimal('max_score', 6, 2)->default(20);

            // میلادی ذخیره می‌شود، نمایش شمسی
            $table->date('graded_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['student_id', 'course_id', 'type', 'title'], 'grades_unique_per_assignment');

            $table->index(['course_id', 'type']);
            $table->index(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};