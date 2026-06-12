<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_reports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('reporter_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reported_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('violation_id')->nullable()->constrained('violations')->nullOnDelete();
            $table->text('description');
            $table->text('evidence_note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedInteger('reward_points')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('reporter_employee_id');
            $table->index('reported_employee_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_reports');
    }
};
