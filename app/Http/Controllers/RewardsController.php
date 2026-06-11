<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRewardRequest;
use App\Http\Requests\UpdateRewardRequest;
use App\Models\Employee;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Reward;
use App\Models\RewardMember;
use App\Models\RewardType;
use Illuminate\Http\Request;

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
        $employees   = Employee::where('is_active', true)->with('branch')->orderBy('name')->get();

        $rewardTypeDefaults = $rewardTypes->mapWithKeys(fn($rt) => [
            $rt->id => ['points' => $rt->default_points],
        ]);

        return view('rewards.index', compact('rewards', 'rewardTypes', 'employees', 'rewardTypeDefaults'));
    }

    public function store(StoreRewardRequest $request)
    {
        $count = Reward::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->withTrashed()
            ->count() + 1;
        $code = 'RWD-' . now()->format('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $reward = Reward::create([
            'code'                 => $code,
            'reward_type_id'       => $request->reward_type_id,
            'employee_id'          => $request->employee_id,
            'description'          => $request->description,
            'total_points_awarded' => $request->total_points_awarded,
            'status'               => 'pending',
            'created_by'           => auth()->id(),
        ]);

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

        $reward->loadMissing(['rewardType', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($reward)
            ->inLog('reward')
            ->withProperties([
                'code'          => $reward->code,
                'employee_name' => $reward->employee?->name,
                'reward_type'   => $reward->rewardType?->name,
                'points'        => $reward->total_points_awarded,
            ])
            ->log('Tạo phiếu thưởng ' . $reward->code . ' — ' . ($reward->employee?->name ?? '—'));

        return redirect()->route('rewards.show', $reward)
            ->with('success', 'Tạo phiếu thưởng thành công!');
    }

    public function show(Reward $reward)
    {
        $reward->load(['employee.branch', 'rewardType', 'approver', 'members.employee', 'creator']);
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

    public function approve(Reward $reward)
    {
        abort_if($reward->status !== 'pending', 403, 'Phiếu thưởng không ở trạng thái chờ duyệt.');

        $reward->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Add points to primary employee
        if ($reward->total_points_awarded > 0) {
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

        // Add points to additional members
        foreach ($reward->members as $member) {
            if ($member->points_awarded > 0) {
                EmployeeScore::create([
                    'employee_id'    => $member->employee_id,
                    'points'         => $member->points_awarded,
                    'reason'         => 'Thưởng điểm liên đới: ' . ($reward->rewardType?->name ?? 'Khen thưởng'),
                    'type'           => 'reward',
                    'reference_type' => Reward::class,
                    'reference_id'   => $reward->id,
                ]);
                $this->applyMonthlyReward($member->employee_id, $member->points_awarded);
            }
        }

        $reward->loadMissing(['rewardType', 'employee']);
        activity()->causedBy(auth()->user())
            ->performedOn($reward)
            ->inLog('reward')
            ->withProperties([
                'code'          => $reward->code,
                'employee_name' => $reward->employee?->name,
                'reward_type'   => $reward->rewardType?->name,
                'points'        => $reward->total_points_awarded,
                'approved_by'   => auth()->user()->name,
            ])
            ->log('Duyệt phiếu thưởng ' . $reward->code
                . ' — Cộng ' . $reward->total_points_awarded . ' điểm'
                . ' — NV: ' . ($reward->employee?->name ?? '—'));

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
                'code'          => $reward->code,
                'employee_name' => $reward->employee?->name,
                'reason'        => $request->rejected_reason,
            ])
            ->log('Từ chối phiếu thưởng ' . $reward->code
                . ' — NV: ' . ($reward->employee?->name ?? '—')
                . ' — Lý do: ' . \Illuminate\Support\Str::limit($request->rejected_reason, 60));

        return back()->with('success', 'Đã từ chối phiếu thưởng!');
    }

    private function applyMonthlyReward(int $employeeId, int $points): void
    {
        try {
            MonthlyEmployeeScore::ensureExists($employeeId, now()->month, now()->year)->reward($points);
        } catch (\Throwable $e) {
            \Log::error('applyMonthlyReward failed', [
                'employee_id' => $employeeId,
                'points'      => $points,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
