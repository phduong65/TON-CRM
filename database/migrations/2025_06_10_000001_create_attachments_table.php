<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penalty_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->enum('type', ['image', 'video']);
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('penalty_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
