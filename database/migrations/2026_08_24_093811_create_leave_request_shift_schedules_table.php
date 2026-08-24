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
        // Cho phép 1 đơn xin nghỉ (is_partial_day) chọn NHIỀU ca cụ thể, có thể thuộc nhiều ngày
        // khác nhau (VD NV part-time xin nghỉ 3 trong 4 ca của 2 ngày) — thay cho cột đơn
        // leave_requests.shift_schedule_id chỉ hỗ trợ đúng 1 ca/1 ngày (giữ lại cột đó cho dữ liệu
        // cũ trước khi có bảng này). day_fraction lưu riêng cho từng ca vì mẫu số (tổng phút các ca
        // đã xếp trong ngày) khác nhau giữa các ngày trong cùng 1 đơn.
        Schema::create('leave_request_shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_schedule_id')->constrained()->cascadeOnDelete();
            $table->decimal('day_fraction', 3, 2);
            $table->timestamps();
            $table->unique(['leave_request_id', 'shift_schedule_id'], 'lrss_leave_shift_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_request_shift_schedules');
    }
};
