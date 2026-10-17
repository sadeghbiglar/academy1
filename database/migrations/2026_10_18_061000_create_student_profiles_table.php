<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();

            // کد داخلی آموزشگاه. یکتا ولی nullable: برای ثبت سریع پذیرش لازم نیست،
            // و برای دانش‌آموزان قدیمی که قبلاً کد نداشتند اجباری هم نیست
            $table->string('student_code', 30)->nullable()->unique();

            $table->string('status', 20)->default('enrolling');

            // تاریخ ثبت‌نام به میلادی ذخیره می‌شود تا ترتیب و بازه‌ی پرس‌وجو
            // درست بماند؛ نمایش شمسی فقط در لایهٔ نمایش است
            $table->date('enrolled_at')->nullable();

            $table->string('photo_path')->nullable();

            // شماره شبا: IR + 24 رقم
            $table->string('sheba', 26)->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
