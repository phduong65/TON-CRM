<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE penalties MODIFY COLUMN status ENUM('pending','approved','rejected','revoked') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('penalties', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('approved_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete()->after('revoked_at');
            $table->string('revoked_reason', 500)->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['revoked_at', 'revoked_reason']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE penalties MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
        }
    }
};
