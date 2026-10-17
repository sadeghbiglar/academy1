<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| فاکتور شهریه.
|
| مبلغ به تومان و integer است، نه float: ۰٫۱ + ۰٫۲ در پول یعنی خطای ریالی
| که در گرد کردن فاکتور خودش را نشان می‌دهد.
|
| شمارهٔ فاکتور یکتاست و در دیتابیس ساخته می‌شود، چون دو فاکتور با یک شماره
| یعنی «کدام فاکتور؟» موقع رسیدگی به شکایت خانواده.
|
| تخفیف فقط از «مبلغ دوره» خورده می‌شود، نه از کل سطر: تخفیف روی کارمزد و مالیات
| با تخفیف روی شهریه فرق دارد.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // شمارهٔ فاکتور یکتا، ترجیحاً از قبل مقدار می‌گیرد
            $table->string('number')->unique();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 20)->default('issued');

            // ریال؟ نه: تومان، عدد صحیح
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->unsignedBigInteger('discount_value')->nullable();

            // مبلغی که باید پرداخت شود = amount - discount_amount، و ذخیره
            // می‌شود تا تغییر بعدی تخفیف، بدهی‌ای را که قبلاً پرداخت شده عوض نکند
            $table->unsignedBigInteger('payable')->default(0);

            $table->text('notes')->nullable();

            // میلادی ذخیره، شمسی نمایش
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
