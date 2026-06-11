<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_import_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['late', 'early'])->comment('late=đi trễ, early=về sớm');
            $table->unsignedSmallInteger('min_minutes')->default(1)->comment('Từ X phút (inclusive)');
            $table->unsignedSmallInteger('max_minutes')->nullable()->comment('Đến Y phút (inclusive), null = không giới hạn');
            $table->foreignId('violation_id')->constrained()->onDelete('cascade');
            $table->string('label')->nullable()->comment('Nhãn mô tả ngưỡng, VD: Dưới 15 phút');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active', 'min_minutes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_import_rules');
    }
};
