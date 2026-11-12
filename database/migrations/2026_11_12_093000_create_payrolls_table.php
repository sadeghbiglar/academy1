<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| حقوق و دستمزد اساتید (issue 40).
|
| تصمیم‌ها:
|
| ۱. دوره به‌جای تاریخ، «سال + ماه شمسی» است (period_year/period_month).
|    دوره یک ماهِ تقویمی است نه یک روز، و چیزی که باید مرتب و گروه‌بندی شود
|    «آبان ۱۴۰۴» است. ذخیرهٔ روزِ میلادیِ اولِ ماه، این نام را دوباره از
|    تبدیل درمی‌آورد و قاعدهٔ «تاریخ در DB میلادی» که برای روزهاست این‌جا
|    معنایی ندارد — اینجا هیچ روزِ واحدی ذخیره نمی‌شود.
|
| ۲. خالص (net) هرگز ورودی کاربر نیست: در هوکِ saving از ناخالص − بیمه −
|    مالیات ساخته می‌شود. اگر کاربر می‌توانست خالص را خودش بنویسد، فیش
|    می‌توانست با ستون‌هایش نخوانَد و گزارش ماهانه دروغ بگوید.
|
| ۳. یکتایی (استاد، سال، ماه) عمدی است: ثبتِ دوبارهٔ همان ماه، فیشِ دوم
|    می‌سازد و حقوق دو بار پرداخت می‌شود. مثل نمره، جلوی تکرار باید ساختار
|    گرفته شود نه دقتِ کاربر.
|
| ۴. استاد nullable و nullOnDelete: فیشِ پرداخت‌شده سند مالی است و حذفِ
|    پروفایلِ استاد نباید سابقهٔ پرداخت را با خود ببرد.
|
| ۵. نرخِ روزانه/ساعتی در جدول نیست چون در REQUIREMENTS هم نیست و ساختنش
|    حدس زدن دربارهٔ قرارداد کار معلمان است. اضافه‌کاری به‌صورت ساعت ثبت و
|    روی فیش نشان داده می‌شود؛ مبلغش را خودِ آموزشگاه در «ناخالص» می‌آورد.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();

            // دوره: ماهِ شمسی، نه تاریخ میلادی — «آبان ۱۴۰۴»
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            $table->unsignedTinyInteger('days_worked');
            $table->decimal('overtime_hours', 5, 1)->default(0);

            // تومان
            $table->unsignedInteger('insurance')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('gross');

            // محاسبه‌شده؛ ایندکس دارد چون گزارشِ پرداختیِ ماه روی همین جمع می‌شود
            $table->unsignedInteger('net')->index();

            $table->string('status', 20)->default('pending');
            // میلادی ذخیره می‌شود، نمایش شمسی
            $table->date('paid_at')->nullable();

            $table->timestamps();

            // یک فیش برای هر استاد در هر ماه
            $table->unique(['teacher_id', 'period_year', 'period_month'], 'payrolls_unique_per_month');
            $table->index(['period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
