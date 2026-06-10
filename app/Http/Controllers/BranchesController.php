<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchesController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount('teams', 'employees')
            ->orderBy('name')
            ->paginate(15);
        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        return view('branches.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:branches',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        Branch::create($validated);

        return redirect()->route('branches.index')
            ->with('success', 'Chi nhánh đã được tạo!');
    }

    public function show(Branch $branch)
    {
        return redirect()->route('branches.index');
    }

    public function edit(Branch $branch)
    {
        return view('branches.form', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:branches,code,' . $branch->id,
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $branch->update($validated);

        return redirect()->route('branches.index')
            ->with('success', 'Chi nhánh đã được cập nhật!');
    }

    public function destroy(Branch $branch)
    {
        $branch->update(['is_active' => false]);

        return redirect()->route('branches.index')
            ->with('success', 'Chi nhánh đã được vô hiệu hóa!');
    }
}
