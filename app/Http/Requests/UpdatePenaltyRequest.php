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
            'violation_id'               => 'required|exists:violations,id',
            'employee_id'                => 'required|exists:employees,id',
            'points_deducted'            => 'required|integer|min:0|max:100',
            'money_deducted'             => 'nullable|numeric|min:0',
            'description'                => 'nullable|string|max:1000',
            'members'                    => 'nullable|array',
            'members.*.employee_id'      => 'required_with:members|exists:employees,id',
            'members.*.points_deducted'  => 'required_with:members|integer|min:0|max:100',
            'delete_attachment_ids'      => 'nullable|array',
            'delete_attachment_ids.*'    => 'integer|exists:attachments,id',
            'attachments'                => 'nullable|array',
            'attachments.*'              => 'file|max:20480|mimes:jpeg,jpg,png,gif,webp,heic,heif,mp4,mov,avi,webm',
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
