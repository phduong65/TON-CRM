<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_schedule_recurrences', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->unique();
            $table->json('shift_ids');
            $table->json('employee_ids');
            $table->json('weekdays');
            $table->date('starts_on');
            $table->date('last_generated_through')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedule_recurrences');
    }
};
