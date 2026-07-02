<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('employees', 'code')->ignore($this->route('employee'))],
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($this->route('employee'))],
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'team_id' => 'required|exists:teams,id',
            'is_active' => 'boolean',
            'joined_at' => 'nullable|date',
            'employment_type' => 'required|in:full_time,part_time,probation,intern',
            'is_office' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã nhân viên là bắt buộc.',
            'code.unique' => 'Mã nhân viên đã tồn tại.',
            'name.required' => 'Tên nhân viên là bắt buộc.',
            'email.unique' => 'Email đã được sử dụng.',
            'branch_id.required' => 'Vui lòng chọn chi nhánh.',
            'branch_id.exists' => 'Chi nhánh không hợp lệ.',
            'team_id.required' => 'Vui lòng chọn đội nhóm.',
            'team_id.exists' => 'Đội nhóm không hợp lệ.',
        ];
    }
}
