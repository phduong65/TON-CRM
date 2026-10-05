<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->whereNull('deleted_at')],
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $branch = Branch::create($validated);

        activity()->causedBy(auth()->user())
            ->performedOn($branch)
            ->inLog('branch')
            ->withProperties(['code' => $branch->code, 'name' => $branch->name])
            ->log('Tạo chi nhánh ' . $branch->name . ' (' . $branch->code . ')');

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
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branch->id)->whereNull('deleted_at')],
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $branch->update($validated);

        activity()->causedBy(auth()->user())
            ->performedOn($branch)
            ->inLog('branch')
            ->withProperties(['code' => $branch->code, 'name' => $branch->name])
            ->log('Cập nhật chi nhánh ' . $branch->name . ' (' . $branch->code . ')');

        return redirect()->route('branches.index')
            ->with('success', 'Chi nhánh đã được cập nhật!');
    }

    public function destroy(Branch $branch, Request $request)
    {
        if ($request->input('_delete_type') === 'permanent') {
            if ($branch->teams()->where('is_active', true)->exists() || $branch->employees()->where('is_active', true)->exists()) {
                return back()->with('error', 'Không thể xóa chi nhánh còn đội nhóm hoặc nhân viên đang hoạt động. Vui lòng chuyển hoặc vô hiệu hóa trước.');
            }

            DB::transaction(function () use ($branch) {
                activity()->causedBy(auth()->user())
                    ->performedOn($branch)
                    ->inLog('branch')
                    ->withProperties(['code' => $branch->code, 'name' => $branch->name])
                    ->log('Xóa chi nhánh ' . $branch->name . ' (' . $branch->code . ')');

                $branch->delete();
            });

            return redirect()->route('branches.index')
                ->with('success', 'Chi nhánh đã được xóa khỏi hệ thống!');
        }

        $branch->update(['is_active' => false]);

        activity()->causedBy(auth()->user())
            ->performedOn($branch)
            ->inLog('branch')
            ->withProperties(['code' => $branch->code, 'name' => $branch->name])
            ->log('Vô hiệu hóa chi nhánh ' . $branch->name . ' (' . $branch->code . ')');

        return redirect()->route('branches.index')
            ->with('success', 'Chi nhánh đã được vô hiệu hóa!');
    }
}
