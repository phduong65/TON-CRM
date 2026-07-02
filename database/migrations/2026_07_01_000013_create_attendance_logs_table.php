<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');

            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->enum('check_in_method', ['gps', 'ip', 'gps_ip', 'wfh'])->nullable();
            $table->enum('check_out_method', ['gps', 'ip', 'gps_ip', 'wfh'])->nullable();

            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->string('check_in_ip', 45)->nullable();
            $table->string('check_out_ip', 45)->nullable();
            $table->foreignId('check_in_location_id')->nullable()->constrained('attendance_locations')->nullOnDelete();
            $table->foreignId('check_out_location_id')->nullable()->constrained('attendance_locations')->nullOnDelete();

            $table->integer('late_minutes')->default(0);
            $table->integer('early_minutes')->default(0);
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
