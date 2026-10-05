<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Services\AnnualLeaveService;
use Illuminate\Http\Request;

/**
 * Báo cáo tổng hợp "Phép năm" — liệt kê tất cả nhân viên văn phòng đủ điều kiện
 * (Employee::isEligibleForAnnualLeave()) cùng lúc, thay vì phải xem từng người trên trang chi
 * tiết nhân viên (EmployeesController::show()). Dùng chung AnnualLeaveService nên luôn khớp
 * quy tắc +1 ngày/tháng làm trọn vẹn, tính lại từ 1/1 mỗi năm (không cộng dồn qua năm).
 */
class AnnualLeaveController extends Controller
{
    public function index(Request $request, AnnualLeaveService $service)
    {
        $year = (int) $request->input('year', now()->year);

        $query = Employee::where('is_active', true)
            ->where('employment_type', 'full_time')
            ->where('is_office', true)
            ->with(['branch', 'team']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        $rows = $query->orderBy('name')->get()->map(fn(Employee $e) => [
            'employee'  => $e,
            'entitled'  => $service->entitledDays($e, $year),
            'used'      => $service->usedDays($e, $year),
            'remaining' => $service->remainingDays($e, $year),
        ]);

        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        $yearOptions = range(now()->year, now()->year - 3);

        return view('annual-leave.index', compact('rows', 'branches', 'year', 'yearOptions'));
    }
}
