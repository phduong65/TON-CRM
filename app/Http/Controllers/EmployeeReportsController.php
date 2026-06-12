<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeReportRequest;
use App\Models\Employee;
use App\Models\EmployeeReport;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Setting;
use App\Models\Violation;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeReportsController extends Controller
{
    public function index(Request $request)
    {
        $canApprove = auth()->user()->can('approve-reports');

        $query = EmployeeReport::with([
                'reporter.branch',
                'reported.branch',
                'violation',
                'creator',
            ])
            ->orderBy('created_at', 'desc');

        if (!$canApprove) {
            $query->where('created_by', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', "%$s%")
                  ->orWhereHas('reporter', fn($eq) => $eq->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"))
                  ->orWhereHas('reported', fn($eq) => $eq->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
            });
        }

        $reports = $query->paginate(15)->withQueryString();

        $violations      = Violation::where('is_active', true)->orderBy('name')->get();
        $employees       = Employee::where('is_active', true)->with('branch')->orderBy('name')->get();
        $currentEmployee = auth()->user()->employee;

        return view('reports.index', compact('reports', 'violations', 'employees', 'currentEmployee', 'canApprove'));
    }

    public function store(StoreEmployeeReportRequest $request)
    {
        $reporterEmployee = auth()->user()->employee;

        if (!$reporterEmployee) {
            return back()
                ->with('error', 'Bạn cần liên kết tài khoản với một nhân viên để tạo báo cáo.')
                ->withInput();
        }

        if ($reporterEmployee->id === (int) $request->reported_employee_id) {
            return back()
                ->withErrors(['reported_employee_id' => 'Không thể tự báo cáo chính mình.'])
                ->withInput();
        }

        $count = EmployeeReport::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->withTrashed()
            ->count() + 1;
        $code = 'RPT-' . now()->format('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $rewardPoints = (int) Setting::getValue('report_reward_points', 5);

        $report = EmployeeReport::create([
            'code'                  => $code,
            'reporter_employee_id'  => $reporterEmployee->id,
            'reported_employee_id'  => $request->reported_employee_id,
            'violation_id'          => $request->violation_id ?: null,
            'description'           => $request->description,
            'evidence_note'         => $request->evidence_note ?: null,
            'status'                => 'pending',
            'reward_points'         => $rewardPoints,
            'created_by'            => auth()->id(),
        ]);

        $report->loadMissing(['reporter', 'reported', 'violation']);
        activity()->causedBy(auth()->user())
            ->performedOn($report)
            ->inLog('report')
            ->withProperties([
                'code'     => $report->code,
                'reporter' => $report->reporter?->name,
                'reported' => $report->reported?->name,
                'violation' => $report->violation?->name,
            ])
            ->log('Tạo báo cáo ' . $report->code
                . ' — ' . ($report->reporter?->name ?? '—')
                . ' báo cáo ' . ($report->reported?->name ?? '—'));

        app(NotificationService::class)->notifyReportCreated($report);

        return redirect()->route('reports.show', $report)
            ->with('success', 'Tạo báo cáo thành công!');
    }

    public function show(EmployeeReport $report)
    {
        if (!auth()->user()->can('approve-reports') && $report->created_by !== auth()->id()) {
            abort(403);
        }

        $report->load(['reporter.branch', 'reported.branch', 'violation', 'creator', 'reviewer']);
        return view('reports.show', compact('report'));
    }

    public function approve(EmployeeReport $report)
    {
        DB::transaction(function () use ($report) {
            $fresh = EmployeeReport::lockForUpdate()->findOrFail($report->id);
            abort_if($fresh->status !== 'pending', 403, 'Báo cáo không ở trạng thái chờ duyệt.');

            $fresh->update([
                'status'      => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $fresh->load('violation');

            // Cộng điểm cho reporter
            if ($fresh->reward_points > 0 && $fresh->reporter_employee_id) {
                EmployeeScore::create([
                    'employee_id'    => $fresh->reporter_employee_id,
                    'points'         => $fresh->reward_points,
                    'reason'         => 'Thưởng báo cáo: ' . $fresh->code,
                    'type'           => 'reward',
                    'reference_type' => EmployeeReport::class,
                    'reference_id'   => $fresh->id,
                ]);
                MonthlyEmployeeScore::ensureExists(
                    $fresh->reporter_employee_id,
                    now()->month,
                    now()->year
                )->reward($fresh->reward_points);
            }

            // Trừ điểm cho nhân viên bị báo cáo theo violation
            if ($fresh->violation_id && $fresh->violation?->points_deducted > 0) {
                EmployeeScore::create([
                    'employee_id'    => $fresh->reported_employee_id,
                    'points'         => -$fresh->violation->points_deducted,
                    'reason'         => 'Bị báo cáo vi phạm: ' . $fresh->violation->name . ' (' . $fresh->code . ')',
                    'type'           => 'penalty',
                    'reference_type' => EmployeeReport::class,
                    'reference_id'   => $fresh->id,
                ]);
                MonthlyEmployeeScore::ensureExists(
                    $fresh->reported_employee_id,
                    now()->month,
                    now()->year
                )->deduct($fresh->violation->points_deducted);
            }

            // Sync outer $report for post-transaction work
            $report->fill($fresh->getAttributes());
            $report->setRelation('violation', $fresh->violation);
        });

        $report->loadMissing(['reporter', 'reported', 'violation']);
        activity()->causedBy(auth()->user())
            ->performedOn($report)
            ->inLog('report')
            ->withProperties([
                'code'          => $report->code,
                'reporter'      => $report->reporter?->name,
                'reported'      => $report->reported?->name,
                'reward_points' => $report->reward_points,
                'approved_by'   => auth()->user()->name,
            ])
            ->log('Duyệt báo cáo ' . $report->code
                . ' — Cộng ' . $report->reward_points . ' điểm cho ' . ($report->reporter?->name ?? '—'));

        app(NotificationService::class)->notifyReportApproved($report);

        return back()->with('success', 'Đã duyệt báo cáo! Cộng ' . $report->reward_points . ' điểm cho ' . ($report->reporter?->name ?? 'người báo cáo') . '.');
    }

    public function reject(Request $request, EmployeeReport $report)
    {
        abort_if($report->status !== 'pending', 403, 'Báo cáo không ở trạng thái chờ duyệt.');

        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $report->update([
            'status'           => 'rejected',
            'reviewed_by'      => auth()->id(),
            'reviewed_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        $report->loadMissing(['reporter', 'reported', 'violation']);
        activity()->causedBy(auth()->user())
            ->performedOn($report)
            ->inLog('report')
            ->withProperties([
                'code'     => $report->code,
                'reporter' => $report->reporter?->name,
                'reported' => $report->reported?->name,
                'reason'   => $request->rejection_reason,
            ])
            ->log('Từ chối báo cáo ' . $report->code
                . ' — Reporter: ' . ($report->reporter?->name ?? '—')
                . ' — Lý do: ' . Str::limit($request->rejection_reason, 60));

        app(NotificationService::class)->notifyReportRejected($report, $request->rejection_reason);

        return back()->with('success', 'Đã từ chối báo cáo!');
    }
}
