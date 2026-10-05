<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ShiftCoverageRequirement;
use App\Models\ShiftSchedule;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftCoverageService
{
    /**
     * Tính toán bảng định biên vận hành cho 1 tuần (Thứ 2 -> Chủ Nhật).
     */
    public function getWeeklyCoverage(int $branchId, ?int $teamId, Carbon $weekStart): array
    {
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $days = collect(range(0, 6))->map(fn($i) => $weekStart->copy()->addDays($i));

        // Lấy danh sách teams áp dụng
        $teamsQuery = Team::where('branch_id', $branchId)->where('is_active', true);
        if ($teamId) {
            $teamsQuery->where('id', $teamId);
        }
        $teams = $teamsQuery->orderBy('name')->get();
        $teamIds = $teams->pluck('id')->all();

        // Lấy danh sách nhân viên thuộc các team này
        $employees = Employee::where('branch_id', $branchId)
            ->whereIn('team_id', $teamIds)
            ->where('is_active', true)
            ->with(['team', 'position'])
            ->orderBy('name')
            ->get();

        // Lấy tất cả quy tắc định biên hiệu lực trong khoảng tuần này
        $requirements = ShiftCoverageRequirement::where('branch_id', $branchId)
            ->whereIn('team_id', $teamIds)
            ->where('is_active', true)
            ->where('effective_from', '<=', $weekEnd->toDateString())
            ->where(function ($q) use ($weekStart) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $weekStart->toDateString());
            })
            ->with(['team', 'shift'])
            ->get();

        // Lấy tất cả lịch ca trong tuần (bao gồm cả attendanceLog)
        $schedules = ShiftSchedule::with(['shift', 'attendanceLog', 'employee.team'])
            ->where('branch_id', $branchId)
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', 'scheduled')
            ->where(function ($q) use ($teamIds) {
                // Ưu tiên team_id snapshot trên shift_schedules, nếu null thì fallback theo employee.team_id
                $q->whereIn('team_id', $teamIds)
                    ->orWhere(function ($qq) use ($teamIds) {
                        $qq->whereNull('team_id')
                            ->whereHas('employee', fn($eq) => $eq->whereIn('team_id', $teamIds));
                    });
            })
            ->get();

        // Lấy đơn nghỉ phép trong tuần (approved & pending)
        $leaveRequests = LeaveRequest::whereIn('employee_id', $employees->pluck('id'))
            ->whereIn('status', ['approved', 'pending'])
            ->where('date_from', '<=', $weekEnd->toDateString())
            ->where('date_to', '>=', $weekStart->toDateString())
            ->with(['employee'])
            ->get();

        $dailyCoverage = [];
        $totalApprovedLeavesWeek = 0;
        $totalShortageFramesWeek = 0;

        foreach ($days as $day) {
            $dateStr = $day->toDateString();
            $dayOfWeekIso = $day->dayOfWeekIso;

            $daySchedules = $schedules->filter(fn($s) => $s->work_date->toDateString() === $dateStr);
            $dayLeaves = $leaveRequests->filter(function ($l) use ($dateStr) {
                return $l->date_from <= $dateStr && $l->date_to >= $dateStr;
            });

            $approvedLeaves = $dayLeaves->where('status', 'approved');
            $pendingLeaves = $dayLeaves->where('status', 'pending');
            $totalApprovedLeavesWeek += $approvedLeaves->count();

            // Tính định biên cho từng team
            $teamResults = [];
            foreach ($teams as $team) {
                $teamReqs = $requirements->filter(function ($r) use ($team, $day) {
                    return $r->team_id === $team->id && $r->isActiveOnDate($day);
                });

                $teamEmpList = $employees->where('team_id', $team->id);
                $teamSchedules = $daySchedules->filter(function ($s) use ($team) {
                    return ($s->team_id ? $s->team_id === $team->id : $s->employee?->team_id === $team->id);
                });

                $scheduledEmployeeIds = $teamSchedules->pluck('employee_id')->unique()->all();
                $unassignedEmployees = $teamEmpList->whereNotIn('id', $scheduledEmployeeIds)->values();

                $teamScheduledEmployees = $teamSchedules->map(fn($s) => [
                    'schedule_id'    => $s->id,
                    'employee'       => $s->employee,
                    'shift_name'     => $s->shift?->name ?? 'Ca linh hoạt',
                    'time_range'     => $this->formatScheduleTimeRange($s),
                    'has_checked_in' => (bool) $s->attendanceLog?->check_in_at,
                    'is_missed'      => $s->isMissed(),
                ])->values();

                $frameResults = [];
                foreach ($teamReqs as $req) {
                    $analysis = $this->analyzeFrameCoverage($req, $teamSchedules, $day);
                    if ($analysis['status'] === 'shortage') {
                        $totalShortageFramesWeek++;
                    }
                    $frameScheduleIds = array_flip($analysis['participating_schedule_ids']);
                    $frameResults[] = [
                        'requirement'         => $req,
                        'analysis'            => $analysis,
                        // Chỉ những người có ca trùng khung giờ này (không lấy cả bộ phận trong ngày)
                        'scheduled_employees' => $teamScheduledEmployees
                            ->filter(fn($e) => isset($frameScheduleIds[$e['schedule_id']]))
                            ->values(),
                    ];
                }

                $teamResults[] = [
                    'team'                 => $team,
                    'frames'               => $frameResults,
                    'scheduled_count'      => count($scheduledEmployeeIds),
                    'scheduled_employees'  => $teamScheduledEmployees,
                    'approved_leaves'      => $approvedLeaves->filter(fn($l) => $l->employee?->team_id === $team->id)->values(),
                    'pending_leaves'       => $pendingLeaves->filter(fn($l) => $l->employee?->team_id === $team->id)->values(),
                    'unassigned_employees' => $unassignedEmployees,
                ];
            }

            $dailyCoverage[$dateStr] = [
                'date'         => $day,
                'is_today'     => $day->isToday(),
                'is_past'      => $day->isPast() && !$day->isToday(),
                'team_results' => $teamResults,
            ];
        }

        return [
            'days'                       => $days,
            'daily_coverage'             => $dailyCoverage,
            'total_approved_leaves_week' => $totalApprovedLeavesWeek,
            'total_shortage_frames_week' => $totalShortageFramesWeek,
        ];
    }

    /**
     * Phân tích độ bao phủ nhân sự cho 1 khung giờ định biên cụ thể theo thuật toán mục 4.5:
     * Chia khung nhu cầu thành các đoạn tại mọi mốc bắt đầu/kết thúc ca, nghỉ giữa ca và nghỉ phép.
     * Trong từng đoạn, đếm nhân viên duy nhất có mặt đồng thời.
     * Số được xếp của khung là MỨC THẤP NHẤT trong các đoạn của khung.
     */
    public function analyzeFrameCoverage(ShiftCoverageRequirement $req, Collection $daySchedules, Carbon $date): array
    {
        $windowStartMin = $this->timeToMinutes($req->start_time);
        $windowEndMin   = $this->timeToMinutes($req->end_time);

        if ($windowEndMin <= $windowStartMin) {
            $windowEndMin += 1440; // Qua đêm
        }

        // Lấy danh sách ca có khoảng giờ giao với khung nhu cầu [windowStartMin, windowEndMin)
        $applicableSchedules = [];
        $timePoints = [$windowStartMin, $windowEndMin];

        foreach ($daySchedules as $schedule) {
            $effective = $schedule->effectiveShift();
            if (!$effective || !$effective->start_time || !$effective->end_time) {
                continue;
            }

            $startMin = $this->timeToMinutes($effective->start_time);
            $endMin   = $this->timeToMinutes($effective->end_time);

            if ($endMin <= $startMin) {
                $endMin += 1440;
            }

            // Kiểm tra giao nhau: [max(start1, start2), min(end1, end2))
            if ($startMin < $windowEndMin && $endMin > $windowStartMin) {
                $clippedStart = max($startMin, $windowStartMin);
                $clippedEnd   = min($endMin, $windowEndMin);

                $applicableSchedules[] = [
                    'schedule_id'    => $schedule->id,
                    'employee_id'    => $schedule->employee_id,
                    'employee_name'  => $schedule->employee?->name,
                    'start_min'      => $startMin,
                    'end_min'        => $endMin,
                    'has_checked_in' => (bool) $schedule->attendanceLog?->check_in_at,
                ];

                if ($clippedStart > $windowStartMin && $clippedStart < $windowEndMin) {
                    $timePoints[] = $clippedStart;
                }
                if ($clippedEnd > $windowStartMin && $clippedEnd < $windowEndMin) {
                    $timePoints[] = $clippedEnd;
                }
            }
        }

        $timePoints = array_values(array_unique($timePoints));
        sort($timePoints);

        // Chia thành các đoạn slice liền kề [t_i, t_{i+1})
        $slices = [];
        $minConcurrentCount = null;
        $minActualConcurrentCount = null;

        for ($i = 0; $i < count($timePoints) - 1; $i++) {
            $sliceStart = $timePoints[$i];
            $sliceEnd   = $timePoints[$i + 1];

            if ($sliceStart >= $sliceEnd) {
                continue;
            }

            // Đếm nhân viên DUY NHẤT có ca bao phủ toàn bộ slice này
            $scheduledEmployees = [];
            $actualCheckedInEmployees = [];

            foreach ($applicableSchedules as $sch) {
                if ($sch['start_min'] <= $sliceStart && $sch['end_min'] >= $sliceEnd) {
                    $scheduledEmployees[$sch['employee_id']] = true;
                    if ($sch['has_checked_in']) {
                        $actualCheckedInEmployees[$sch['employee_id']] = true;
                    }
                }
            }

            $count = count($scheduledEmployees);
            $actualCount = count($actualCheckedInEmployees);

            $slices[] = [
                'start_formatted' => $this->minutesToTime($sliceStart),
                'end_formatted'   => $this->minutesToTime($sliceEnd),
                'concurrent'      => $count,
                'actual'          => $actualCount,
                'is_shortage'     => $count < $req->minimum_staff,
            ];

            if ($minConcurrentCount === null || $count < $minConcurrentCount) {
                $minConcurrentCount = $count;
            }
            if ($minActualConcurrentCount === null || $actualCount < $minActualConcurrentCount) {
                $minActualConcurrentCount = $actualCount;
            }
        }

        // Nếu trong khung không có mốc nào (không ai làm cả), số người = 0
        if ($minConcurrentCount === null) {
            $minConcurrentCount = 0;
            $minActualConcurrentCount = 0;
            $slices[] = [
                'start_formatted' => substr((string) $req->start_time, 0, 5),
                'end_formatted'   => substr((string) $req->end_time, 0, 5),
                'concurrent'      => 0,
                'actual'          => 0,
                'is_shortage'     => $req->minimum_staff > 0,
            ];
        }

        $minStaff = $req->minimum_staff;
        $targetStaff = $req->target_staff ?? $minStaff;

        if ($minConcurrentCount < $minStaff) {
            $status = 'shortage';
            $shortageAmount = $minStaff - $minConcurrentCount;
            $statusLabel = "Thiếu {$shortageAmount} người";
            $badgeColor = 'danger'; // đỏ
        } elseif ($minConcurrentCount < $targetStaff) {
            $status = 'minimum_met';
            $needForTarget = $targetStaff - $minConcurrentCount;
            $statusLabel = "Đủ tối thiểu (thiếu {$needForTarget} để đạt mục tiêu)";
            $badgeColor = 'warning'; // vàng
        } else {
            $status = 'target_met';
            $statusLabel = 'Đạt mục tiêu';
            $badgeColor = 'success'; // xanh lá
        }

        // Tìm các đoạn giờ cụ thể bị thiếu
        $shortageIntervals = collect($slices)->filter(fn($s) => $s['is_shortage'])->values()->all();

        return [
            'status'              => $status,
            'status_label'        => $statusLabel,
            'badge_color'         => $badgeColor,
            'minimum_staff'       => $minStaff,
            'target_staff'        => $targetStaff,
            'scheduled_coverage'  => $minConcurrentCount,
            'actual_coverage'     => $minActualConcurrentCount,
            'slices'              => $slices,
            'shortage_intervals'  => $shortageIntervals,
            'participating_count' => collect($applicableSchedules)->pluck('employee_id')->unique()->count(),
            // Ca có giờ làm giao với khung (đã xử lý ca qua đêm) — dùng để chỉ liệt kê đúng người của khung này
            'participating_schedule_ids' => collect($applicableSchedules)->pluck('schedule_id')->unique()->values()->all(),
        ];
    }

    /**
     * Kiểm tra trùng lặp/chồng lấn quy tắc định biên khi tạo mới hoặc cập nhật.
     */
    public function checkRequirementOverlap(array $data, ?int $ignoreId = null): ?string
    {
        $branchId     = $data['branch_id'];
        $teamId       = $data['team_id'];
        $daysOfWeek   = (array) $data['days_of_week'];
        $startMin     = $this->timeToMinutes($data['start_time']);
        $endMin       = $this->timeToMinutes($data['end_time']);
        if ($endMin <= $startMin) {
            $endMin += 1440;
        }

        $effFrom  = $data['effective_from'];
        $effUntil = $data['effective_until'] ?? null;

        $existingReqs = ShiftCoverageRequirement::where('branch_id', $branchId)
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->get();

        foreach ($existingReqs as $other) {
            // 1. Kiểm tra ngày hiệu lực có giao nhau không
            $otherFrom = $other->effective_from->toDateString();
            $otherUntil = $other->effective_until?->toDateString();

            $dateOverlap = true;
            if ($effUntil && $effUntil < $otherFrom) {
                $dateOverlap = false;
            }
            if ($otherUntil && $otherUntil < $effFrom) {
                $dateOverlap = false;
            }
            if (!$dateOverlap) {
                continue;
            }

            // 2. Kiểm tra các thứ trong tuần có giao nhau không
            $intersectDays = array_intersect($daysOfWeek, $other->days_of_week ?? []);
            if (empty($intersectDays)) {
                continue;
            }

            // 3. Kiểm tra khung giờ có chồng lấn không [start, end)
            $otherStartMin = $this->timeToMinutes($other->start_time);
            $otherEndMin   = $this->timeToMinutes($other->end_time);
            if ($otherEndMin <= $otherStartMin) {
                $otherEndMin += 1440;
            }

            if (max($startMin, $otherStartMin) < min($endMin, $otherEndMin)) {
                $dayMap = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];
                $overlapDaysStr = collect($intersectDays)->map(fn($d) => $dayMap[$d] ?? $d)->implode(', ');
                return "Quy tắc bị chồng lấn với khung \"{$other->name}\" ({$other->start_time}–{$other->end_time}) vào các ngày [{$overlapDaysStr}].";
            }
        }

        return null;
    }

    private function formatScheduleTimeRange(ShiftSchedule $schedule): string
    {
        $eff = $schedule->effectiveShift();
        if (!$eff || !$eff->start_time) {
            return '--:--';
        }

        return substr($eff->start_time, 0, 5) . ' – ' . substr($eff->end_time, 0, 5);
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    private function minutesToTime(int $minutes): string
    {
        $normalized = $minutes % 1440;
        $h = floor($normalized / 60);
        $m = $normalized % 60;

        return sprintf('%02d:%02d', $h, $m);
    }
}
