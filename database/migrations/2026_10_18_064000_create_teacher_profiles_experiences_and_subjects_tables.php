<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();

            // یک نیم‌رخ برای هر استاد: دو رکورد استخدامی برای یک نفر یعنی حقوق
            // و بیمه دو بار حساب می‌شود
            $table->foreignId('teacher_id')->unique()->constrained()->cascadeOnDelete();

            // کد پرسنلی، nullable تا ثبت سریع مانع کار نباشد ولی یکتا بماند تا
            // فیش حقوقی و لیست بیمه به یک نفر برسند نه به دو
            $table->string('employee_code', 30)->nullable()->unique();

            $table->string('position')->nullable();
            $table->string('status', 20)->default('active');

            // شروع و پایان همکاری؛ میلادی ذخیره می‌شود تا بازه‌های گزارش حقوق
            // (سابقهٔ فعال در یک بازه) درست کار کند
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();

            $table->string('insurance_number', 30)->nullable();

            $table->timestamps();

            $table->index('status');
        });

        Schema::create('teacher_experiences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('organization')->nullable();

            $table->date('started_at');
            // null یعنی «هنوز تمام نشده» — نه ۰ و نه تاریخ امروز، چون هر دو بعداً
            // به یک تاریخ غلط در پرونده تبدیل می‌شوند
            $table->date('ended_at')->nullable();

            $table->timestamps();

            // «سابقهٔ فعلی» همیشه آخرین سابقهٔ شروع‌شده است
            $table->index(['teacher_id', 'started_at']);
        });

        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // تخصص تکراری بی‌معنی است و در جدول انتخاب استاد، ردیف دوم در همان
            // کمبو باکس نمایش داده می‌شود
            $table->unique(['teacher_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subjects');
        Schema::dropIfExists('teacher_experiences');
        Schema::dropIfExists('teacher_profiles');
    }
};
