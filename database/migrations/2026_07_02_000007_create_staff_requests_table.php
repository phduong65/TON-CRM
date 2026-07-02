<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // 4 loại yêu cầu gộp trong "Yêu cầu và Phê duyệt" — Nghỉ phép (leave_requests) và
            // Đổi ca (shift_swap_requests) vẫn dùng bảng/luồng riêng vì đã có sẵn, đầy đủ test.
            $table->enum('type', ['attendance_correction', 'business_trip', 'late_early', 'time_change']);
            $table->date('work_date');
            // Dữ liệu riêng theo từng loại — VD attendance_correction: {check_in_at, check_out_at},
            // business_trip: {from_time, to_time, location}, late_early: {mode, minutes},
            // time_change: {new_check_in, new_check_out}.
            $table->json('payload');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index('status');
            $table->index('type');
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_requests');
    }
};
