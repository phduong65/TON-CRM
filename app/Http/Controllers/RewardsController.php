<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRewardRequest;
use App\Http\Requests\UpdateRewardRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Reward;
use App\Models\RewardMember;
use App\Models\RewardType;
use App\Models\Team;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardsController extends Controller
{
    public function index(Request $request)
    {
        $query = Reward::with(['employee.branch', 'rewardType', 'approver', 'members'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', "%$s%")
                    ->orWhereHas('employee', fn($eq) => $eq->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('reward_type_id')) {
            $query->where('reward_type_id', $request->reward_type_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $rewards = $query->paginate(15)->withQueryString();

        $rewardTypes = RewardType::active()->orderBy('name')->get();
        $employees   = Employee::where('is_active', true)->with(['branch', 'team'])->orderBy('name')->get();
        $branches    = Branch::where('is_active', true)->orderBy('name')->get();
        $teams       = Team::where('is_active', true)->orderBy('name')->get();

        $rewardTypeDefaults = $rewardTypes->mapWithKeys(fn($rt) => [
            $rt->id => ['points' => $rt->default_points],
        ]);

        return view('rewards.index', compact('rewards', 'rewardTypes', 'employees', 'branches', 'teams', 'rewardTypeDefaults'));
    }

    public function store(StoreRewardRequest $request)
    {
        $count = Reward::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->withTrashed()
            ->count() + 1;
        $code = 'RWD-' . now()->format('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $targetType = $request->input('target_type', 'individual');

        $reward = Reward::create([
            'code'                 => $code,
            'target_type'          => $targetType,
            'target_id'            => in_array($targetType, ['branch', 'team']) ? $request->target_id : null,
            'reward_type_id'       => $request->reward_type_id,
            'employee_id'          => $targetType === 'individual' ? $request->employee_id : null,
            'description'          => $request->description,
            'total_points_awarded' => $request->total_points_awarded,
            'status'               => 'pending',
            'created_by'           => auth()->id(),
        ]);

        if ($targetType !== 'individual') {
            // Bulk: resolve target employees and create reward_members
            $this->createBulkMembers($reward, $targetType, $request->target_id, $request->total_points_awarded);
        } elseif ($request->filled('members')) {
            foreach ($request->members as $m) {
                if (!empty($m['employee_id'])) {
                    RewardMember::create([
                        'reward_id'      => $reward->id,
                        'employee_id'    => $m['employee_id'],
                        'points_awarded' => $m['points_awarded'] ?? $request->total_points_awarded,
                        'note'           => $m['note'] ?? null,
                    ]);
                }
            }
        }
        // individual with no extra members → chỉ dùng employee_id trên reward

        $reward->loadMissing(['rewardType', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($reward)
            ->inLog('reward')
            ->withProperties([
                'code'        => $reward->code,
                'target_type' => $targetType,
                'target_id'   => $reward->target_id,
                'reward_type' => $reward->rewardType?->name,
                'points'      => $reward->total_points_awarded,
            ])
            ->log('Tạo phiếu thưởng ' . $reward->code . ' — ' . $this->targetLabel($reward));

        app(NotificationService::class)->notifyRewardCreated($reward);

        return redirect()->route('rewards.show', $reward)
            ->with('success', 'Tạo phiếu thưởng thành công!');
    }

    public function show(Reward $reward)
    {
        $reward->load(['employee.branch', 'rewardType', 'approver', 'members.employee', 'creator', 'revoker']);
        return view('rewards.show', compact('reward'));
    }

    public function update(UpdateRewardRequest $request, Reward $reward)
    {
        abort_if($reward->status !== 'pending', 403, 'Không thể chỉnh sửa phiếu thưởng đã xử lý.');

        $reward->update([
            'reward_type_id'       => $request->reward_type_id,
            'employee_id'          => $request->employee_id,
            'description'          => $request->description,
            'total_points_awarded' => $request->total_points_awarded,
        ]);

        $reward->members()->delete();
        if ($request->filled('members')) {
            foreach ($request->members as $m) {
                if (!empty($m['employee_id'])) {
                    RewardMember::create([
                        'reward_id'      => $reward->id,
                        'employee_id'    => $m['employee_id'],
                        'points_awarded' => $m['points_awarded'] ?? $request->total_points_awarded,
                        'note'           => $m['note'] ?? null,
                    ]);
                }
            }
        }

        $reward->refresh()->loadMissing(['rewardType', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($reward)
            ->inLog('reward')
            ->withProperties([
                'code'          => $reward->code,
                'employee_name' => $reward->employee?->name,
                'reward_type'   => $reward->rewardType?->name,
                'points'        => $reward->total_points_awarded,
            ])
            ->log('Cập nhật phiếu thưởng ' . $reward->code . ' — ' . ($reward->employee?->name ?? '—'));

        return back()->with('success', 'Cập nhật phiếu thưởng thành công!');
    }

    public function destroy(Reward $reward)
    {
        abort_if($reward->status !== 'pending', 403, 'Không thể xóa phiếu thưởng đã xử lý.');

        $reward->loadMissing(['rewardType', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($reward)
            ->inLog('reward')
            ->withProperties([
                'code'          => $reward->code,
                'employee_name' => $reward->employee?->name,
                'points'        => $reward->total_points_awarded,
            ])
            ->log('Xóa phiếu thưởng ' . $reward->code . ' — ' . ($reward->employee?->name ?? '—'));

        $reward->delete();

        return redirect()->route('rewards.index')
            ->with('success', 'Đã xóa phiếu thưởng!');
    }

    public function detailJson(Reward $reward)
    {
        $reward->load(['employee.branch', 'rewardType', 'approver', 'members.employee']);
        $user = auth()->user();

        return response()->json([
            'id'                   => $reward->id,
            'code'                 => $reward->code,
            'status'               => $reward->status,
            'status_label'         => match ($reward->status) {
                'pending'  => 'Chờ duyệt',
                'approved' => 'Đã duyệt',
                'rejected' => 'Từ chối',
                'revoked'  => 'Đã thu hồi',
                default    => $reward->status,
            },
            'target_type'          => $reward->target_type,
            'target_label'         => $this->targetLabel($reward),
            'employee'             => [
                'id'     => $reward->employee?->id,
                'name'   => $reward->employee?->name,
                'code'   => $reward->employee?->code,
                'branch' => $reward->employee?->branch?->name,
            ],
            'reward_type'          => $reward->rewardType?->name,
            'total_points_awarded' => $reward->total_points_awarded,
            'description'          => $reward->description,
            'rejected_reason'      => $reward->rejected_reason,
            'approved_at'          => $reward->approved_at?->format('d/m/Y H:i'),
            'approver'             => $reward->approver?->name,
            'created_at'           => $reward->created_at->format('d/m/Y H:i'),
            'members'              => $reward->members->map(fn($m) => [
                'employee_name'  => $m->employee?->name,
                'employee_code'  => $m->employee?->code,
                'points_awarded' => $m->points_awarded,
                'note'           => $m->note,
            ]),
            'can_approve'          => $user->can('approve-rewards'),
            'can_edit'             => $user->can('create-rewards'),
            'can_revoke'           => $user->can('revoke-rewards'),
            'employee_id'          => $reward->employee_id,
            'reward_type_id'       => $reward->reward_type_id,
        ]);
    }

    public function approve(Reward $reward)
    {
        DB::transaction(function () use ($reward) {
            $reward = Reward::lockForUpdate()->findOrFail($reward->id);
            abort_if($reward->status !== 'pending', 403, 'Phiếu thưởng không ở trạng thái chờ duyệt.');

            $reward->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            if ($reward->employee_id && $reward->total_points_awarded > 0) {
                EmployeeScore::create([
                    'employee_id'    => $reward->employee_id,
                    'points'         => $reward->total_points_awarded,
                    'reason'         => 'Thưởng điểm: ' . ($reward->rewardType?->name ?? 'Khen thưởng'),
                    'type'           => 'reward',
                    'reference_type' => Reward::class,
                    'reference_id'   => $reward->id,
                ]);
                $this->applyMonthlyReward($reward->employee_id, $reward->total_points_awarded);
            }

            foreach ($reward->members as $member) {
                if ($member->points_awarded > 0) {
                    EmployeeScore::create([
                        'employee_id'    => $member->employee_id,
                        'points'         => $member->points_awarded,
                        'reason'         => 'Thưởng điểm: ' . ($reward->rewardType?->name ?? 'Khen thưởng'),
                        'type'           => 'reward',
                        'reference_type' => Reward::class,
                        'reference_id'   => $reward->id,
                    ]);
                    $this->applyMonthlyReward($member->employee_id, $member->points_awarded);
                }
            }
        });

        $reward->loadMissing(['rewardType', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($reward)
            ->inLog('reward')
            ->withProperties([
                'code'        => $reward->code,
                'target'      => $this->targetLabel($reward),
                'reward_type' => $reward->rewardType?->name,
                'points'      => $reward->total_points_awarded,
                'approved_by' => auth()->user()->name,
            ])
            ->log('Duyệt phiếu thưởng ' . $reward->code
                . ' — Cộng ' . $reward->total_points_awarded . ' điểm'
                . ' — ' . $this->targetLabel($reward));

        app(NotificationService::class)->notifyRewardApproved($reward);

        return back()->with('success', 'Đã duyệt phiếu thưởng!');
    }

    public function reject(Request $request, Reward $reward)
    {
        abort_if($reward->status !== 'pending', 403, 'Phiếu thưởng không ở trạng thái chờ duyệt.');

        $request->validate(['rejected_reason' => 'required|string|max:500']);

        $reward->update([
            'status'          => 'rejected',
            'rejected_reason' => $request->rejected_reason,
        ]);

        $reward->loadMissing(['rewardType', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($reward)
            ->inLog('reward')
            ->withProperties([
                'code'   => $reward->code,
                'target' => $this->targetLabel($reward),
                'reason' => $request->rejected_reason,
            ])
            ->log('Từ chối phiếu thưởng ' . $reward->code
                . ' — ' . $this->targetLabel($reward)
                . ' — Lý do: ' . \Illuminate\Support\Str::limit($request->rejected_reason, 60));

        app(NotificationService::class)->notifyRewardRejected($reward, $request->rejected_reason);

        return back()->with('success', 'Đã từ chối phiếu thưởng!');
    }

    public function revoke(Request $request, Reward $reward)
    {
        abort_if($reward->status !== 'approved', 403, 'Chỉ có thể thu hồi phiếu thưởng đã duyệt.');

        $request->validate(['revoked_reason' => 'required|string|max:500']);

        DB::transaction(function () use ($request, $reward) {
            $reward = Reward::lockForUpdate()->findOrFail($reward->id);
            abort_if($reward->status !== 'approved', 403, 'Chỉ có thể thu hồi phiếu thưởng đã duyệt.');

            $reward->update([
                'status'        => 'revoked',
                'revoked_by'    => auth()->id(),
                'revoked_at'    => now(),
                'revoked_reason' => $request->revoked_reason,
            ]);

            $approvedMonth = $reward->approved_at->month;
            $approvedYear  = $reward->approved_at->year;

            // Reverse main employee reward
            if ($reward->employee_id && $reward->total_points_awarded > 0) {
                EmployeeScore::create([
                    'employee_id'    => $reward->employee_id,
                    'points'         => -$reward->total_points_awarded,
                    'reason'         => 'Thu hồi thưởng: ' . ($reward->rewardType?->name ?? 'Khen thưởng') . ' (' . $reward->code . ')',
                    'type'           => 'adjustment',
                    'reference_type' => Reward::class,
                    'reference_id'   => $reward->id,
                ]);
                $this->reverseMonthlyReward($reward->employee_id, $reward->total_points_awarded, $approvedMonth, $approvedYear);
            }

            // Reverse all member rewards
            foreach ($reward->members as $member) {
                if ($member->points_awarded > 0) {
                    EmployeeScore::create([
                        'employee_id'    => $member->employee_id,
                        'points'         => -$member->points_awarded,
                        'reason'         => 'Thu hồi thưởng: ' . ($reward->rewardType?->name ?? 'Khen thưởng') . ' (' . $reward->code . ')',
                        'type'           => 'adjustment',
                        'reference_type' => Reward::class,
                        'reference_id'   => $reward->id,
                    ]);
                    $this->reverseMonthlyReward($member->employee_id, $member->points_awarded, $approvedMonth, $approvedYear);
                }
            }
        });

        $reward->loadMissing(['rewardType', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($reward)
            ->inLog('reward')
            ->withProperties([
                'code'        => $reward->code,
                'target'      => $this->targetLabel($reward),
                'points'      => $reward->total_points_awarded,
                'revoked_by'  => auth()->user()->name,
                'reason'      => $request->revoked_reason,
            ])
            ->log('Thu hồi phiếu thưởng ' . $reward->code
                . ' — Hoàn ' . $reward->total_points_awarded . ' điểm'
                . ' — ' . $this->targetLabel($reward));

        return back()->with('success', 'Đã thu hồi phiếu thưởng và hoàn điểm!');
    }

    // -----------------------------------------------------------------------

    private function applyMonthlyReward(int $employeeId, int $points): void
    {
        MonthlyEmployeeScore::ensureExists($employeeId, now()->month, now()->year)->reward($points);
    }

    private function reverseMonthlyReward(int $employeeId, int $points, int $month, int $year): void
    {
        $record = MonthlyEmployeeScore::where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($record) {
            $record->revokeReward($points);
        }
    }

    private function createBulkMembers(Reward $reward, string $targetType, ?int $targetId, int $points): void
    {
        $query = Employee::where('is_active', true);

        if ($targetType === 'branch' && $targetId) {
            $query->where('branch_id', $targetId);
        } elseif ($targetType === 'team' && $targetId) {
            $query->where('team_id', $targetId);
        }
        // 'all' → no extra filter

        $query->select('id')->each(function ($employee) use ($reward, $points) {
            RewardMember::create([
                'reward_id'      => $reward->id,
                'employee_id'    => $employee->id,
                'points_awarded' => $points,
                'note'           => null,
            ]);
        });
    }

    private function targetLabel(Reward $reward): string
    {
        return match ($reward->target_type) {
            'all'    => 'Tất cả nhân viên',
            'branch' => 'Chi nhánh #' . $reward->target_id,
            'team'   => 'Đội nhóm #' . $reward->target_id,
            default  => $reward->employee?->name ?? '—',
        };
    }
}
