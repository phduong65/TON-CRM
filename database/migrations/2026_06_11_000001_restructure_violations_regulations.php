<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // violations: bỏ category, thêm penalty_type + amounts
        Schema::table('violations', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->enum('penalty_type', ['points', 'money', 'both'])
                  ->default('points')
                  ->after('regulation_id');
            $table->integer('points_deducted')->default(0)->after('penalty_type');
            $table->decimal('money_deducted', 12, 2)->default(0)->after('points_deducted');
        });

        // regulations: bỏ penalty fields — chỉ là danh mục
        Schema::table('regulations', function (Blueprint $table) {
            $table->dropColumn(['type', 'default_points', 'default_money']);
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->dropColumn(['penalty_type', 'points_deducted', 'money_deducted']);
            $table->string('category', 100)->nullable()->after('severity');
        });

        Schema::table('regulations', function (Blueprint $table) {
            $table->enum('type', ['points', 'money', 'both'])->default('points');
            $table->integer('default_points')->default(0);
            $table->decimal('default_money', 12, 2)->default(0);
        });
    }
};
