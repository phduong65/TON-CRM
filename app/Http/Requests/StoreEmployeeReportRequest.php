<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reported_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'violation_id'         => ['nullable', 'integer', 'exists:violations,id'],
            'description'          => ['required', 'string', 'max:2000'],
            'evidence_note'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reported_employee_id.required' => 'Vui lòng chọn nhân viên bị báo cáo.',
            'reported_employee_id.exists'   => 'Nhân viên bị báo cáo không tồn tại.',
            'description.required'          => 'Vui lòng mô tả sự việc.',
            'description.max'               => 'Mô tả không được vượt quá 2000 ký tự.',
        ];
    }
}
