<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePenaltyRequest;
use App\Http\Requests\UpdatePenaltyRequest;
use App\Models\Employee;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Penalty;
use App\Models\PenaltyMember;
use App\Models\Violation;
use App\Services\AttachmentService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PenaltiesController extends Controller
{
    public function index(Request $request)
    {
        $query = Penalty::with(['employee', 'violation.regulation', 'approver', 'members', 'attachments'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('employee', fn($eq) => $eq->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"))
                  ->orWhere('code', 'like', "%$s%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $penalties = $query->paginate(15)->withQueryString();

        $violations = Violation::with('regulation')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $regulations = \App\Models\Regulation::where('is_active', true)
            ->orderBy('name')
            ->get();

        $employees = Employee::where('is_active', true)
            ->with(['branch', 'team'])
            ->orderBy('name')
            ->get();

        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get();

        $teams = \App\Models\Team::with(['employees' => fn($q) => $q->where('is_active', true)->orderBy('name'), 'branch'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // JS maps: regulation_id → violations[]
        $regulationViolationsMap = $violations->groupBy(fn($v) => $v->regulation_id ?? 0)
            ->map(fn($vios) => $vios->map(fn($v) => [
                'id'     => $v->id,
                'name'   => $v->name,
                'points' => $v->points_deducted,
                'money'  => (float) $v->money_deducted,
                'type'   => $v->penalty_type,
            ])->values());

        // JS map: team_id → employees[]
        $teamEmployeesMap = $teams->mapWithKeys(fn($t) => [
            $t->id => $t->employees->map(fn($e) => [
                'id'   => $e->id,
                'code' => $e->code,
                'name' => $e->name,
            ])->values(),
        ]);

        // Flat map violation_id → defaults (used by edit modal)
        $violationDefaults = $violations->mapWithKeys(fn($v) => [
            $v->id => [
                'points' => $v->points_deducted,
                'money'  => (float) $v->money_deducted,
                'type'   => $v->penalty_type,
            ],
        ]);

        return view('penalties.index', compact(
            'penalties', 'violations', 'regulations', 'employees', 'branches', 'teams',
            'regulationViolationsMap', 'teamEmployeesMap', 'violationDefaults'
        ));
    }

    public function store(StorePenaltyRequest $request, AttachmentService $attachments)
    {
        $count = Penalty::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        $code = 'PNL-' . now()->format('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $penalty = Penalty::create([
            'code'                  => $code,
            'created_by'            => auth()->id(),
            'employee_id'           => $request->employee_id,
            'violation_id'          => $request->violation_id,
            'description'           => $request->description,
            'status'                => 'pending',
            'total_points_deducted' => $request->points_deducted,
            'total_money_deducted'  => $request->money_deducted ?? 0,
        ]);

        if ($request->filled('members')) {
            foreach ($request->members as $m) {
                if (!empty($m['employee_id'])) {
                    PenaltyMember::create([
                        'penalty_id'      => $penalty->id,
                        'employee_id'     => $m['employee_id'],
                        'points_deducted' => $m['points_deducted'] ?? $request->points_deducted,
                        'money_deducted'  => $m['money_deducted'] ?? 0,
                        'note'            => $m['note'] ?? null,
                    ]);
                }
            }
        }

        if ($request->hasFile('attachments')) {
            $attachments->storeForPenalty($request->file('attachments'), $penalty->id);
        }

        $penalty->loadMissing(['violation', 'employee']);
        $membersCount = collect($request->members ?? [])->filter(fn($m) => !empty($m['employee_id']))->count();
        activity()->causedBy(auth()->user())
            ->performedOn($penalty)
            ->inLog('penalty')
            ->withProperties([
                'code'            => $penalty->code,
                'employee_name'   => $penalty->employee?->name,
                'employee_code'   => $penalty->employee?->code,
                'violation'       => $penalty->violation?->name,
                'points_deducted' => $penalty->total_points_deducted,
                'money_deducted'  => (float) $penalty->total_money_deducted,
                'members_count'   => $membersCount,
            ])
            ->log('Tạo phiếu phạt ' . $penalty->code
                . ' — Vi phạm: ' . ($penalty->violation?->name ?? '—')
                . ' — NV: ' . ($penalty->employee?->name ?? '—'));

        app(NotificationService::class)->notifyPenaltyCreated($penalty);

        return redirect()->route('penalties.show', $penalty)
            ->with('success', 'Tạo phiếu phạt thành công!');
    }

    public function show(Penalty $penalty)
    {
        $penalty->load(['employee', 'violation.regulation', 'approver', 'members.employee', 'attachments']);
        return view('penalties.show', compact('penalty'));
    }

    public function detailJson(Penalty $penalty)
    {
        $penalty->load(['employee.branch', 'violation.regulation', 'approver', 'members.employee', 'attachments']);
        $user = auth()->user();

        return response()->json([
            'id'                    => $penalty->id,
            'code'                  => $penalty->code ?? '#' . $penalty->id,
            'status'                => $penalty->status,
            'status_label'          => match ($penalty->status) {
                'pending'  => 'Chờ duyệt',
                'approved' => 'Đã duyệt',
                'rejected' => 'Từ chối',
                default    => $penalty->status,
            },
            'employee'              => [
                'id'     => $penalty->employee?->id,
                'name'   => $penalty->employee?->name,
                'code'   => $penalty->employee?->code,
                'branch' => $penalty->employee?->branch?->name,
            ],
            'violation'             => [
                'name'       => $penalty->violation?->name,
                'regulation' => $penalty->violation?->regulation?->name,
            ],
            'total_points_deducted' => $penalty->total_points_deducted,
            'total_money_deducted'  => (float) $penalty->total_money_deducted,
            'description'           => $penalty->description,
            'rejected_reason'       => $penalty->rejected_reason,
            'approved_at'           => $penalty->approved_at?->format('d/m/Y H:i'),
            'approver'              => $penalty->approver?->name,
            'created_at'            => $penalty->created_at->format('d/m/Y H:i'),
            'members'               => $penalty->members->map(fn($m) => [
                'employee_name'   => $m->employee?->name,
                'employee_code'   => $m->employee?->code,
                'points_deducted' => $m->points_deducted,
                'money_deducted'  => (float) $m->money_deducted,
                'note'            => $m->note,
            ]),
            'can_approve'           => $user->can('approve-penalties'),
            'can_edit'              => $user->can('create-penalties'),
            'violation_id'          => $penalty->violation_id,
            'employee_id'           => $penalty->employee_id,
            'attachments'           => $penalty->attachments->map(fn($a) => [
                'url'      => $a->url,
                'type'     => $a->type,
                'filename' => $a->filename,
                'size'     => $a->formatted_size,
                'path'     => $a->path,
            ]),
        ]);
    }

    public function update(UpdatePenaltyRequest $request, Penalty $penalty, AttachmentService $attachments)
    {
        abort_if($penalty->status !== 'pending', 403, 'Không thể chỉnh sửa phiếu phạt đã xử lý.');

        $penalty->update([
            'employee_id'           => $request->employee_id,
            'violation_id'          => $request->violation_id,
            'description'           => $request->description,
            'total_points_deducted' => $request->points_deducted,
            'total_money_deducted'  => $request->money_deducted ?? 0,
        ]);

        // Sync additional members: delete all, re-insert from request
        $penalty->members()->delete();
        if ($request->filled('members')) {
            foreach ($request->members as $m) {
                if (!empty($m['employee_id'])) {
                    PenaltyMember::create([
                        'penalty_id'      => $penalty->id,
                        'employee_id'     => $m['employee_id'],
                        'points_deducted' => $m['points_deducted'] ?? $request->points_deducted,
                        'money_deducted'  => $m['money_deducted'] ?? 0,
                        'note'            => $m['note'] ?? null,
                    ]);
                }
            }
        }

        // Delete removed attachments
        if ($request->filled('delete_attachment_ids')) {
            foreach ($request->delete_attachment_ids as $attId) {
                $attachments->deleteAttachment((int) $attId);
            }
        }

        // Upload new attachments
        if ($request->hasFile('attachments')) {
            $attachments->storeForPenalty($request->file('attachments'), $penalty->id);
        }

        $penalty->refresh()->loadMissing(['violation', 'employee']);
        $membersCount = $penalty->members()->count();
        activity()->causedBy(auth()->user())
            ->performedOn($penalty)
            ->inLog('penalty')
            ->withProperties([
                'code'            => $penalty->code,
                'employee_name'   => $penalty->employee?->name,
                'employee_code'   => $penalty->employee?->code,
                'violation'       => $penalty->violation?->name,
                'points_deducted' => $penalty->total_points_deducted,
                'money_deducted'  => (float) $penalty->total_money_deducted,
                'members_count'   => $membersCount,
            ])
            ->log('Cập nhật phiếu phạt ' . $penalty->code
                . ' — NV: ' . ($penalty->employee?->name ?? '—')
                . ' — Vi phạm: ' . ($penalty->violation?->name ?? '—'));

        return back()->with('success', 'Cập nhật phiếu phạt thành công!');
    }

    public function destroy(Penalty $penalty, AttachmentService $attachments)
    {
        abort_if($penalty->status !== 'pending', 403, 'Không thể xóa phiếu phạt đã xử lý.');

        $penalty->loadMissing(['violation', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($penalty)
            ->inLog('penalty')
            ->withProperties([
                'code'          => $penalty->code,
                'employee_name' => $penalty->employee?->name,
                'employee_code' => $penalty->employee?->code,
                'violation'     => $penalty->violation?->name,
                'points'        => $penalty->total_points_deducted,
            ])
            ->log('Xóa phiếu phạt ' . $penalty->code
                . ' — NV: ' . ($penalty->employee?->name ?? '—')
                . ' — Vi phạm: ' . ($penalty->violation?->name ?? '—'));

        $penaltyId = $penalty->id;
        $penalty->delete(); // cascade deletes attachment records

        $attachments->deleteForPenalty($penaltyId);

        return redirect()->route('penalties.index')
            ->with('success', 'Đã xóa phiếu phạt!');
    }

    public function approve(Penalty $penalty)
    {
        DB::transaction(function () use ($penalty) {
            // Re-fetch with row lock to prevent concurrent double-approval
            $penalty = Penalty::lockForUpdate()->findOrFail($penalty->id);
            abort_if($penalty->status !== 'pending', 403, 'Phiếu phạt không ở trạng thái chờ duyệt.');

            $penalty->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            if ($penalty->total_points_deducted > 0) {
                EmployeeScore::create([
                    'employee_id'    => $penalty->employee_id,
                    'points'         => -$penalty->total_points_deducted,
                    'reason'         => 'Xử phạt: ' . ($penalty->violation?->name ?? 'Vi phạm'),
                    'type'           => 'penalty',
                    'reference_type' => Penalty::class,
                    'reference_id'   => $penalty->id,
                ]);
                $this->applyMonthlyDeduction($penalty->employee_id, $penalty->total_points_deducted);
            }

            foreach ($penalty->members as $member) {
                if ($member->points_deducted > 0) {
                    EmployeeScore::create([
                        'employee_id'    => $member->employee_id,
                        'points'         => -$member->points_deducted,
                        'reason'         => 'Xử phạt liên đới: ' . ($penalty->violation?->name ?? 'Vi phạm'),
                        'type'           => 'penalty',
                        'reference_type' => Penalty::class,
                        'reference_id'   => $penalty->id,
                    ]);
                    $this->applyMonthlyDeduction($member->employee_id, $member->points_deducted);
                }
            }
        });

        $penalty->loadMissing(['violation', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($penalty)
            ->inLog('penalty')
            ->withProperties([
                'code'            => $penalty->code,
                'employee_name'   => $penalty->employee?->name,
                'employee_code'   => $penalty->employee?->code,
                'violation'       => $penalty->violation?->name,
                'points_deducted' => $penalty->total_points_deducted,
                'money_deducted'  => (float) $penalty->total_money_deducted,
                'approved_by'     => auth()->user()->name,
            ])
            ->log('Duyệt phiếu phạt ' . $penalty->code
                . ' — Trừ ' . $penalty->total_points_deducted . ' điểm'
                . ' — NV: ' . ($penalty->employee?->name ?? '—'));

        app(NotificationService::class)->notifyPenaltyApproved($penalty);

        return back()->with('success', 'Đã duyệt xử phạt!');
    }

    private function applyMonthlyDeduction(int $employeeId, int $points): void
    {
        MonthlyEmployeeScore::ensureExists($employeeId, now()->month, now()->year)->deduct($points);
    }

    public function reject(Request $request, Penalty $penalty)
    {
        abort_if($penalty->status !== 'pending', 403, 'Phiếu phạt không ở trạng thái chờ duyệt.');

        $request->validate(['rejected_reason' => 'required|string|max:500']);

        $penalty->update([
            'status'          => 'rejected',
            'rejected_reason' => $request->rejected_reason,
        ]);

        $penalty->loadMissing(['violation', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($penalty)
            ->inLog('penalty')
            ->withProperties([
                'code'          => $penalty->code,
                'employee_name' => $penalty->employee?->name,
                'employee_code' => $penalty->employee?->code,
                'violation'     => $penalty->violation?->name,
                'reason'        => $request->rejected_reason,
            ])
            ->log('Từ chối phiếu phạt ' . $penalty->code
                . ' — NV: ' . ($penalty->employee?->name ?? '—')
                . ' — Lý do: ' . \Illuminate\Support\Str::limit($request->rejected_reason, 60));

        app(NotificationService::class)->notifyPenaltyRejected($penalty, $request->rejected_reason);

        return back()->with('success', 'Đã từ chối xử phạt!');
    }
}
