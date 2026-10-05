<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_ip_mismatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->timestamps();

            $table->index(['attendance_location_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_ip_mismatches');
    }
};
