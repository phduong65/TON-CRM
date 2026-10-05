<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_locations', function (Blueprint $table) {
            $table->timestamp('ip_mismatch_alerted_at')->nullable()->after('allowed_ips');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_locations', function (Blueprint $table) {
            $table->dropColumn('ip_mismatch_alerted_at');
        });
    }
};
