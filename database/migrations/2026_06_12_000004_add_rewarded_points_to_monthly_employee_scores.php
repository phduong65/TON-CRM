<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_employee_scores', function (Blueprint $table) {
            $table->unsignedInteger('rewarded_points')->default(0)->after('deducted_points');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_employee_scores', function (Blueprint $table) {
            $table->dropColumn('rewarded_points');
        });
    }
};
