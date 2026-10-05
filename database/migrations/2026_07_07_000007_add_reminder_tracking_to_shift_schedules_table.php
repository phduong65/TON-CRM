<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->timestamp('checkin_reminder_sent_at')->nullable()->after('custom_is_wfh');
            $table->timestamp('checkout_reminder_sent_at')->nullable()->after('checkin_reminder_sent_at');
            $table->timestamp('checkin_late_alert_sent_at')->nullable()->after('checkout_reminder_sent_at');
            $table->timestamp('checkout_late_alert_sent_at')->nullable()->after('checkin_late_alert_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'checkin_reminder_sent_at',
                'checkout_reminder_sent_at',
                'checkin_late_alert_sent_at',
                'checkout_late_alert_sent_at',
            ]);
        });
    }
};
