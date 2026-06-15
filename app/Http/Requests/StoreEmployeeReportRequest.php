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
            'evidence_files'       => ['nullable', 'array', 'max:5'],
            'evidence_files.*'     => ['file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'reported_employee_id.required' => 'Vui lòng chọn nhân viên bị báo cáo.',
            'reported_employee_id.exists'   => 'Nhân viên bị báo cáo không tồn tại.',
            'description.required'          => 'Vui lòng mô tả sự việc.',
            'description.max'               => 'Mô tả không được vượt quá 2000 ký tự.',
            'evidence_files.max'            => 'Tối đa 5 file đính kèm.',
            'evidence_files.*.file'         => 'File đính kèm không hợp lệ.',
            'evidence_files.*.mimes'        => 'Chỉ chấp nhận ảnh (JPG, PNG, GIF, WEBP) và video (MP4, MOV, AVI, WEBM).',
            'evidence_files.*.max'          => 'Mỗi file không được vượt quá 20 MB.',
        ];
    }
}
