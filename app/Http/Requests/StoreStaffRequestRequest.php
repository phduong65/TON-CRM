<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Người có quyền duyệt (bất kỳ loại nào trong hub) được tạo yêu cầu thay cho nhân viên khác —
     * nhân viên thường chỉ tạo được cho chính mình (employee_id bị bỏ qua ở Controller nếu có gửi lên).
     */
    public function userIsApprover(): bool
    {
        $user = $this->user();

        return $user && ($user->can('approve-staff-requests') || $user->can('approve-leave-requests') || $user->can('approve-shift-swaps'));
    }

    public function rules(): array
    {
        $rules = [
            'type'        => 'required|in:attendance_correction,business_trip,late_early,time_change,overtime',
            'employee_id' => ($this->userIsApprover() ? 'required' : 'nullable') . '|exists:employees,id',
            'work_date'   => 'required|date',
            'reason'      => 'required|string|max:1000',
        ];

        return array_merge($rules, match ($this->input('type')) {
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
                // Không dùng "after" — cho phép tăng ca qua đêm (VD 23:00–03:00 hôm sau).
                // "different" chỉ chặn trường hợp giờ bắt đầu = giờ kết thúc (0 giờ, vô nghĩa).
                'ot_to_time'   => ['required', 'date_format:H:i', 'different:ot_from_time', function ($attribute, $value, $fail) {
                    $from = $this->input('ot_from_time');
                    if (!$from || !preg_match('/^\d{2}:\d{2}$/', $value)) {
                        return;
                    }

                    $fromAt = \Carbon\Carbon::parse($from);
                    $toAt   = \Carbon\Carbon::parse($value);
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

    public function messages(): array
    {
        return [
            'type.required'                  => 'Vui lòng chọn loại yêu cầu.',
            'employee_id.required'            => 'Vui lòng chọn nhân viên.',
            'work_date.required'              => 'Vui lòng chọn ngày.',
            'reason.required'                 => 'Vui lòng nhập lý do.',
            'check_in_at.required_without'    => 'Nhập giờ vào hoặc giờ ra (ít nhất 1 trong 2).',
            'check_out_at.required_without'   => 'Nhập giờ vào hoặc giờ ra (ít nhất 1 trong 2).',
            'to_time.after'                   => 'Giờ kết thúc phải sau giờ bắt đầu.',
            'new_check_out.after'             => 'Giờ ra mới phải sau giờ vào mới.',
            'ot_to_time.different'            => 'Giờ kết thúc không được trùng giờ bắt đầu.',
        ];
    }
}
