<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| پرداخت‌های شهریه.
|
| مبلغ تومان و صحیح. هر پرداخت یک سطر جدا و غیرقابل‌ویرایش است: ویرایش یک
| پرداخت یعنی از بین رفتن سابقهٔ اینکه چه مبلغی کِی و چطور پرداخت شد. اشتباه
| در پرداخت با یک سطر «اصلاحی» و مبلغ منفی جبران می‌شود، نه با ویرایش.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // عمداً signed: سطر برگشت مبلغ منفی دارد. یک سطر «برگشت» با
            // مبلغ مثبت جمع کل پرداخت‌ها را زیاد می‌کرد و یعنی برگشتی که
            // پول بیشتری می‌آورد.
            $table->bigInteger('amount');

            $table->string('method', 20)->default('cash');
            $table->string('reference')->nullable();

            $table->date('paid_at');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
