<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('points_awarded')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['reward_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_members');
    }
};
