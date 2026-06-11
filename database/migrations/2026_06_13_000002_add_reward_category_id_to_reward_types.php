<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_types', function (Blueprint $table) {
            $table->foreignId('reward_category_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('reward_categories')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reward_types', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\RewardCategory::class);
            $table->dropColumn('reward_category_id');
        });
    }
};
