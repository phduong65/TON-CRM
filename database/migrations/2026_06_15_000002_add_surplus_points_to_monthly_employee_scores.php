<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_employee_scores', function (Blueprint $table) {
            $table->unsignedInteger('surplus_points')->default(0)->after('rewarded_points');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_employee_scores', function (Blueprint $table) {
            $table->dropColumn('surplus_points');
        });
    }
};
