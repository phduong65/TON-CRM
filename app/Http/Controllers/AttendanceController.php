<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLocation;
use App\Models\AttendanceLog;
use App\Models\Setting;
use App\Models\ShiftSchedule;
use App\Services\AttendanceAlertService;
use App\Services\AttendanceAutoPenaltyService;
use App\Services\AttendanceIpMismatchAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index()
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        // Rà soát cảnh báo chấm công mới nhất cho nhân viên
        app(AttendanceAlertService::class)->scanAlerts($employee->id, 7);

        $today = now()->toDateString();

        $shiftSchedules = $this->relevantShiftSchedulesQuery($employee->id, $today)
            ->with(['shift', 'attendanceLog'])
            ->orderBy('id')
            ->get();

        // Tách ca "đang diễn ra" (còn check-in/out được) khỏi ca "đã bỏ lỡ" (quá giờ kết thúc mà
        // chưa từng check-in) — xem ShiftSchedule::isMissed(). Hiển thị riêng để nhân viên không
        // nhầm tưởng vẫn còn chấm công được cho ca đã lỡ.
        $activeShifts = $shiftSchedules->reject(fn($s) => $s->isMissed())->values();
        $missedShifts = $shiftSchedules->filter(fn($s) => $s->isMissed())->values();

        // Chỉ cần khi không có ca nào hôm nay — hiển thị lại trạng thái chấm công "ca ngoài lịch" đã lỡ thực hiện.
        $unscheduledLog = $shiftSchedules->isEmpty()
            ? AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $today)
                ->whereNull('shift_schedule_id')
                ->first()
            : null;

        return view('attendance.index', compact('employee', 'activeShifts', 'missedShifts', 'unscheduledLog'));
    }

    /**
     * Xác định ca cần chấm công hôm nay.
     * - Nếu client gửi shift_schedule_id: dùng đúng ca đó (phải thuộc về nhân viên & nằm trong
     *   phạm vi ca "đang diễn ra" — xem relevantShiftSchedulesQuery()).
     * - Nếu không gửi: chỉ tự suy ra khi nhân viên có 0 hoặc đúng 1 ca (tương thích ngược).
     *   Có ≥2 ca mà không chỉ định rõ thì bắt buộc client phải chọn.
     *
     * @return array{0: ?ShiftSchedule, 1: ?string} [shiftSchedule, error]
     */
    private function resolveShiftScheduleForCheck(\App\Models\Employee $employee, string $today, ?int $requestedId): array
    {
        if ($requestedId) {
            $schedule = $this->relevantShiftSchedulesQuery($employee->id, $today)
                ->with('shift')
                ->where('id', $requestedId)
                ->first();

            return $schedule ? [$schedule, null] : [null, 'Ca làm việc không hợp lệ.'];
        }

        $candidateSchedules = $this->relevantShiftSchedulesQuery($employee->id, $today)
            ->with('shift')
            ->get();

        if ($candidateSchedules->count() > 1) {
            return [null, 'Bạn có nhiều ca hôm nay, vui lòng chọn ca cần chấm công.'];
        }

        return [$candidateSchedules->first(), null];
    }

    /**
     * Danh sách ca "đang diễn ra" cho nhân viên. Logic dùng chung với DashboardController
     * (widget chấm công nhanh) — xem ShiftSchedule::relevantForEmployeeToday().
     */
    private function relevantShiftSchedulesQuery(int $employeeId, string $today)
    {
        return ShiftSchedule::relevantForEmployeeToday($employeeId, $today);
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

        // Ca đã quá giờ kết thúc mà chưa từng check-in ("đã bỏ lỡ" — xem ShiftSchedule::isMissed())
        // thì chặn hẳn, không cho check-in trễ tuỳ ý. Nhân viên cần liên hệ quản lý để được chấm
        // công hộ qua AttendanceLogsController::store() (permission create-attendance-logs).
        if ($shiftSchedule && $shiftSchedule->isMissed()) {
            return response()->json([
                'success' => false,
                'message' => 'Ca này đã kết thúc, bạn không thể check-in nữa. Vui lòng liên hệ quản lý để được hỗ trợ chấm công.',
            ], 422);
        }

        [$method, $locationId, $error, $mismatchLocation] = $this->resolveCheckMethod($shiftSchedule, $employee->branch_id, $validated['lat'] ?? null, $validated['lng'] ?? null, $ip);

        if ($error) {
            if ($mismatchLocation) {
                app(AttendanceIpMismatchAlertService::class)->recordAndMaybeAlert($mismatchLocation, $employee, $ip);
            }

            return response()->json(['success' => false, 'message' => $error], 422);
        }

        if ($method === 'gps_ip' && $locationId) {
            app(AttendanceIpMismatchAlertService::class)->clearAlertIfResolved($locationId);
        }

        $shiftScheduleId = $shiftSchedule?->id;
        // Ca qua đêm: dùng đúng work_date gốc của ShiftSchedule (không phải "hôm nay") để bản ghi
        // check-in/out cùng khớp một log dù check-out diễn ra sau khi đã sang ngày mới.
        $logWorkDate = $shiftSchedule ? $shiftSchedule->work_date->toDateString() : $today;

        $result = DB::transaction(function () use ($employee, $logWorkDate, $shiftSchedule, $shiftScheduleId, $method, $locationId, $validated, $ip, $device) {
            $log = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $logWorkDate)
                ->when($shiftScheduleId, fn($q) => $q->where('shift_schedule_id', $shiftScheduleId), fn($q) => $q->whereNull('shift_schedule_id'))
                ->lockForUpdate()
                ->first();

            if ($log && $log->check_in_at) {
                return ['success' => false, 'message' => 'Bạn đã check-in ca này hôm nay rồi.'];
            }

            // Ca WFH chỉ bỏ qua xác thực GPS/WiFi (method='wfh') — vẫn phải tính trễ/sớm như ca
            // onsite bình thường, dựa theo giờ ca đã xếp (effectiveShift()).
            $lateMinutes    = 0;
            $effectiveShift = $shiftSchedule ? $this->resolveEffectiveShift($shiftSchedule) : null;
            if ($effectiveShift) {
                $lateMinutes = $this->computeLateMinutes($shiftSchedule);
            }

            $data = [
                'employee_id'          => $employee->id,
                'shift_schedule_id'    => $shiftScheduleId,
                'work_date'            => $logWorkDate,
                'check_in_at'          => now(),
                'check_in_method'      => $method,
                'check_in_lat'         => $validated['lat'] ?? null,
                'check_in_lng'         => $validated['lng'] ?? null,
                'check_in_ip'          => $ip,
                'check_in_location_id' => $locationId,
                'check_in_device'      => $device,
                'late_minutes'         => $lateMinutes,
                ...($shiftSchedule?->shiftSnapshotAttributes() ?? []),
            ];

            if ($log) {
                $log->update($data);
            } else {
                $log = AttendanceLog::create($data);
            }

            if ($lateMinutes > 0) {
                app(AttendanceAutoPenaltyService::class)->createIfNeeded($log, 'late', $lateMinutes);
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

        app(AttendanceAlertService::class)->resolveAlertsOnLog($result['log']);

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

        [$method, $locationId, $error, $mismatchLocation] = $this->resolveCheckMethod($shiftSchedule, $employee->branch_id, $validated['lat'] ?? null, $validated['lng'] ?? null, $ip);

        if ($error) {
            if ($mismatchLocation) {
                app(AttendanceIpMismatchAlertService::class)->recordAndMaybeAlert($mismatchLocation, $employee, $ip);
            }

            return response()->json(['success' => false, 'message' => $error], 422);
        }

        if ($method === 'gps_ip' && $locationId) {
            app(AttendanceIpMismatchAlertService::class)->clearAlertIfResolved($locationId);
        }

        $shiftScheduleId = $shiftSchedule?->id;
        // Ca qua đêm: dùng đúng work_date gốc của ShiftSchedule (không phải "hôm nay") để bản ghi
        // check-in/out cùng khớp một log dù check-out diễn ra sau khi đã sang ngày mới.
        $logWorkDate = $shiftSchedule ? $shiftSchedule->work_date->toDateString() : $today;

        $result = DB::transaction(function () use ($employee, $logWorkDate, $shiftSchedule, $shiftScheduleId, $method, $locationId, $validated, $ip, $device) {
            $log = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $logWorkDate)
                ->when($shiftScheduleId, fn($q) => $q->where('shift_schedule_id', $shiftScheduleId), fn($q) => $q->whereNull('shift_schedule_id'))
                ->lockForUpdate()
                ->first();

            if (!$log || !$log->check_in_at) {
                return ['success' => false, 'message' => 'Bạn chưa check-in ca này hôm nay.'];
            }

            if ($log->check_out_at) {
                return ['success' => false, 'message' => 'Bạn đã check-out ca này hôm nay rồi.'];
            }

            // Xem giải thích ở checkIn() — WFH chỉ bỏ qua xác thực vị trí, không bỏ qua tính sớm.
            $earlyMinutes   = 0;
            $effectiveShift = $shiftSchedule ? $this->resolveEffectiveShift($shiftSchedule) : null;
            if ($effectiveShift) {
                $earlyMinutes = $this->computeEarlyMinutes($shiftSchedule);
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

            if ($earlyMinutes > 0) {
                app(AttendanceAutoPenaltyService::class)->createIfNeeded($log, 'early', $earlyMinutes);
            }

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

        app(AttendanceAlertService::class)->resolveAlertsOnLog($result['log']);

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
     * Cách xác thực áp dụng phụ thuộc 2 cờ cấu hình toàn hệ thống — settings.attendance_verify_gps
     * và settings.attendance_verify_wifi (xem SettingsController) — bật cờ nào thì kiểm tra cờ đó:
     * - Cả hai bật: bắt buộc đạt CẢ HAI (GPS trong bán kính cho phép VÀ IP thuộc WiFi văn phòng)
     *   của CÙNG một điểm chấm công (không tính gộp GPS khớp điểm A + IP khớp điểm B) → 'gps_ip'.
     * - Chỉ GPS bật: chỉ cần đúng vị trí GPS của 1 điểm bất kỳ → 'gps'.
     * - Chỉ WiFi bật: chỉ cần đúng IP văn phòng của 1 điểm bất kỳ → 'ip'.
     * - Cả hai tắt: bỏ qua xác thực vị trí hoàn toàn → 'skip'.
     * Ca WFH luôn bỏ qua xác thực vị trí bất kể cấu hình → 'wfh'.
     *
     * Khi lỗi xảy ra đúng vì "GPS đúng nhưng IP sai" trong chế độ bắt buộc cả hai (nhân viên thực sự
     * đang ở văn phòng), trả kèm $mismatchLocation — điểm chấm công đó — để caller ghi nhận qua
     * AttendanceIpMismatchAlertService (dấu hiệu allowed_ips có thể đã lỗi thời do ISP đổi IP public).
     *
     * @return array{0: ?string, 1: ?int, 2: ?string, 3: ?AttendanceLocation} [method, location_id, error, mismatchLocation]
     */
    private function resolveCheckMethod(?ShiftSchedule $shiftSchedule, ?int $branchId, ?float $lat, ?float $lng, string $ip): array
    {
        $isWfh = $shiftSchedule?->isFlexible()
            ? (bool) $shiftSchedule->custom_is_wfh
            : (bool) $shiftSchedule?->shift?->isWfh();

        if ($isWfh) {
            return ['wfh', null, null, null];
        }

        $gpsEnabled  = Setting::getValue('attendance_verify_gps', '1') !== '0';
        $wifiEnabled = Setting::getValue('attendance_verify_wifi', '1') !== '0';

        if (!$gpsEnabled && !$wifiEnabled) {
            return ['skip', null, null, null];
        }

        if (!$branchId) {
            return [null, null, 'Bạn chưa được gán chi nhánh, không thể xác định điểm chấm công.', null];
        }

        $locations = AttendanceLocation::where('branch_id', $branchId)
            ->where('is_active', true)
            ->get();

        if ($locations->isEmpty()) {
            return [null, null, 'Chi nhánh của bạn chưa cấu hình điểm chấm công.', null];
        }

        if ($gpsEnabled && $wifiEnabled) {
            return $this->resolveGpsAndIpMethod($locations, $lat, $lng, $ip);
        }

        if ($gpsEnabled) {
            foreach ($locations as $location) {
                if ($lat !== null && $lng !== null && $location->isWithinRadius($lat, $lng)) {
                    return ['gps', $location->id, null, null];
                }
            }

            return [null, null, 'Bạn không ở trong khu vực chấm công cho phép (sai vị trí GPS).', null];
        }

        foreach ($locations as $location) {
            if ($location->matchesIp($ip)) {
                return ['ip', $location->id, null, null];
            }
        }

        return [null, null, 'Bạn chưa kết nối đúng WiFi văn phòng để chấm công.', null];
    }

    /**
     * @param \Illuminate\Support\Collection<int, AttendanceLocation> $locations
     * @return array{0: ?string, 1: ?int, 2: ?string, 3: ?AttendanceLocation}
     */
    private function resolveGpsAndIpMethod($locations, ?float $lat, ?float $lng, string $ip): array
    {
        $ipOkAny  = false;
        $gpsOkAny = false;
        $gpsOkLocation = null;

        foreach ($locations as $location) {
            $ipOk  = $location->matchesIp($ip);
            $gpsOk = $lat !== null && $lng !== null && $location->isWithinRadius($lat, $lng);

            if ($ipOk && $gpsOk) {
                return ['gps_ip', $location->id, null, null];
            }

            if ($gpsOk && !$gpsOkAny) {
                $gpsOkLocation = $location;
            }

            $ipOkAny  = $ipOkAny || $ipOk;
            $gpsOkAny = $gpsOkAny || $gpsOk;
        }

        if (!$gpsOkAny && !$ipOkAny) {
            return [null, null, 'Bạn không ở trong khu vực chấm công cho phép (sai vị trí GPS và không kết nối WiFi văn phòng). Cần đạt cả hai để chấm công.', null];
        }
        if (!$gpsOkAny) {
            return [null, null, 'Bạn đã kết nối đúng WiFi văn phòng nhưng vị trí GPS không đúng khu vực chấm công. Cần đạt cả hai để chấm công.', null];
        }

        return [null, null, 'Vị trí GPS hợp lệ nhưng bạn chưa kết nối WiFi văn phòng. Cần đạt cả hai để chấm công.', $gpsOkLocation];
    }

    private function resolveEffectiveShift(ShiftSchedule $shiftSchedule): ?\App\Models\Shift
    {
        return $shiftSchedule->effectiveShift();
    }

    /**
     * Dùng ShiftSchedule::startAt() (đã tính đúng theo work_date gốc, xử lý đúng ca qua đêm) thay
     * vì tự dựng mốc giờ từ now()'s date (setTimeFromTimeString) — nếu không, check-in trễ SAU
     * NỬA ĐÊM cho ca qua đêm (VD ca 22h-06h, check-in lúc 00h20) sẽ tính ra mốc "start" rơi vào
     * TƯƠNG LAI (22h cùng ngày hiện tại), khiến now() <= start → trả về 0 phút trễ dù thực trễ.
     */
    private function computeLateMinutes(ShiftSchedule $shiftSchedule): int
    {
        $shift = $shiftSchedule->effectiveShift();
        $start = $shiftSchedule->startAt();
        if (!$shift || !$start) {
            return 0;
        }

        $now = now();
        if ($now->lessThanOrEqualTo($start)) {
            return 0;
        }

        return max(0, $start->diffInMinutes($now) - ($shift->grace_late_minutes ?? 0));
    }

    private function computeEarlyMinutes(ShiftSchedule $shiftSchedule): int
    {
        $shift = $shiftSchedule->effectiveShift();
        $end   = $shiftSchedule->endAt();
        if (!$shift || !$end) {
            return 0;
        }

        $now = now();
        if ($now->greaterThanOrEqualTo($end)) {
            return 0;
        }

        return max(0, $now->diffInMinutes($end) - ($shift->grace_early_minutes ?? 0));
    }
}
