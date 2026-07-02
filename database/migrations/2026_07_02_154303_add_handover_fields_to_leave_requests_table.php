<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('shift_schedule_id')->nullable()->after('date_to')
                ->constrained('shift_schedules')->nullOnDelete();
            $table->string('handover_to', 150)->nullable()->after('reason');
            $table->string('handover_phone', 20)->nullable()->after('handover_to');
            $table->text('handover_note')->nullable()->after('handover_phone');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_schedule_id');
            $table->dropColumn(['handover_to', 'handover_phone', 'handover_note']);
        });
    }
};
