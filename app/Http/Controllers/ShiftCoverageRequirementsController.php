<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\ShiftCoverageRequirement;
use App\Models\Team;
use App\Services\ShiftCoverageService;
use Illuminate\Http\Request;

class ShiftCoverageRequirementsController extends Controller
{
    public function __construct(protected ShiftCoverageService $coverageService)
    {
    }

    public function index(Request $request)
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $selectedBranchId = $request->filled('branch_id')
            ? (int) $request->branch_id
            : ($branches->first()?->id ?? 0);

        $teams = Team::where('branch_id', $selectedBranchId)->where('is_active', true)->orderBy('name')->get();
        $selectedTeamId = $request->filled('team_id') ? (int) $request->team_id : null;

        $shifts = Shift::where(function ($q) use ($selectedBranchId) {
            $q->whereNull('branch_id')->orWhere('branch_id', $selectedBranchId);
        })->where('is_active', true)->orderBy('name')->get();

        $query = ShiftCoverageRequirement::with(['branch', 'team', 'shift', 'createdBy'])
            ->where('branch_id', $selectedBranchId);

        if ($selectedTeamId) {
            $query->where('team_id', $selectedTeamId);
        }

        $requirements = $query->orderBy('team_id')
            ->orderBy('start_time')
            ->paginate(20)
            ->withQueryString();

        return view('shift-coverage-requirements.index', compact(
            'requirements',
            'branches',
            'teams',
            'shifts',
            'selectedBranchId',
            'selectedTeamId'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id'       => 'required|exists:branches,id',
            'team_id'         => 'required|exists:teams,id',
            'name'            => 'required|string|max:100',
            'days_of_week'    => 'required|array|min:1',
            'days_of_week.*'  => 'integer|between:1,7',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i',
            'minimum_staff'   => 'required|integer|min:0',
            'target_staff'    => 'nullable|integer|gte:minimum_staff',
            'effective_from'  => 'required|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'shift_id'        => 'nullable|exists:shifts,id',
            'is_active'       => 'boolean',
        ]);

        $overlapError = $this->coverageService->checkRequirementOverlap($validated);
        if ($overlapError) {
            return back()->withInput()->withErrors(['overlap' => $overlapError]);
        }

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->boolean('is_active', true);

        $requirement = ShiftCoverageRequirement::create($validated);

        activity()->causedBy(auth()->user())
            ->performedOn($requirement)
            ->inLog('shift_coverage')
            ->log("Thêm quy tắc định biên: {$requirement->name}");

        return redirect()->route('shift-coverage-requirements.index', [
            'branch_id' => $requirement->branch_id,
            'team_id'   => $requirement->team_id,
        ])->with('success', 'Đã tạo quy tắc định biên thành công!');
    }

    public function update(Request $request, ShiftCoverageRequirement $shiftCoverageRequirement)
    {
        $validated = $request->validate([
            'branch_id'       => 'required|exists:branches,id',
            'team_id'         => 'required|exists:teams,id',
            'name'            => 'required|string|max:100',
            'days_of_week'    => 'required|array|min:1',
            'days_of_week.*'  => 'integer|between:1,7',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i',
            'minimum_staff'   => 'required|integer|min:0',
            'target_staff'    => 'nullable|integer|gte:minimum_staff',
            'effective_from'  => 'required|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'shift_id'        => 'nullable|exists:shifts,id',
            'is_active'       => 'boolean',
        ]);

        $overlapError = $this->coverageService->checkRequirementOverlap($validated, $shiftCoverageRequirement->id);
        if ($overlapError) {
            return back()->withInput()->withErrors(['overlap' => $overlapError]);
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $shiftCoverageRequirement->update($validated);

        activity()->causedBy(auth()->user())
            ->performedOn($shiftCoverageRequirement)
            ->inLog('shift_coverage')
            ->log("Cập nhật quy tắc định biên: {$shiftCoverageRequirement->name}");

        return redirect()->route('shift-coverage-requirements.index', [
            'branch_id' => $shiftCoverageRequirement->branch_id,
            'team_id'   => $shiftCoverageRequirement->team_id,
        ])->with('success', 'Đã cập nhật quy tắc định biên!');
    }

    public function destroy(ShiftCoverageRequirement $shiftCoverageRequirement)
    {
        $name = $shiftCoverageRequirement->name;
        $branchId = $shiftCoverageRequirement->branch_id;
        $teamId = $shiftCoverageRequirement->team_id;

        $shiftCoverageRequirement->delete();

        activity()->causedBy(auth()->user())
            ->inLog('shift_coverage')
            ->log("Xoá quy tắc định biên: {$name}");

        return redirect()->route('shift-coverage-requirements.index', [
            'branch_id' => $branchId,
            'team_id'   => $teamId,
        ])->with('success', "Đã xoá quy tắc định biên \"{$name}\"!");
    }
}
