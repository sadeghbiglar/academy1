<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| زمان‌بندی کلاس.
|
| یک دوره می‌تواند چند جلسه در هفته داشته باشد، پس زمان‌بندی یک‌به‌یک با دوره
| نیست. هر سطر یک «الگوی هفتگی» است: چه روزهایی، چه ساعتی، کجا.
|
| شمارهٔ هفته و تاریخ جلسه کنار هم هستند چون هر دو لازم‌اند: الگو برای
| تقویم می‌خواهد، و تاریخ دقیق برای ثبت حضور و غیاب. جلسهٔ جبرانی همان الگو
| است با تاریخی متفاوت.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // الگوی هفتگی: هفتهٔ ۱ تا ۵۳
            $table->unsignedTinyInteger('week_number')->default(1);

            // شمارهٔ ISO (شنبه = ۱ … جمعه = ۷)
            $table->unsignedTinyInteger('week_day');

            // دقیقه از نیمه‌شب. عدد است نه 'HH:MM' تا محاسبهٔ تداخل و مرتب‌سازی
            // درست باشد و '10:00' < '9:00' نشود
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');

            $table->string('room')->nullable();

            // میلادی ذخیره می‌شود؛ نمایش شمسی در لایهٔ نمایش
            $table->date('session_date')->nullable();

            $table->boolean('is_makeup')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['course_id', 'week_day']);
            $table->index(['room', 'week_day', 'start_minute']);
            $table->index('session_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
