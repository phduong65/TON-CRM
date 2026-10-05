<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->boolean('is_partial_day')->default(false)->after('date_to');
            $table->time('from_time')->nullable()->after('is_partial_day');
            $table->time('to_time')->nullable()->after('from_time');
            $table->decimal('day_fraction', 3, 2)->nullable()->after('to_time');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['is_partial_day', 'from_time', 'to_time', 'day_fraction']);
        });
    }
};
