<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| تنظیمات آموزشگاه.
|
| کلید/مقدار، نه ستون‌های متعدد: هر تنظیمی که بعداً لازم شد نیازی به مهاجرت
| ندارد و نام و کد پستی و آستانهٔ حضور همه در یک جا می‌نشینند.
|
| کلید یکتاست؛ بدون آن دو سطر با کلید یکسان، «مقدارِ درست» مبهم می‌شود.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();

            // گروه‌بندی برای صفحهٔ تنظیمات: عمومی، مالی، آستانه‌ها
            $table->string('group', 30)->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
