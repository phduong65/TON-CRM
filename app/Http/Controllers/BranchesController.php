<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchesController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::withCount('teams', 'employees')
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $branches = $query->paginate(15)->withQueryString();
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
