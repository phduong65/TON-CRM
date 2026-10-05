<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresTimesheetConfirmationEnabled;
use App\Services\TimesheetConfirmationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TimesheetConfirmationController extends Controller
{
    use RequiresTimesheetConfirmationEnabled;

    public function __construct(private readonly TimesheetConfirmationService $service)
    {
    }

    public function index(Request $request)
    {
        $this->ensureFeatureEnabled();

        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $month = (int) ($request->query('month') ?? now()->month);
        $year  = (int) ($request->query('year') ?? now()->year);

        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d = Carbon::now()->subMonths($i);
            $monthOptions[] = ['month' => $d->month, 'year' => $d->year, 'label' => $d->translatedFormat('m/Y')];
        }

        $data = $this->service->summaryFor($employee, $month, $year);

        return view('timesheet-confirmation.index', array_merge($data, [
            'employee'     => $employee,
            'month'        => $month,
            'year'         => $year,
            'monthOptions' => $monthOptions,
        ]));
    }

    public function confirm(Request $request)
    {
        $this->ensureFeatureEnabled();

        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|between:2020,2100',
        ]);

        $this->service->confirm($employee, (int) $validated['month'], (int) $validated['year'], auth()->user(), isProxy: false);

        return back()->with('success', 'Đã xác nhận công tháng ' . $validated['month'] . '/' . $validated['year'] . '.');
    }

    public function unconfirm(Request $request)
    {
        $this->ensureFeatureEnabled();

        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|between:2020,2100',
        ]);

        $this->service->unconfirm($employee, (int) $validated['month'], (int) $validated['year']);

        return back()->with('success', 'Đã huỷ xác nhận công tháng ' . $validated['month'] . '/' . $validated['year'] . '.');
    }
}
