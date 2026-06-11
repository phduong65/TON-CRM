<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reward_type_id'     => 'required|exists:reward_types,id',
            'employee_id'        => 'required|exists:employees,id',
            'description'        => 'nullable|string|max:2000',
            'total_points_awarded' => 'required|integer|min:1|max:9999',
            'members'            => 'nullable|array',
            'members.*.employee_id'    => 'required_with:members|exists:employees,id',
            'members.*.points_awarded' => 'required_with:members|integer|min:0',
            'members.*.note'           => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'reward_type_id.required'       => 'Vui lòng chọn loại thưởng.',
            'employee_id.required'          => 'Vui lòng chọn nhân viên được thưởng.',
            'total_points_awarded.required' => 'Điểm thưởng không được để trống.',
            'total_points_awarded.min'      => 'Điểm thưởng phải lớn hơn 0.',
        ];
    }
}
