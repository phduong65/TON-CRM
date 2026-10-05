<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Một số môi trường (production) đã có sẵn bảng fcm_tokens từ trước khi migration gốc
     * (2026_07_07_000001_create_fcm_tokens_table) được cập nhật thêm cột user_agent — do
     * migration gốc có guard "return sớm nếu bảng đã tồn tại" nên không tự thêm cột khi chạy
     * lại. Thêm ở migration riêng này, có kiểm tra hasColumn để an toàn với môi trường đã có sẵn.
     */
    public function up(): void
    {
        if (Schema::hasColumn('fcm_tokens', 'user_agent')) {
            return;
        }

        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->string('user_agent', 255)->nullable()->after('token');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('fcm_tokens', 'user_agent')) {
            return;
        }

        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
};
