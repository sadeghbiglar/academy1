<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();

            // پایهٔ تحصیلی: ۷ تا ۱۲. رشتهٔ آموزشگاه به پایه گره خورده چون برنامه و
            // ظرفیت سالانه بر پایهٔ تحصیلی حساب می‌شود
            $table->unsignedTinyInteger('grade_level')->nullable();

            // ظرفیت ساعت سالانه، نه تعداد دانش‌آموز. ظرفیت دوره‌ها را محدود
            // می‌کند ولی باید در سطح درس تعریف شود، نه در هر دوره جدا
            $table->unsignedSmallInteger('annual_hours')->nullable();

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['grade_level', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
