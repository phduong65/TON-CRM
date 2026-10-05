<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_schedule_id')->constrained('shift_schedules')->cascadeOnDelete();
            $table->enum('alert_type', ['missing_check_in', 'missing_check_out']);
            $table->enum('status', ['open', 'seen', 'resolved', 'excused'])->default('open');
            $table->dateTime('triggered_at');
            $table->dateTime('seen_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->unique(['shift_schedule_id', 'alert_type'], 'att_alerts_schedule_type_unique');
            $table->index(['employee_id', 'status'], 'att_alerts_emp_status_idx');
            $table->index(['status', 'triggered_at'], 'att_alerts_status_trig_idx');
            $table->index('triggered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_alerts');
    }
};
