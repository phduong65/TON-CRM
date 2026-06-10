<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Branch;
use Illuminate\Http\Request;

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
            'code' => 'required|string|max:50|unique:teams',
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        Team::create($validated);

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
            'code' => 'required|string|max:50|unique:teams,code,' . $team->id,
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $team->update($validated);

        return redirect()->route('teams.index')
            ->with('success', 'Đội nhóm đã được cập nhật!');
    }

    public function destroy(Team $team)
    {
        $team->update(['is_active' => false]);

        return redirect()->route('teams.index')
            ->with('success', 'Đội nhóm đã được vô hiệu hóa!');
    }
}
