<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Extend notifications.type enum with report types (MySQL only — SQLite does not support MODIFY COLUMN)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'general',
                'penalty_created','penalty_approved','penalty_rejected',
                'reward_created','reward_approved','reward_rejected',
                'redzone_alert',
                'report_created','report_approved','report_rejected'
            ) NOT NULL DEFAULT 'general'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'general',
                'penalty_created','penalty_approved','penalty_rejected',
                'reward_created','reward_approved','reward_rejected',
                'redzone_alert'
            ) NOT NULL DEFAULT 'general'");
        }
    }
};
