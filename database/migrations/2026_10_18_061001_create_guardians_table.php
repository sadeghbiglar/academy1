<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');

            // رابطه با دانش‌آموز enum است، نه رشته آزاد، تا «پدر/مادر/عمو/دایی»
            // همه ممکن شوند بدون اینکه typو کردنشان به مقایسه رشته تبدیل شود
            $table->string('relation', 30);

            $table->string('mobile', 20);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('national_code', 10)->nullable();

            // سرپرست اصلی همان است که به او زنگ می‌زنند
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // عمداً unique نیست: unique(student_id, is_primary) بیش از حد سخت‌گیر
            // است چون حتی دو سرپرست غیر‌اصلی را هم رد می‌کند. قاعده درست «حداکثر
            // یک سرپرست اصلی» است که در Guardian::booted() اعمال می‌شود، چون
            // unique جزئی روی sqlite/mysql با هم یکسان رفتار نمی‌کند
            $table->index(['student_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
