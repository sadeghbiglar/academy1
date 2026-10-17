<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| وضعیت از خود دانش‌آموز به پرونده منتقل شد (شماره 34).
|
| پروندهٔ آموزشی وضعیت را نگه می‌دارد، چون وضعیت مالکیت متفاوتی از رکورد پایه
| دارد. نگه‌داشتن ستون در هر دو جدول یعنی هر تغییر وضعیت باید در دو جا نوشته
| شود و یکی از آن‌ها باز می‌ماند — همان ریسکی که #33 حذفش کرد.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('status', 20)->default('enrolling')->after('gender');
        });
    }
};
