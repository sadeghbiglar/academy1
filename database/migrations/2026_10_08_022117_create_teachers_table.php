<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();

            // اطلاعات هویتی
            $table->string('first_name');
            $table->string('last_name');
            $table->string('national_code', 10)->nullable();
            $table->string('father_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();

            // اطلاعات تماس
            $table->string('mobile');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // اطلاعات شغلی
            $table->string('subject')->nullable();
            $table->date('hire_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
