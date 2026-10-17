<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| هزینه‌ها.
|
| تومان و صحیح، مثل فاکتور. مبلغ هزینهٔ ناقص در گزارش سود یعنی سودِ
| ساختگی، و هزینهٔ نامشخص (دستهٔ null) در هیچ دسته‌ای جمع نمی‌شود، پس دسته
| اجباری است.
|
| «تاریخ» یعنی تاریخ وقوع هزینه، نه تاریخ ثبت: قبض برق را ممکن است ده روز
| بعد وارد کنند و باید در ماهی بنشیند که واقعاً در آن مصرف شده.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // دسته enum است، نه رشتهٔ آزاد
            $table->string('category', 30);

            $table->string('title');
            $table->unsignedBigInteger('amount');

            $table->text('notes')->nullable();

            // میلادی ذخیره، شمسی نمایش و فیلتر
            $table->date('spent_at');

            $table->timestamps();

            $table->index('spent_at');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
