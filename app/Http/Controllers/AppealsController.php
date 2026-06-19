<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Penalty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppealsController extends Controller
{
    public function index(Request $request)
    {
        $query = Appeal::with(['penalty.employee.branch', 'penalty.violation', 'appellant'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc');

        // Chỉ người duyệt khiếu nại thấy tất cả; người khiếu nại / người bị phạt chỉ thấy khiếu nại liên quan đến mình
        if (!auth()->user()->can('review-appeals')) {
            $userId   = auth()->id();
            $employee = auth()->user()->employee;

            $query->where(function ($q) use ($userId, $employee) {
                $q->where('appellant_id', $userId);

                if ($employee) {
                    $q->orWhereHas('penalty', fn($p) =>
                        $p->where('employee_id', $employee->id)
                          ->orWhereHas('members', fn($m) => $m->where('employee_id', $employee->id))
                    );
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('penalty', fn($pq) => $pq->where('code', 'like', "%$s%"))
                  ->orWhereHas('penalty.employee', fn($eq) => $eq->where('name', 'like', "%$s%"));
            });
        }

        $appeals = $query->paginate(15)->withQueryString();

        return view('appeals.index', compact('appeals'));
    }

    public function store(Request $request, Penalty $penalty)
    {
        abort_unless(
            $penalty->employee?->user_id === auth()->id(),
            403,
            'Chỉ nhân viên bị phạt mới có thể gửi khiếu nại.'
        );
        abort_if($penalty->status !== 'approved', 422, 'Chỉ có thể khiếu nại phiếu phạt đã được duyệt.');

        $existing = Appeal::where('penalty_id', $penalty->id)->where('status', 'pending')->exists();
        abort_if($existing, 422, 'Phiếu phạt này đang có khiếu nại chờ xét duyệt.');

        $request->validate(['reason' => 'required|string|max:1000']);

        $appeal = Appeal::create([
            'penalty_id'   => $penalty->id,
            'appellant_id' => auth()->id(),
            'reason'       => $request->reason,
            'status'       => 'pending',
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($appeal)
            ->inLog('appeal')
            ->withProperties(['penalty_code' => $penalty->code])
            ->log('Gửi khiếu nại phiếu phạt ' . $penalty->code);

        return back()->with('success', 'Đã gửi khiếu nại thành công. Vui lòng chờ kết quả xét duyệt.');
    }

    public function accept(Request $request, Appeal $appeal)
    {
        abort_if($appeal->status !== 'pending', 403, 'Khiếu nại này đã được xử lý.');

        DB::transaction(function () use ($appeal) {
            $appeal->update([
                'status'        => 'accepted',
                'reviewer_id'   => auth()->id(),
                'reviewed_at'   => now(),
                'reviewer_note' => 'Chấp nhận khiếu nại — phiếu phạt sẽ được thu hồi.',
            ]);

            $penalty = Penalty::with('members')->lockForUpdate()->findOrFail($appeal->penalty_id);
            abort_if($penalty->status !== 'approved', 403, 'Phiếu phạt không còn ở trạng thái đã duyệt.');

            $penalty->update([
                'status'         => 'revoked',
                'revoked_by'     => auth()->id(),
                'revoked_at'     => now(),
                'revoked_reason' => 'Thu hồi do chấp nhận khiếu nại #' . $appeal->id,
            ]);

            $approvedMonth = $penalty->approved_at->month;
            $approvedYear  = $penalty->approved_at->year;

            if ($penalty->total_points_deducted > 0) {
                EmployeeScore::create([
                    'employee_id'    => $penalty->employee_id,
                    'points'         => $penalty->total_points_deducted,
                    'reason'         => 'Hoàn điểm do khiếu nại được chấp nhận: ' . $penalty->code,
                    'type'           => 'adjustment',
                    'reference_type' => Penalty::class,
                    'reference_id'   => $penalty->id,
                ]);
                $this->refundMonthly($penalty->employee_id, $penalty->total_points_deducted, $approvedMonth, $approvedYear);
            }

            foreach ($penalty->members as $member) {
                if ($member->points_deducted > 0) {
                    EmployeeScore::create([
                        'employee_id'    => $member->employee_id,
                        'points'         => $member->points_deducted,
                        'reason'         => 'Hoàn điểm liên đới do khiếu nại được chấp nhận: ' . $penalty->code,
                        'type'           => 'adjustment',
                        'reference_type' => Penalty::class,
                        'reference_id'   => $penalty->id,
                    ]);
                    $this->refundMonthly($member->employee_id, $member->points_deducted, $approvedMonth, $approvedYear);
                }
            }
        });

        $appeal->loadMissing(['penalty.employee', 'penalty.violation']);
        activity()->causedBy(auth()->user())
            ->performedOn($appeal)
            ->inLog('appeal')
            ->withProperties(['penalty_code' => $appeal->penalty?->code])
            ->log('Chấp nhận khiếu nại — thu hồi phiếu phạt ' . $appeal->penalty?->code);

        return back()->with('success', 'Đã chấp nhận khiếu nại và thu hồi phiếu phạt!');
    }

    public function reject(Request $request, Appeal $appeal)
    {
        abort_if($appeal->status !== 'pending', 403, 'Khiếu nại này đã được xử lý.');

        $request->validate(['reviewer_note' => 'required|string|max:500']);

        $appeal->update([
            'status'        => 'rejected',
            'reviewer_id'   => auth()->id(),
            'reviewed_at'   => now(),
            'reviewer_note' => $request->reviewer_note,
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($appeal)
            ->inLog('appeal')
            ->withProperties(['penalty_code' => $appeal->penalty?->code, 'note' => $request->reviewer_note])
            ->log('Từ chối khiếu nại phiếu phạt ' . $appeal->penalty?->code);

        return back()->with('success', 'Đã từ chối khiếu nại.');
    }

    private function refundMonthly(int $employeeId, int $points, int $month, int $year): void
    {
        $record = MonthlyEmployeeScore::where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($record) {
            $record->refundDeduction($points);
        }
    }
}
