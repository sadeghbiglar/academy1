<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| جلوگیری از ثبت‌نام تکراری در یک دوره.
|
| یکتایی سادهٔ (student_id, course_id) درست نیست: ثبت‌نام لغو شده باید در
| سابقه بماند ولی فرد بتواند دوباره ثبت‌نام کند. یک ایندکس جزئی این را حل
| می‌کند ولی partial index بین sqlite و MySQL رفتار یکسان ندارد و در MySQL
| هیچ‌وقت به شکل قابل حمل نیست.
|
| راه‌حل: ستون کمکی که فقط برای ثبت‌نام‌های فعال مقدار می‌گیرد. چون NULL در
| هر دو پایگاه داده یکتا شمرده می‌شود، چند ثبت‌نام لغو شده با هم مشکلی
| ندارند و ایندکس یکتای استاندارد کار می‌کند.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->string('active_key', 60)->nullable()->after('status');
        });

        DB::table('course_enrollments')
            ->where('status', '!=', 'cancelled')
            ->update([
                'active_key' => DB::raw("student_id || ':' || course_id"),
            ]);

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->unique('active_key');
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropUnique(['active_key']);
            $table->dropColumn('active_key');
        });
    }
};
