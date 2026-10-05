<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_coverage_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('days_of_week'); // [1, 2, 3, 4, 5, 6, 7] Thứ 2 .. Chủ Nhật
            $table->string('start_time', 8); // '11:00' hoặc '11:00:00'
            $table->string('end_time', 8);   // '15:00' hoặc '00:00' (hỗ trợ qua nửa đêm)
            $table->unsignedInteger('minimum_staff')->default(1);
            $table->unsignedInteger('target_staff')->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'team_id', 'is_active'], 'scr_branch_team_active_idx');
            $table->index(['effective_from', 'effective_until'], 'scr_effective_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_coverage_requirements');
    }
};
