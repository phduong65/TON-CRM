<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_reports', function (Blueprint $table) {
            $table->enum('type', ['individual', 'team', 'joint'])->default('individual')->after('reported_employee_id');
            $table->foreignId('team_id')->nullable()->after('type')->constrained('teams')->nullOnDelete();
            $table->unsignedInteger('deducted_points')->default(0)->after('reward_points');
        });

        // Widen reported_employee_id to nullable (report có thể ở dạng "team" — không có 1 cá nhân chính)
        // SQLite (dùng cho test suite) không hỗ trợ cú pháp MODIFY của MySQL và vốn không
        // enforce NOT NULL nghiêm ngặt qua ALTER TABLE — chỉ chạy trên MySQL.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE employee_reports MODIFY reported_employee_id BIGINT UNSIGNED NULL');
        }

        Schema::create('employee_report_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_report_id')->constrained('employee_reports')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedInteger('points_deducted')->default(0);
            $table->timestamps();

            $table->unique(['employee_report_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_report_members');

        Schema::table('employee_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['type', 'deducted_points']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE employee_reports MODIFY reported_employee_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
