<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // نقش به‌صورت enum در سطح پایگاه داده، نه رشته آزاد: «admin» و
            // «Admin» دو مقدار متفاوت نباشند.
            $table->string('role')->default('reception')->after('email');

            $table->string('first_name')->nullable()->after('role');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('mobile')->nullable()->after('last_name');

            // حساب غیرفعال به‌جای حذف: سوابق مالی و آموزشی به کاربر گره خورده‌اند
            $table->boolean('is_active')->default(true)->after('mobile');

            $table->index(['role', 'is_active']);
        });

        // دانش‌آموز/استاد اختیاری به یک حساب وصل می‌شود تا «دبیر» بداند کلاس‌های
        // خودش کدام‌اند بدون اینکه لازم باشد یک پروفایل موازی نگه داریم.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('is_active')
                ->constrained('teachers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn(['role', 'first_name', 'last_name', 'mobile', 'is_active', 'teacher_id']);
        });
    }
};
