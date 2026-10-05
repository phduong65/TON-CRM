<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffRequestController extends Controller
{
    private const TYPE_PREFIXES = [
        'attendance_correction' => 'ATC',
        'business_trip'         => 'BTR',
        'late_early'            => 'LE',
        'time_change'           => 'TC',
        'overtime'              => 'OT',
    ];

    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $query = StaffRequest::with('reviewer')
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $requests = $query->paginate(15);
        $requests->getCollection()->transform(fn(StaffRequest $r) => $this->format($r));

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $type = $request->input('type');

        $rules = [
            'type'      => ['required', Rule::in(array_keys(self::TYPE_PREFIXES))],
            'work_date' => 'required|date',
            'reason'    => 'required|string|max:1000',
        ];

        $rules = array_merge($rules, match ($type) {
            'attendance_correction' => [
                'check_in_at'  => 'nullable|required_without:check_out_at|date_format:H:i',
                'check_out_at' => 'nullable|required_without:check_in_at|date_format:H:i',
            ],
            'business_trip' => [
                'from_time' => 'required|date_format:H:i',
                'to_time'   => 'required|date_format:H:i|after:from_time',
                'location'  => 'required|string|max:255',
            ],
            'late_early' => [
                'mode'    => 'required|in:late,early',
                'minutes' => 'required|integer|min:1|max:480',
            ],
            'time_change' => [
                'new_check_in'  => 'required|date_format:H:i',
                'new_check_out' => 'required|date_format:H:i|after:new_check_in',
            ],
            'overtime' => [
                'ot_from_time' => 'required|date_format:H:i',
                'ot_to_time'   => 'required|date_format:H:i|different:ot_from_time',
            ],
            default => [],
        });

        $validated = $request->validate($rules);

        $payload = match ($type) {
            'attendance_correction' => array_filter([
                'check_in_at'  => $validated['check_in_at'] ?? null,
                'check_out_at' => $validated['check_out_at'] ?? null,
            ]),
            'business_trip' => [
                'from_time' => $validated['from_time'],
                'to_time'   => $validated['to_time'],
                'location'  => $validated['location'],
            ],
            'late_early' => [
                'mode'    => $validated['mode'],
                'minutes' => (int) $validated['minutes'],
            ],
            'time_change' => [
                'new_check_in'  => $validated['new_check_in'],
                'new_check_out' => $validated['new_check_out'],
            ],
            'overtime' => [
                'from_time' => $validated['ot_from_time'],
                'to_time'   => $validated['ot_to_time'],
            ],
        };

        $count = StaffRequest::where('type', $type)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        $code = self::TYPE_PREFIXES[$type] . '-' . now()->format('Ym') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $staffRequest = StaffRequest::create([
            'code'        => $code,
            'employee_id' => $employee->id,
            'type'        => $type,
            'work_date'   => $validated['work_date'],
            'payload'     => $payload,
            'reason'      => $validated['reason'],
            'status'      => 'pending',
        ]);

        activity()->causedBy($request->user())
            ->performedOn($staffRequest)
            ->inLog('staff_request')
            ->withProperties(['code' => $code, 'type' => $type, 'employee_name' => $employee->name, 'source' => 'mobile'])
            ->log("Gửi yêu cầu {$staffRequest->typeLabel()} {$code} — {$employee->name}");

        app(NotificationService::class)->notifyStaffRequestCreated($staffRequest);

        return response()->json($this->format($staffRequest), 201);
    }

    public function destroy(Request $request, StaffRequest $staffRequest)
    {
        abort_unless($staffRequest->employee_id === $request->user()->employee?->id, 403, 'Bạn chỉ có thể huỷ yêu cầu của chính mình.');
        abort_if($staffRequest->status !== 'pending', 403, 'Chỉ có thể huỷ yêu cầu đang chờ duyệt.');

        $staffRequest->delete();

        return response()->json(['message' => 'Đã huỷ yêu cầu.']);
    }

    private function format(StaffRequest $r): array
    {
        return [
            'id'               => $r->id,
            'code'             => $r->code,
            'type'             => $r->type,
            'type_label'       => $r->typeLabel(),
            'work_date'        => $r->work_date->toDateString(),
            'summary'          => $r->summary(),
            'reason'           => $r->reason,
            'status'           => $r->status,
            'status_label'     => $r->statusLabel(),
            'rejection_reason' => $r->rejection_reason,
            'created_at'       => $r->created_at->toIso8601String(),
        ];
    }
}
