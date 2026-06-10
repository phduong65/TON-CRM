<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'violation_id'    => 'required|exists:violations,id',
            'employee_id'     => 'required|exists:employees,id',
            'points_deducted' => 'required|integer|min:0|max:100',
            'money_deducted'  => 'nullable|numeric|min:0',
            'description'     => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'violation_id.required'    => 'Vui lòng chọn vi phạm.',
            'employee_id.required'     => 'Vui lòng chọn nhân viên vi phạm.',
            'points_deducted.required' => 'Vui lòng nhập số điểm trừ.',
            'points_deducted.max'      => 'Điểm trừ không vượt quá 100.',
        ];
    }
}
