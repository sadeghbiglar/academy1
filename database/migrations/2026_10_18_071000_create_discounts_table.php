<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| تخفیف‌های تعریف‌شده.
|
| این جدول «تخفیفِ قابل استفاده» است: یک قاعده که پذیرش هنگام صدور فاکتور
| انتخاب می‌کند. تخفیفِ اعمال‌شده خودش روی فاکتور (discount_type/value/amount)
| ذخیره می‌شود، چون مبلغی که روی فاکتور نشسته باید در تاریخ خودش ثابت
| بماند؛ اگر بعداً قاعده عوض شد، فاکتور قدیمی نباید تغییر کند.
|
| دامنه: همه، یک دوره، یک دانش‌آموز. هر سه nullable‌اند و «همه» یعنی هر سه
| null است.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();

            $table->string('title');

            // percent یا fixed — همان DiscountType
            $table->string('type', 20);
            $table->unsignedBigInteger('value');

            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();

            // میلادی ذخیره، شمسی نمایش. null یعنی بدون انقضا.
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();

            // علت: «تخفیف خواهران»، «بورسیه»، «جبران کلاس از دست‌رفته»
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'course_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
