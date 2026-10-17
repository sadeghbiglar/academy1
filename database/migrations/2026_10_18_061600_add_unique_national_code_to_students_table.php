<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| کد ملی باید یکتا باشد.
|
| قبلاً فقط در UI بررسی می‌شد و هر جای دیگری که دانش‌آموز ساخته می‌شد
| (import، seed، اسکریپت) می‌توانست دو نفر را با یک کد ملی ثبت کند. یکتایی در
| پایگاه داده تنها جایی است که نمی‌شود دور زد، پس همان‌جا اعمال شد.
|
| پیش از افزودن ایندکس، ردیف‌های تکراری پاک می‌شوند تا migration شکست نخورد؛
| آخرین ردیف تکراری نگه داشته می‌شود.
*/
return new class extends Migration
{
    public function up(): void
    {
        // شناسه‌ها اول جدا می‌شوند، بعد حذف. MySQL اجازه نمی‌دهد از همان جدولی که
        // داری از آن DELETE می‌کنی SELECT داخل subquery بگیری (خطای 1093)، ولی
        // sqlite اجازه می‌دهد — پس کدی که فقط روی sqlite تست شده بود، روی دیتابیس
        // واقعی می‌افتاد.
        $duplicates = DB::table('students')
            ->select('national_code')
            ->whereNotNull('national_code')
            ->groupBy('national_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('national_code');

        foreach ($duplicates as $code) {
            $ids = DB::table('students')->where('national_code', $code)->pluck('id');

            // آخرین ردیف نگه داشته می‌شود
            DB::table('students')->whereIn('id', $ids->slice(1))->delete();
        }

        Schema::table('students', function (Blueprint $table) {
            $table->unique('national_code');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['national_code']);
        });
    }
};
