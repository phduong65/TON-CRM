<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLocation;
use App\Models\AttendanceLog;
use App\Models\ShiftSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index()
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $today = now()->toDateString();

        $shiftSchedules = ShiftSchedule::with(['shift', 'attendanceLog'])
            ->where('employee_id', $employee->id)
            ->where('work_date', $today)
            ->where('status', 'scheduled')
            ->orderBy('id')
            ->get();

        // Chỉ cần khi không có ca nào hôm nay — hiển thị lại trạng thái chấm công "ca ngoài lịch" đã lỡ thực hiện.
        $unscheduledLog = $shiftSchedules->isEmpty()
            ? AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $today)
                ->whereNull('shift_schedule_id')
                ->first()
            : null;

        return view('attendance.index', compact('employee', 'shiftSchedules', 'unscheduledLog'));
    }

    /**
     * Xác định ca cần chấm công hôm nay.
     * - Nếu client gửi shift_schedule_id: dùng đúng ca đó (phải thuộc về nhân viên & đúng ngày hôm nay).
     * - Nếu không gửi: chỉ tự suy ra khi nhân viên có 0 hoặc đúng 1 ca hôm nay (tương thích ngược).
     *   Có ≥2 ca mà không chỉ định rõ thì bắt buộc client phải chọn.
     *
     * @return array{0: ?ShiftSchedule, 1: ?string} [shiftSchedule, error]
     */
    private function resolveShiftScheduleForCheck(\App\Models\Employee $employee, string $today, ?int $requestedId): array
    {
        if ($requestedId) {
            $schedule = ShiftSchedule::with('shift')
                ->where('id', $requestedId)
                ->where('employee_id', $employee->id)
                ->where('work_date', $today)
                ->first();

            return $schedule ? [$schedule, null] : [null, 'Ca làm việc không hợp lệ.'];
        }

        $todaySchedules = ShiftSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->where('work_date', $today)
            ->where('status', 'scheduled')
            ->get();

        if ($todaySchedules->count() > 1) {
            return [null, 'Bạn có nhiều ca hôm nay, vui lòng chọn ca cần chấm công.'];
        }

        return [$todaySchedules->first(), null];
    }

    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'lat'               => 'nullable|numeric|between:-90,90',
            'lng'               => 'nullable|numeric|between:-180,180',
            'shift_schedule_id' => 'nullable|integer',
        ]);

        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $today  = now()->toDateString();
        $ip     = $request->ip();
        $device = substr((string) $request->userAgent(), 0, 255) ?: null;

        [$shiftSchedule, $resolveError] = $this->resolveShiftScheduleForCheck($employee, $today, $validated['shift_schedule_id'] ?? null);

        if ($resolveError) {
            return response()->json(['success' => false, 'message' => $resolveError], 422);
        }

        [$method, $locationId, $error] = $this->resolveCheckMethod($shiftSchedule, $employee->branch_id, $validated['lat'] ?? null, $validated['lng'] ?? null, $ip);

        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $shiftScheduleId = $shiftSchedule?->id;

        $result = DB::transaction(function () use ($employee, $today, $shiftSchedule, $shiftScheduleId, $method, $locationId, $validated, $ip, $device) {
            $log = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $today)
                ->when($shiftScheduleId, fn($q) => $q->where('shift_schedule_id', $shiftScheduleId), fn($q) => $q->whereNull('shift_schedule_id'))
                ->lockForUpdate()
                ->first();

            if ($log && $log->check_in_at) {
                return ['success' => false, 'message' => 'Bạn đã check-in ca này hôm nay rồi.'];
            }

            $lateMinutes    = 0;
            $effectiveShift = $shiftSchedule ? $this->resolveEffectiveShift($shiftSchedule) : null;
            if ($effectiveShift && $method !== 'wfh') {
                $lateMinutes = $this->computeLateMinutes($effectiveShift);
            }

            $data = [
                'employee_id'          => $employee->id,
                'shift_schedule_id'    => $shiftScheduleId,
                'work_date'            => $today,
                'check_in_at'          => now(),
                'check_in_method'      => $method,
                'check_in_lat'         => $validated['lat'] ?? null,
                'check_in_lng'         => $validated['lng'] ?? null,
                'check_in_ip'          => $ip,
                'check_in_location_id' => $locationId,
                'check_in_device'      => $device,
                'late_minutes'         => $lateMinutes,
            ];

            if ($log) {
                $log->update($data);
            } else {
                $log = AttendanceLog::create($data);
            }

            return ['success' => true, 'log' => $log];
        });

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        activity()->causedBy(auth()->user())
            ->performedOn($result['log'])
            ->inLog('attendance')
            ->withProperties(['employee_code' => $employee->code, 'method' => $method, 'device' => $device])
            ->log("Check-in chấm công — {$employee->name}");

        return response()->json(['success' => true, 'message' => 'Check-in thành công!']);
    }

    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'lat'               => 'nullable|numeric|between:-90,90',
            'lng'               => 'nullable|numeric|between:-180,180',
            'shift_schedule_id' => 'nullable|integer',
        ]);

        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $today  = now()->toDateString();
        $ip     = $request->ip();
        $device = substr((string) $request->userAgent(), 0, 255) ?: null;

        [$shiftSchedule, $resolveError] = $this->resolveShiftScheduleForCheck($employee, $today, $validated['shift_schedule_id'] ?? null);

        if ($resolveError) {
            return response()->json(['success' => false, 'message' => $resolveError], 422);
        }

        [$method, $locationId, $error] = $this->resolveCheckMethod($shiftSchedule, $employee->branch_id, $validated['lat'] ?? null, $validated['lng'] ?? null, $ip);

        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $shiftScheduleId = $shiftSchedule?->id;

        $result = DB::transaction(function () use ($employee, $today, $shiftSchedule, $shiftScheduleId, $method, $locationId, $validated, $ip, $device) {
            $log = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $today)
                ->when($shiftScheduleId, fn($q) => $q->where('shift_schedule_id', $shiftScheduleId), fn($q) => $q->whereNull('shift_schedule_id'))
                ->lockForUpdate()
                ->first();

            if (!$log || !$log->check_in_at) {
                return ['success' => false, 'message' => 'Bạn chưa check-in ca này hôm nay.'];
            }

            if ($log->check_out_at) {
                return ['success' => false, 'message' => 'Bạn đã check-out ca này hôm nay rồi.'];
            }

            $earlyMinutes   = 0;
            $effectiveShift = $shiftSchedule ? $this->resolveEffectiveShift($shiftSchedule) : null;
            if ($effectiveShift && $method !== 'wfh') {
                $earlyMinutes = $this->computeEarlyMinutes($effectiveShift);
            }

            $log->update([
                'check_out_at'          => now(),
                'check_out_method'      => $method,
                'check_out_lat'         => $validated['lat'] ?? null,
                'check_out_lng'         => $validated['lng'] ?? null,
                'check_out_ip'          => $ip,
                'check_out_location_id' => $locationId,
                'check_out_device'      => $device,
                'early_minutes'         => $earlyMinutes,
            ]);

            return ['success' => true, 'log' => $log, 'device_changed' => $log->deviceChanged()];
        });

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        activity()->causedBy(auth()->user())
            ->performedOn($result['log'])
            ->inLog('attendance')
            ->withProperties(['employee_code' => $employee->code, 'method' => $method, 'device' => $device, 'device_changed' => $result['device_changed']])
            ->log("Check-out chấm công — {$employee->name}");

        $message = 'Check-out thành công!';
        if ($result['device_changed']) {
            $message .= ' Lưu ý: bạn đang chấm công bằng thiết bị khác với lúc check-in.';
        }

        return response()->json([
            'success'        => true,
            'message'        => $message,
            'device_changed' => $result['device_changed'],
        ]);
    }

    /**
     * Xác định phương thức xác thực hợp lệ hoặc trả lỗi.
     *
     * Bắt buộc đạt CẢ HAI: GPS trong bán kính cho phép VÀ IP thuộc WiFi văn phòng — của CÙNG một
     * điểm chấm công (không tính gộp GPS khớp điểm A + IP khớp điểm B). Chỉ có 2 kết quả hợp lệ:
     * 'gps_ip' (đạt cả hai) hoặc 'wfh' (ca WFH, bỏ qua xác thực vị trí). Các giá trị 'gps'/'ip'
     * trong enum cột chỉ còn giữ lại cho dữ liệu lịch sử trước khi đổi sang yêu cầu cả hai.
     *
     * @return array{0: ?string, 1: ?int, 2: ?string} [method, location_id, error]
     */
    private function resolveCheckMethod(?ShiftSchedule $shiftSchedule, ?int $branchId, ?float $lat, ?float $lng, string $ip): array
    {
        $isWfh = $shiftSchedule?->isFlexible()
            ? (bool) $shiftSchedule->custom_is_wfh
            : (bool) $shiftSchedule?->shift?->isWfh();

        if ($isWfh) {
            return ['wfh', null, null];
        }

        if (!$branchId) {
            return [null, null, 'Bạn chưa được gán chi nhánh, không thể xác định điểm chấm công.'];
        }

        $locations = AttendanceLocation::where('branch_id', $branchId)
            ->where('is_active', true)
            ->get();

        if ($locations->isEmpty()) {
            return [null, null, 'Chi nhánh của bạn chưa cấu hình điểm chấm công.'];
        }

        $ipOkAny  = false;
        $gpsOkAny = false;

        foreach ($locations as $location) {
            $ipOk  = $location->matchesIp($ip);
            $gpsOk = $lat !== null && $lng !== null && $location->isWithinRadius($lat, $lng);

            if ($ipOk && $gpsOk) {
                return ['gps_ip', $location->id, null];
            }

            $ipOkAny  = $ipOkAny || $ipOk;
            $gpsOkAny = $gpsOkAny || $gpsOk;
        }

        if (!$gpsOkAny && !$ipOkAny) {
            return [null, null, 'Bạn không ở trong khu vực chấm công cho phép (sai vị trí GPS và không kết nối WiFi văn phòng). Cần đạt cả hai để chấm công.'];
        }
        if (!$gpsOkAny) {
            return [null, null, 'Bạn đã kết nối đúng WiFi văn phòng nhưng vị trí GPS không đúng khu vực chấm công. Cần đạt cả hai để chấm công.'];
        }

        return [null, null, 'Vị trí GPS hợp lệ nhưng bạn chưa kết nối WiFi văn phòng. Cần đạt cả hai để chấm công.'];
    }

    /**
     * Shift dùng để tính trễ/sớm: shift thật nếu có, hoặc Shift tạm (không lưu DB) dựng từ
     * custom_start_time/custom_end_time của ca linh hoạt — không cho phép trễ/sớm (grace = 0).
     */
    private function resolveEffectiveShift(ShiftSchedule $shiftSchedule): ?\App\Models\Shift
    {
        if ($shiftSchedule->shift) {
            return $shiftSchedule->shift;
        }

        if ($shiftSchedule->isFlexible() && $shiftSchedule->custom_start_time) {
            return new \App\Models\Shift([
                'start_time'          => $shiftSchedule->custom_start_time,
                'end_time'            => $shiftSchedule->custom_end_time,
                'is_overnight'        => (bool) $shiftSchedule->custom_is_overnight,
                'grace_late_minutes'  => 0,
                'grace_early_minutes' => 0,
            ]);
        }

        return null;
    }

    private function computeLateMinutes(\App\Models\Shift $shift): int
    {
        $now   = now();
        $start = $now->copy()->setTimeFromTimeString((string) $shift->start_time);

        if ($now->lessThanOrEqualTo($start)) {
            return 0;
        }

        return max(0, $start->diffInMinutes($now) - $shift->grace_late_minutes);
    }

    private function computeEarlyMinutes(\App\Models\Shift $shift): int
    {
        $now = now();
        $end = $now->copy()->setTimeFromTimeString((string) $shift->end_time);

        if ($now->greaterThanOrEqualTo($end)) {
            return 0;
        }

        return max(0, $now->diffInMinutes($end) - $shift->grace_early_minutes);
    }
}
