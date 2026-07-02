<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shift = $this->route('shift');

        return [
            'code'                 => 'required|string|max:50|unique:shifts,code,' . $shift->id,
            'name'                 => 'required|string|max:255',
            'branch_id'            => 'nullable|exists:branches,id',
            'start_time'           => 'required|date_format:H:i',
            'end_time'             => 'required|date_format:H:i',
            'is_overnight'         => 'nullable|boolean',
            'break_minutes'        => 'nullable|integer|min:0|max:600',
            'grace_late_minutes'   => 'nullable|integer|min:0|max:120',
            'grace_early_minutes'  => 'nullable|integer|min:0|max:120',
            'standard_work_hours'  => 'nullable|numeric|min:1|max:24',
            'shift_type'           => 'required|in:fulltime,parttime',
            'work_mode'            => 'required|in:onsite,wfh',
            'color'                => 'nullable|string|max:20',
            'is_active'            => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'       => 'Mã ca không được để trống.',
            'code.unique'         => 'Mã ca đã tồn tại.',
            'name.required'       => 'Tên ca không được để trống.',
            'start_time.required' => 'Giờ bắt đầu không được để trống.',
            'end_time.required'   => 'Giờ kết thúc không được để trống.',
        ];
    }
}
