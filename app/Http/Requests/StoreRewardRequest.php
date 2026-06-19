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
        $targetType = $this->input('target_type', 'individual');

        return [
            'target_type'          => 'required|in:individual,branch,team,all',
            'target_id'            => $this->targetIdRule($targetType),
            'reward_type_id'       => 'required|exists:reward_types,id',
            'employee_id'          => $targetType === 'individual' ? 'required|exists:employees,id' : 'nullable|exists:employees,id',
            'description'          => 'nullable|string|max:2000',
            'total_points_awarded' => 'required|integer|min:1|max:9999',
            'members'              => 'nullable|array',
            'members.*.employee_id'    => 'required_with:members|exists:employees,id',
            'members.*.points_awarded' => 'required_with:members|integer|min:0',
            'members.*.note'           => 'nullable|string|max:255',
        ];
    }

    private function targetIdRule(string $targetType): string
    {
        return match ($targetType) {
            'branch' => 'required|exists:branches,id',
            'team'   => 'required|exists:teams,id',
            default  => 'nullable',
        };
    }

    public function messages(): array
    {
        return [
            'target_type.required'          => 'Vui lòng chọn đối tượng thưởng.',
            'target_id.required'            => 'Vui lòng chọn chi nhánh / đội nhóm.',
            'reward_type_id.required'       => 'Vui lòng chọn loại thưởng.',
            'employee_id.required'          => 'Vui lòng chọn nhân viên được thưởng.',
            'total_points_awarded.required' => 'Điểm thưởng không được để trống.',
            'total_points_awarded.min'      => 'Điểm thưởng phải lớn hơn 0.',
        ];
    }
}
