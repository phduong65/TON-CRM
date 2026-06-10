<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Branch;
use App\Models\Team;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use Illuminate\Http\Request;

class EmployeesController extends Controller
{
    public function index()
    {
        $employees = Employee::with(['branch', 'team'])
            ->orderBy('name')
            ->paginate(15);
        $branches = Branch::orderBy('name')->get();
        $teams    = Team::orderBy('name')->get();
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
        $employee = Employee::create($request->validated());

        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->log('created_employee');

        return redirect()->route('employees.index')
            ->with('success', 'Nhân viên ' . $employee->name . ' đã được thêm!');
    }

    public function show(Employee $employee)
    {
        $employee->load(['branch', 'team', 'scores', 'penalties' => function($q) {
            $q->with('violation')->latest();
        }]);
        return view('employees.show', compact('employee'));
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

        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->log('updated_employee');

        return redirect()->route('employees.index')
            ->with('success', 'Thông tin nhân viên đã được cập nhật!');
    }

    public function destroy(Employee $employee)
    {
        $employee->update(['is_active' => false]);

        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->log('deactivated_employee');

        return redirect()->route('employees.index')
            ->with('success', 'Nhân viên đã được vô hiệu hóa!');
    }

    public function penalties(Employee $employee)
    {
        $penalties = $employee->penalties()
            ->with(['violation', 'approver'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        return view('employees.penalties', compact('employee', 'penalties'));
    }
}
