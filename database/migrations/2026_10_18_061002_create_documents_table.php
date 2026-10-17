<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // کسی که سند را بارگذاری کرده. nullOnDelete چون سند پذیرش باید
            // بماند حتی اگر کارمندی که ثبتش کرده از سامانه حذف شود
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // سند پذیرش به یک دانش‌آموز وصل است؛ سند عمومی (مجوز، رزرو) ممکن است
            // نباشد، پس nullable است
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type', 30);
            $table->string('title');

            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->date('document_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['type', 'document_date']);
            $table->index(['student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
