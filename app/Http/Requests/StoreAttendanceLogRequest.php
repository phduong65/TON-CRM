<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id'       => 'required|exists:employees,id',
            'work_date'         => 'required|date|before_or_equal:today',
            'shift_schedule_id' => 'nullable|integer|exists:shift_schedules,id',
            'check_in_at'       => 'nullable|required_without:check_out_at|date_format:H:i',
            'check_out_at'      => 'nullable|required_without:check_in_at|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required'          => 'Vui lòng chọn nhân viên.',
            'employee_id.exists'            => 'Nhân viên không hợp lệ.',
            'work_date.required'            => 'Vui lòng chọn ngày.',
            'work_date.before_or_equal'     => 'Không thể tạo chấm công cho ngày trong tương lai.',
            'check_in_at.required_without'  => 'Nhập giờ vào hoặc giờ ra (ít nhất 1 trong 2).',
            'check_out_at.required_without' => 'Nhập giờ vào hoặc giờ ra (ít nhất 1 trong 2).',
            'check_in_at.date_format'       => 'Giờ vào không hợp lệ.',
            'check_out_at.date_format'      => 'Giờ ra không hợp lệ.',
        ];
    }
}
