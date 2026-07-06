<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
        });

        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->change();

            $table->time('custom_start_time')->nullable()->after('shift_id');
            $table->time('custom_end_time')->nullable()->after('custom_start_time');
            $table->unsignedInteger('custom_break_minutes')->nullable()->after('custom_end_time');
            $table->boolean('custom_is_overnight')->nullable()->after('custom_break_minutes');
            $table->boolean('custom_is_wfh')->nullable()->after('custom_is_overnight');
        });

        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['custom_start_time', 'custom_end_time', 'custom_break_minutes', 'custom_is_overnight', 'custom_is_wfh']);
        });

        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable(false)->change();
        });

        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
        });
    }
};
