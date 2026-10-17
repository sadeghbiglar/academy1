<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| اقساط شهریه.
|
| هر قسط یک تاریخ سررسید و یک مبلغ دارد و به فاکتور وصل است. مبلغ قسط در
| همین جدول ذخیره می‌شود و هر بار از فاکتور حساب نمی‌شود: اگر بعداً تخفیف فاکتور
| عوض شود، برنامهٔ اقساطی که با خانواده توافق شده نباید زیر پایشان خالی شود.
|
| دو حالت برای فاکتور بی‌قسط لازم است، پس nullable است و منطق در مدل.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // شمارهٔ ترتیبی قسط در همان فاکتور
            $table->unsignedSmallInteger('sequence');

            $table->unsignedBigInteger('amount');

            // میلادی ذخیره، شمسی نمایش
            $table->date('due_at');

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->unique(['invoice_id', 'sequence'], 'installments_unique_per_invoice');
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
