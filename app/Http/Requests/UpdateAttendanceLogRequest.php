<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in_at'  => 'nullable|date_format:H:i',
            'check_out_at' => 'nullable|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'check_in_at.date_format'  => 'Giờ vào không hợp lệ.',
            'check_out_at.date_format' => 'Giờ ra không hợp lệ.',
        ];
    }
}
