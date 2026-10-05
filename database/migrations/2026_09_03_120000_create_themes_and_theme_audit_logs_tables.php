<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->tinyInteger('level')->default(1)->comment('1: Corporate, 2: Celebration, 3: Company Event');
            $table->string('status', 20)->default('draft')->index(); // draft, scheduled, active, paused, archived
            $table->integer('priority')->default(0)->index();
            $table->string('timezone', 50)->default('Asia/Ho_Chi_Minh');
            $table->dateTime('start_at')->nullable()->index();
            $table->dateTime('end_at')->nullable()->index();
            $table->json('scope')->nullable();
            $table->string('audience', 50)->default('all');
            $table->json('config');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_at', 'end_at', 'priority']);
        });

        Schema::create('theme_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // created, updated, scheduled, activated, paused, rollback, archived
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['theme_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_audit_logs');
        Schema::dropIfExists('themes');
    }
};
