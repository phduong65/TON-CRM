<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chuyển employees.position từ text tự do sang dropdown chọn sẵn (bảng positions) — tránh dữ
 * liệu chức danh bị nhập lệch chính tả/hoa-thường (VD "Phục vụ" vs "Phục Vụ") khiến các báo cáo
 * sắp xếp/nhóm theo chức danh (Xuất Excel báo cáo chấm công...) bị tách nhóm sai.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('position')
                ->constrained('positions')->nullOnDelete();
        });

        // Gom nhóm các giá trị employees.position hiện có (không phân biệt hoa/thường, đã trim)
        // thành các bản ghi Position — giữ nguyên cách viết xuất hiện đầu tiên (theo id tăng dần)
        // làm tên hiển thị chuẩn cho cả nhóm.
        $employees = DB::table('employees')
            ->whereNotNull('position')
            ->where('position', '!=', '')
            ->orderBy('id')
            ->get(['id', 'position']);

        $positionIdByKey = [];
        foreach ($employees as $employee) {
            $name = trim($employee->position);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);

            if (!isset($positionIdByKey[$key])) {
                $positionIdByKey[$key] = DB::table('positions')->insertGetId([
                    'name'       => $name,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('employees')->where('id', $employee->id)->update([
                'position_id' => $positionIdByKey[$key],
            ]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('position', 255)->nullable()->after('email');
        });

        DB::table('employees')
            ->join('positions', 'positions.id', '=', 'employees.position_id')
            ->update(['employees.position' => DB::raw('positions.name')]);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
        });
    }
};
