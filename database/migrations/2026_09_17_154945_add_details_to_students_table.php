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
        Schema::table('students', function (Blueprint $table) {
            // اطلاعات هویتی
            $table->string('national_code', 10)->nullable();
            $table->string('father_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();

            // اطلاعات تماس
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // اطلاعات سرپرست
            $table->string('guardian_name')->nullable();
            $table->string('guardian_mobile')->nullable();

            // اطلاعات محل سکونت
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'national_code',
                'father_name',
                'birth_date',
                'gender',
                'phone',
                'email',
                'guardian_name',
                'guardian_mobile',
                'province',
                'city',
                'address',
                'postal_code',
            ]);
        });
    }
};