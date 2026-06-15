<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_reports', function (Blueprint $table) {
            $table->json('evidence_files')->nullable()->after('evidence_note');
        });
    }

    public function down(): void
    {
        Schema::table('employee_reports', function (Blueprint $table) {
            $table->dropColumn('evidence_files');
        });
    }
};
