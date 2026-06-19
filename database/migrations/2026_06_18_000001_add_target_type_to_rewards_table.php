<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->enum('target_type', ['individual', 'branch', 'team', 'all'])
                  ->default('individual')
                  ->after('code');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->unsignedBigInteger('employee_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropColumn(['target_type', 'target_id']);
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
        });
    }
};
