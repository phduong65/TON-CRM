<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('late_penalty_id')->nullable()->after('late_minutes')
                ->constrained('penalties')->nullOnDelete();
            $table->foreignId('early_penalty_id')->nullable()->after('early_minutes')
                ->constrained('penalties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('late_penalty_id');
            $table->dropConstrainedForeignId('early_penalty_id');
        });
    }
};
