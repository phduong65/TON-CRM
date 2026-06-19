<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Branch;
use App\Models\Team;
use App\Models\User;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeesController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['branch', 'team'])
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $employees = $query->paginate(15)->withQueryString();
        $branches  = Branch::orderBy('name')->get();
        $teams     = Team::orderBy('name')->get();
        return view('employees.index', compact('employees', 'branches', 'teams'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->get();
        $teams = Team::orderBy('name')->get();
        return view('employees.form', compact('branches', 'teams'));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $plainPassword = Str::random(10);

        $defaultPoints = (int) Setting::getValue('default_points', 100);

        $employee = DB::transaction(function () use ($request, $plainPassword, $defaultPoints) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $plainPassword,
            ]);

            if (\Spatie\Permission\Models\Role::where('name', 'staff')->exists()) {
                $user->assignRole('staff');
            }

            $employee = Employee::create(array_merge(
                $request->validated(),
                ['user_id' => $user->id]
            ));

            $employee->scores()->create([
                'points' => $defaultPoints,
                'reason' => 'Điểm khởi điểm',
                'type'   => 'adjustment',
            ]);

            return $employee;
        });

        $employee->loadMissing(['branch', 'team']);
        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->inLog('employee')
            ->withProperties([
                'code'     => $employee->code,
                'name'     => $employee->name,
                'email'    => $employee->email,
                'position' => $employee->position,
                'branch'   => $employee->branch?->name,
                'team'     => $employee->team?->name,
            ])
            ->log('Tạo nhân viên ' . $employee->name . ' (' . $employee->code . ')');

        return redirect()->route('employees.show', $employee)
            ->with('new_account', [
                'name'     => $employee->name,
                'code'     => $employee->code,
                'email'    => $employee->email,
                'password' => $plainPassword,
            ]);
    }

    public function show(Employee $employee)
    {
        $user = auth()->user();
        $canViewSensitive = $user->hasRole(['admin', 'manager'])
            || $user->employee?->id === $employee->id;

        $employee->load(['branch', 'team']);
        if ($canViewSensitive) {
            $employee->load(['scores', 'penalties' => function ($q) {
                $q->with('violation')->latest();
            }]);
        }
        return view('employees.show', compact('employee', 'canViewSensitive'));
    }

    public function edit(Employee $employee)
    {
        $branches = Branch::orderBy('name')->get();
        $teams = Team::orderBy('name')->get();
        return view('employees.form', compact('employee', 'branches', 'teams'));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());
        $employee->refresh()->loadMissing(['branch', 'team']);

        $employee->user?->update(['status' => $employee->is_active ? 'active' : 'inactive']);
        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->inLog('employee')
            ->withProperties([
                'code'     => $employee->code,
                'name'     => $employee->name,
                'position' => $employee->position,
                'branch'   => $employee->branch?->name,
                'team'     => $employee->team?->name,
            ])
            ->log('Cập nhật nhân viên ' . $employee->name . ' (' . $employee->code . ')');

        return redirect()->route('employees.index')
            ->with('success', 'Thông tin nhân viên đã được cập nhật!');
    }

    public function destroy(Employee $employee, Request $request)
    {
        $employee->loadMissing(['branch', 'user']);

        if ($request->input('_delete_type') === 'permanent') {
            DB::transaction(function () use ($employee) {
                $user = $employee->user;
                activity()
                    ->performedOn($employee)
                    ->causedBy(auth()->user())
                    ->inLog('employee')
                    ->withProperties([
                        'code'   => $employee->code,
                        'name'   => $employee->name,
                        'branch' => $employee->branch?->name,
                    ])
                    ->log('Xóa nhân viên ' . $employee->name . ' (' . $employee->code . ')');

                $employee->delete();
                $user?->delete();
            });

            return redirect()->route('employees.index')
                ->with('success', 'Nhân viên đã được xóa khỏi hệ thống!');
        }

        // Default: mark as resigned
        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->inLog('employee')
            ->withProperties([
                'code'   => $employee->code,
                'name'   => $employee->name,
                'branch' => $employee->branch?->name,
            ])
            ->log('Đánh dấu nghỉ việc nhân viên ' . $employee->name . ' (' . $employee->code . ')');

        $employee->update(['is_active' => false]);
        $employee->user?->update(['status' => 'inactive']);

        return redirect()->route('employees.index')
            ->with('success', 'Nhân viên đã được đánh dấu nghỉ việc!');
    }

    public function penalties(Employee $employee)
    {
        $user = auth()->user();
        $isOwnProfile = $user->employee?->id === $employee->id;
        $isPrivileged = $user->hasRole(['admin', 'manager']);

        if (!$isOwnProfile && !$isPrivileged) {
            abort(403, 'Bạn không có quyền xem lịch sử vi phạm của nhân viên khác.');
        }

        $penalties = $employee->penalties()
            ->with(['violation', 'approver'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        return view('employees.penalties', compact('employee', 'penalties'));
    }
}
