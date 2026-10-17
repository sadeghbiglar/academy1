<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // میلادی ذخیره می‌شود، شمسی فقط نمایش
            $table->date('enrolled_at');

            $table->string('status', 20)->default('pending');

            // شهریهٔ نهایی پس از اعمال تخفیف. null یعنی «هنوز تعیین نشده»، نه صفر
            $table->unsignedBigInteger('final_fee')->nullable();
            $table->unsignedBigInteger('discount')->default(0);

            $table->text('notes')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // یک ثبت‌نام فعال برای هر دانش‌آموز در هر دوره. چون «لغو شده» با
            // «حذف شده» فرق دارد و در سابقه می‌ماند، یکتایی کامل روی
            // (student, course) غلط است و ثبت‌نام دوباره را غیرممکن می‌کند
            $table->index(['course_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};
