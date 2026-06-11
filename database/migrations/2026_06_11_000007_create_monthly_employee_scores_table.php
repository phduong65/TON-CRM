<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_employee_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');   // 1–12
            $table->unsignedSmallInteger('year');   // e.g. 2026
            $table->unsignedInteger('initial_score')->default(100);
            $table->unsignedInteger('deducted_points')->default(0);
            $table->integer('final_score')->default(100);
            $table->enum('zone', ['green', 'yellow', 'orange', 'red'])->default('green');
            $table->timestamps();

            $table->unique(['employee_id', 'month', 'year']);
            $table->index(['month', 'year']);
            $table->index(['employee_id', 'zone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_employee_scores');
    }
};
