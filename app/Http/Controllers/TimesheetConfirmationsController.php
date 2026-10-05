<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresTimesheetConfirmationEnabled;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Team;
use App\Services\NotificationService;
use App\Services\TimesheetConfirmationService;
use Illuminate\Http\Request;

class TimesheetConfirmationsController extends Controller
{
    use RequiresTimesheetConfirmationEnabled;

    public function __construct(
        private readonly TimesheetConfirmationService $service,
        private readonly NotificationService $notifications,
    ) {
    }

    public function index(Request $request)
    {
        $this->ensureFeatureEnabled();

        $month = (int) ($request->query('month') ?? now()->month);
        $year  = (int) ($request->query('year') ?? now()->year);

        $query = Employee::with(['branch', 'team', 'user'])
            ->where('is_active', true)
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('team_id'), fn ($q) => $q->where('team_id', $request->team_id))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('id', $request->employee_id))
            ->with(['timesheetConfirmations' => fn ($q) => $q->where('month', $month)->where('year', $year)])
            ->orderBy('name');

        $employees = $query->paginate(20)->withQueryString();

        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d = now()->copy()->subMonths($i);
            $monthOptions[] = ['month' => $d->month, 'year' => $d->year, 'label' => $d->translatedFormat('m/Y')];
        }

        $branches     = Branch::where('is_active', true)->orderBy('name')->get();
        $teams        = Team::where('is_active', true)->orderBy('name')->get();
        $allEmployees = Employee::where('is_active', true)->orderBy('name')->get();

        return view('timesheet-confirmations.index', compact('employees', 'month', 'year', 'monthOptions', 'branches', 'teams', 'allEmployees'));
    }

    public function show(Employee $employee, Request $request)
    {
        $this->ensureFeatureEnabled();

        $month = (int) ($request->query('month') ?? now()->month);
        $year  = (int) ($request->query('year') ?? now()->year);

        $data = $this->service->summaryFor($employee, $month, $year);

        return view('timesheet-confirmations.show', array_merge($data, [
            'employee' => $employee,
            'month'    => $month,
            'year'     => $year,
        ]));
    }

    public function confirm(Employee $employee, Request $request)
    {
        $this->ensureFeatureEnabled();

        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|between:2020,2100',
        ]);

        $month = (int) $validated['month'];
        $year  = (int) $validated['year'];

        $this->service->confirm($employee, $month, $year, auth()->user(), isProxy: true);
        $this->notifications->notifyTimesheetConfirmedProxy($employee, $month, $year, auth()->user());

        return back()->with('success', "Đã xác nhận hộ công tháng {$month}/{$year} cho {$employee->name}.");
    }
}
