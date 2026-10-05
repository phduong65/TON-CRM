<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamsController extends Controller
{
    public function index(Request $request)
    {
        $query = Team::with('branch')
            ->withCount('employees')
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $teams    = $query->paginate(15)->withQueryString();
        $branches = Branch::orderBy('name')->get();
        return view('teams.index', compact('teams', 'branches'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->get();
        return view('teams.form', compact('branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('teams', 'code')->whereNull('deleted_at')],
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'description' => 'nullable|string|max:500',
            'is_office' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['is_office'] = $request->boolean('is_office');

        $team = Team::create($validated);

        activity()->causedBy(auth()->user())
            ->performedOn($team)
            ->inLog('team')
            ->withProperties(['code' => $team->code, 'name' => $team->name])
            ->log('Tạo đội nhóm ' . $team->name . ' (' . $team->code . ')');

        return redirect()->route('teams.index')
            ->with('success', 'Đội nhóm đã được tạo!');
    }

    public function show(Team $team)
    {
        return redirect()->route('teams.index');
    }

    public function edit(Team $team)
    {
        $branches = Branch::orderBy('name')->get();
        return view('teams.form', compact('team', 'branches'));
    }

    public function update(Request $request, Team $team)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('teams', 'code')->ignore($team->id)->whereNull('deleted_at')],
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'description' => 'nullable|string|max:500',
            'is_office' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['is_office'] = $request->boolean('is_office');

        $team->update($validated);

        activity()->causedBy(auth()->user())
            ->performedOn($team)
            ->inLog('team')
            ->withProperties(['code' => $team->code, 'name' => $team->name])
            ->log('Cập nhật đội nhóm ' . $team->name . ' (' . $team->code . ')');

        return redirect()->route('teams.index')
            ->with('success', 'Đội nhóm đã được cập nhật!');
    }

    public function destroy(Team $team, Request $request)
    {
        if ($request->input('_delete_type') === 'permanent') {
            if ($team->employees()->where('is_active', true)->exists()) {
                return back()->with('error', 'Không thể xóa đội nhóm còn nhân viên đang hoạt động. Vui lòng chuyển nhân viên sang đội khác trước.');
            }

            DB::transaction(function () use ($team) {
                activity()->causedBy(auth()->user())
                    ->performedOn($team)
                    ->inLog('team')
                    ->withProperties(['code' => $team->code, 'name' => $team->name])
                    ->log('Xóa đội nhóm ' . $team->name . ' (' . $team->code . ')');

                $team->delete();
            });

            return redirect()->route('teams.index')
                ->with('success', 'Đội nhóm đã được xóa khỏi hệ thống!');
        }

        $team->update(['is_active' => false]);

        activity()->causedBy(auth()->user())
            ->performedOn($team)
            ->inLog('team')
            ->withProperties(['code' => $team->code, 'name' => $team->name])
            ->log('Vô hiệu hóa đội nhóm ' . $team->name . ' (' . $team->code . ')');

        return redirect()->route('teams.index')
            ->with('success', 'Đội nhóm đã được vô hiệu hóa!');
    }
}
