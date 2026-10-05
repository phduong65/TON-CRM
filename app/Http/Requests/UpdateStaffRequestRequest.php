<?php

namespace App\Http\Requests;

use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate sửa 1 trong 4 loại phiếu dùng chung bảng staff_requests (Lượt chấm công, Công tác/Ra
 * ngoài, Đi muộn về sớm, Thay đổi giờ vào/ra, Tăng ca). Loại phiếu (type) và nhân viên (employee_id)
 * KHÔNG được đổi khi sửa — luôn lấy từ bản ghi gốc (route model binding), chỉ validate các trường
 * nội dung tương ứng với type đó. Xem StoreStaffRequestRequest cho luồng tạo mới (rules tương tự).
 */
class UpdateStaffRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->route('staffRequest')->type;

        $rules = [
            'work_date'         => 'required|date',
            'reason'            => 'required|string|max:1000',
            'shift_schedule_id' => 'nullable|integer',
        ];

        return array_merge($rules, match ($type) {
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
                'ot_to_time'   => ['required', 'date_format:H:i', 'different:ot_from_time', function ($attribute, $value, $fail) {
                    $from = $this->input('ot_from_time');
                    if (!$from || !preg_match('/^\d{2}:\d{2}$/', $value)) {
                        return;
                    }

                    $fromAt = Carbon::parse($from);
                    $toAt   = Carbon::parse($value);
                    if ($toAt->lessThanOrEqualTo($fromAt)) {
                        $toAt->addDay();
                    }

                    if ($fromAt->diffInMinutes($toAt) > 16 * 60) {
                        $fail('Thời gian tăng ca không được vượt quá 16 giờ.');
                    }
                }],
            ],
            default => [],
        });
    }

    /**
     * Xem StoreStaffRequestRequest::withValidator() — cùng nguyên tắc bắt buộc chọn đúng ca khi
     * nhân viên xếp đa ca cùng ngày, chỉ khác employee_id luôn lấy từ bản ghi gốc (không đổi được).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $staffRequest = $this->route('staffRequest');
            $type         = $staffRequest->type;

            if (!in_array($type, StoreStaffRequestRequest::SHIFT_AWARE_TYPES, true)) {
                return;
            }

            $employeeId = $staffRequest->employee_id;
            $workDate   = $this->input('work_date');
            $value      = $this->input('shift_schedule_id');

            if ($value) {
                $belongsToEmployeeAndDate = ShiftSchedule::where('id', $value)
                    ->where('employee_id', $employeeId)
                    ->where('work_date', $workDate)
                    ->exists();
                if (!$belongsToEmployeeAndDate) {
                    $validator->errors()->add('shift_schedule_id', 'Ca đã chọn không hợp lệ cho nhân viên/ngày này.');
                }
                return;
            }

            $scheduleCount = ShiftSchedule::where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->where('status', 'scheduled')
                ->count();
            if ($scheduleCount > 1) {
                $validator->errors()->add('shift_schedule_id', 'Nhân viên có nhiều ca vào ngày này — vui lòng chọn đúng ca cần áp dụng.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'work_date.required'            => 'Vui lòng chọn ngày.',
            'reason.required'                => 'Vui lòng nhập lý do.',
            'check_in_at.required_without'   => 'Nhập giờ vào hoặc giờ ra (ít nhất 1 trong 2).',
            'check_out_at.required_without'  => 'Nhập giờ vào hoặc giờ ra (ít nhất 1 trong 2).',
            'to_time.after'                  => 'Giờ kết thúc phải sau giờ bắt đầu.',
            'new_check_out.after'            => 'Giờ ra mới phải sau giờ vào mới.',
            'ot_to_time.different'           => 'Giờ kết thúc không được trùng giờ bắt đầu.',
        ];
    }
}
