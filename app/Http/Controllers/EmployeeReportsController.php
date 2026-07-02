<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeReportRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeReport;
use App\Models\EmployeeReportMember;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Regulation;
use App\Models\Setting;
use App\Models\Team;
use App\Models\Violation;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
                'team',
                'members.employee',
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

        $branches    = Branch::where('is_active', true)->orderBy('name')->get();
        $teams       = Team::where('is_active', true)->withCount(['employees as employees_count' => fn($q) => $q->where('is_active', true)])->orderBy('name')->get();
        $regulations = Regulation::where('is_active', true)->orderBy('name')->get();
        $violations  = Violation::where('is_active', true)->orderBy('name')->get();

        // Admin/Director không thuộc diện bị báo cáo/chấm điểm kỷ luật nội bộ
        $employees = Employee::where('is_active', true)
            ->whereDoesntHave('user', fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', ['admin', 'director'])))
            ->with('branch')
            ->orderBy('name')
            ->get();

        $currentEmployee = auth()->user()->employee;

        return view('reports.index', compact(
            'reports', 'violations', 'employees', 'currentEmployee',
            'canApprove', 'branches', 'teams', 'regulations'
        ));
    }

    public function store(StoreEmployeeReportRequest $request)
    {
        $reporterEmployee = auth()->user()->employee;

        if (!$reporterEmployee) {
            return back()
                ->with('error', 'Bạn cần liên kết tài khoản với một nhân viên để tạo báo cáo.')
                ->withInput();
        }

        $type = $request->type;

        // ── Xác định danh sách nhân viên bị báo cáo theo từng hình thức ────────
        $primaryEmployeeId = null;
        $memberEmployeeIds = [];

        if ($type === 'team') {
            $team = Team::with(['employees' => fn($q) => $q->where('is_active', true)])->findOrFail($request->team_id);
            $memberEmployeeIds = $team->employees->pluck('id')->all();

            if (empty($memberEmployeeIds)) {
                return back()->withErrors(['team_id' => 'Team này hiện không có nhân viên nào.'])->withInput();
            }
        } elseif ($type === 'joint') {
            $primaryEmployeeId = (int) $request->reported_employee_id;
            $memberEmployeeIds = collect($request->members ?? [])
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id !== $primaryEmployeeId)
                ->unique()
                ->values()
                ->all();
        } else { // individual
            $primaryEmployeeId = (int) $request->reported_employee_id;
        }

        $allTargetIds = collect([$primaryEmployeeId])->merge($memberEmployeeIds)->filter()->unique();

        if ($allTargetIds->contains($reporterEmployee->id)) {
            return back()
                ->withErrors(['reported_employee_id' => 'Không thể tự báo cáo chính mình.'])
                ->withInput();
        }

        $count = EmployeeReport::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->withTrashed()
            ->count() + 1;
        $code = 'RPT-' . now()->format('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $rewardPoints  = (int) Setting::getValue('report_reward_points', 5);
        $evidenceFiles = $this->processEvidenceFiles($request);

        $report = DB::transaction(function () use (
            $type, $primaryEmployeeId, $memberEmployeeIds, $request,
            $reporterEmployee, $code, $rewardPoints, $evidenceFiles
        ) {
            $report = EmployeeReport::create([
                'code'                  => $code,
                'reporter_employee_id'  => $reporterEmployee->id,
                'reported_employee_id'  => $primaryEmployeeId,
                'type'                  => $type,
                'team_id'               => $type === 'team' ? $request->team_id : null,
                'violation_id'          => $request->violation_id ?: null,
                'description'           => $request->description,
                'evidence_note'         => $request->evidence_note ?: null,
                'evidence_files'        => $evidenceFiles ?: null,
                'status'                => 'pending',
                'reward_points'         => $rewardPoints,
                'created_by'            => auth()->id(),
            ]);

            foreach ($memberEmployeeIds as $empId) {
                EmployeeReportMember::create([
                    'employee_report_id' => $report->id,
                    'employee_id'        => $empId,
                ]);
            }

            return $report;
        });

        $report->loadMissing(['reporter', 'reported', 'team', 'members.employee', 'violation']);

        $targetLabel = match ($type) {
            'team'  => 'team ' . ($report->team?->name ?? '—'),
            'joint' => $report->targetEmployees()->pluck('name')->implode(', '),
            default => $report->reported?->name ?? '—',
        };

        activity()->causedBy(auth()->user())
            ->performedOn($report)
            ->inLog('report')
            ->withProperties([
                'code'           => $report->code,
                'reporter'       => $report->reporter?->name,
                'type'           => $report->type,
                'reported'       => $targetLabel,
                'violation'      => $report->violation?->name,
                'evidence_count' => count($evidenceFiles),
            ])
            ->log('Tạo báo cáo ' . $report->code
                . ' — ' . ($report->reporter?->name ?? '—')
                . ' báo cáo ' . $targetLabel);

        app(NotificationService::class)->notifyReportCreated($report);

        return redirect()->route('reports.show', $report)
            ->with('success', 'Tạo báo cáo thành công!');
    }

    public function show(EmployeeReport $report)
    {
        if (!auth()->user()->can('approve-reports') && $report->created_by !== auth()->id()) {
            abort(403);
        }

        $report->load(['reporter.branch', 'reported.branch', 'team', 'members.employee.branch', 'violation', 'creator', 'reviewer']);
        return view('reports.show', compact('report'));
    }

    public function approve(EmployeeReport $report)
    {
        $deductedTotal = 0;

        DB::transaction(function () use ($report, &$deductedTotal) {
            $fresh = EmployeeReport::lockForUpdate()->findOrFail($report->id);
            abort_if($fresh->status !== 'pending', 403, 'Báo cáo không ở trạng thái chờ duyệt.');

            $fresh->load(['violation', 'members']);

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

            $pointsPerTarget = $fresh->violation_id ? (int) ($fresh->violation?->points_deducted ?? 0) : 0;

            if ($pointsPerTarget > 0) {
                $targetIds = collect([$fresh->reported_employee_id])
                    ->merge($fresh->members->pluck('employee_id'))
                    ->filter()
                    ->unique();

                // Admin/Director không bị trừ điểm — loại khỏi danh sách chịu phạt
                $exemptIds = Employee::whereIn('id', $targetIds)
                    ->whereHas('user', fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', ['admin', 'director'])))
                    ->pluck('id');

                $chargeableIds = $targetIds->diff($exemptIds);

                foreach ($chargeableIds as $empId) {
                    EmployeeScore::create([
                        'employee_id'    => $empId,
                        'points'         => -$pointsPerTarget,
                        'reason'         => 'Bị báo cáo vi phạm: ' . $fresh->violation->name . ' (' . $fresh->code . ')',
                        'type'           => 'penalty',
                        'reference_type' => EmployeeReport::class,
                        'reference_id'   => $fresh->id,
                    ]);
                    MonthlyEmployeeScore::ensureExists($empId, now()->month, now()->year)
                        ->deduct($pointsPerTarget);
                    $deductedTotal += $pointsPerTarget;
                }
            }

            $fresh->update([
                'status'          => 'approved',
                'reviewed_by'     => auth()->id(),
                'reviewed_at'     => now(),
                'deducted_points' => $deductedTotal,
            ]);

            $report->fill($fresh->getAttributes());
            $report->setRelation('violation', $fresh->violation);
        });

        $report->loadMissing(['reporter', 'reported', 'team', 'members.employee', 'violation']);
        activity()->causedBy(auth()->user())
            ->performedOn($report)
            ->inLog('report')
            ->withProperties([
                'code'            => $report->code,
                'reporter'        => $report->reporter?->name,
                'reward_points'   => $report->reward_points,
                'deducted_points' => $deductedTotal,
                'approved_by'     => auth()->user()->name,
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

    // ── File handling ────────────────────────────────────────────────────────

    private function processEvidenceFiles(Request $request): array
    {
        if (!$request->hasFile('evidence_files')) {
            return [];
        }

        $paths     = [];
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($request->file('evidence_files') as $file) {
            $ext = strtolower($file->getClientOriginalExtension());

            if (in_array($ext, $imageExts)) {
                $paths[] = $this->resizeAndStoreImage($file, $ext);
            } else {
                $paths[] = $file->store('reports/evidence', 'public');
            }
        }

        return $paths;
    }

    private function resizeAndStoreImage(UploadedFile $file, string $ext): string
    {
        $maxDim = 1000;

        [$origW, $origH] = @getimagesize($file->getRealPath()) ?: [0, 0];

        // Skip resize if GD info unavailable or image already within bounds
        if ($origW === 0 || ($origW <= $maxDim && $origH <= $maxDim)) {
            return $file->store('reports/evidence', 'public');
        }

        try {
            $ratio = min($maxDim / $origW, $maxDim / $origH);
            $newW  = max(1, (int) round($origW * $ratio));
            $newH  = max(1, (int) round($origH * $ratio));

            $src = match ($ext) {
                'png'  => imagecreatefrompng($file->getRealPath()),
                'gif'  => imagecreatefromgif($file->getRealPath()),
                'webp' => imagecreatefromwebp($file->getRealPath()),
                default => imagecreatefromjpeg($file->getRealPath()),
            };

            $dst = imagecreatetruecolor($newW, $newH);

            if ($ext === 'png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefilledrectangle($dst, 0, 0, $newW, $newH,
                    imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($src);

            $storageDir = storage_path('app/public/reports/evidence');
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $outputExt = in_array($ext, ['png', 'gif', 'webp']) ? $ext : 'jpg';
            $filename  = Str::uuid() . '.' . $outputExt;
            $fullPath  = $storageDir . '/' . $filename;

            match ($ext) {
                'png'  => imagepng($dst, $fullPath, 6),
                'gif'  => imagegif($dst, $fullPath),
                'webp' => imagewebp($dst, $fullPath, 85),
                default => imagejpeg($dst, $fullPath, 85),
            };

            imagedestroy($dst);

            return 'reports/evidence/' . $filename;
        } catch (\Throwable) {
            // Fallback: store original without resize
            return $file->store('reports/evidence', 'public');
        }
    }
}
