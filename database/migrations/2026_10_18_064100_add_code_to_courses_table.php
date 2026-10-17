<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| کد دوره.
|
| پذیرش برای هر ثبت‌نام باید یک چیز قابل خواندن و گفتن داشته باشد. عنوان دوره
| برای این کار نیست: دو ترم از یک دوره عنوان یکسان دارند. کد یکتا است و
| nullable تا ثبت سریع مانع کار نباشد.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->unique()->after('title');
        });

        // دوره‌های موجود کد می‌گیرند تا هیچ‌کدام بدون کد نمانند
        foreach (DB::table('courses')->whereNull('code')->get() as $course) {
            DB::table('courses')->where('id', $course->id)->update([
                'code' => 'C-'.str_pad((string) $course->id, 5, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
