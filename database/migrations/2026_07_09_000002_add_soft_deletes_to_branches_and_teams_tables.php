<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->softDeletes();
        });

        // `code` unique() phải kèm deleted_at, nếu không mã của bản ghi đã soft-delete sẽ
        // chặn vĩnh viễn việc tạo mới cùng mã (xem migration
        // 2026_07_09_000001_fix_employees_unique_constraints_for_soft_delete cho employees).
        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique('branches_code_unique');
            $table->unique(['code', 'deleted_at']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique('teams_code_unique');
            $table->unique(['code', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['code', 'deleted_at']);
            $table->unique('code');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['code', 'deleted_at']);
            $table->unique('code');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
