<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE violations MODIFY COLUMN severity ENUM('low','medium','high','critical','extreme') NOT NULL DEFAULT 'medium'");
    }

    public function down(): void
    {
        DB::table('violations')->where('severity', 'extreme')->update(['severity' => 'critical']);
        DB::statement("ALTER TABLE violations MODIFY COLUMN severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium'");
    }
};
