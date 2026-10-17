<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');

            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();

            // ظرفیت مثبت و شهریه نامنفی با قید پایگاه داده enforce می‌شوند؛
            // اعتبارسنجی در فرم کافی نیست چون هر مسیر دیگری دور می‌زندش
            $table->unsignedSmallInteger('capacity')->default(0);

            $table->unsignedSmallInteger('session_count')->default(0);
            $table->unsignedSmallInteger('session_duration')->default(90);

            // شهریه به ریال؛ صفر یعنی رایگان، پس منفی معنا ندارد
            $table->unsignedBigInteger('base_fee')->default(0);

            $table->string('status', 20)->default('open');

            // میلادی ذخیره می‌شود، شمسی فقط نمایش
            $table->date('starts_at')->nullable();

            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['subject_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
