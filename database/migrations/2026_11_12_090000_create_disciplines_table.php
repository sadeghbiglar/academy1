<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| رخدادهای انضباطی دانش‌آموزان.
|
| یک سطر برای هر رخداد: نوع (تذکر/تقدیر/غیبت/تأخیر)، شرح، تاریخ، ثبت‌کننده.
| دو تصمیم این‌جا مهم‌اند:
|
| ۱. تاریخ میلادی در DB و شمسی در نمایش — قاعدهٔ کل سامانه (R1). اگر شمسی
|    ذخیره می‌شد، مرتب‌سازی الفبایی می‌شد و «گزارش بازه‌ای» با مقایسهٔ رشته
|    تا روز آخر هر ماه کار نمی‌کرد.
|
| ۲. ثبت‌کننده nullOnDelete است: پاک شدن حساب کاربری نباید رخدادِ ثبت‌شده را
|    با خود ببرد — «چه کسی ثبت کرده» بخشی از سوابق است، و حذفِ معلّمِ جداشده
|    نباید سابقهٔ دانش‌آموز را ناقص کند.
|
| ثبت‌کننده و دانش‌آموز جدا از هم‌اند: یک رخداد را ممکن است معاون ثبت کرده
| باشد در حالی که مربوط به کلاس دبیر دیگری است.
|
| برای دانش‌آموز هیچ یکتایی گذاشته نشده: دو تذکر در یک روز معتبر است و
| جلوی آن را باید اپلیکیشن بگیرد، نه ساختار جدول.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 20);
            $table->text('description');

            // میلادی ذخیره می‌شود، نمایش شمسی
            $table->date('occurred_on');

            $table->timestamps();

            // گزارش بازه‌ای روی تاریخ فیلتر می‌کند، پس ایندکس لازم است
            $table->index('occurred_on');
            $table->index(['student_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplines');
    }
};
