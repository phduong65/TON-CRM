<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'     => 'required|exists:branches,id',
            'name'          => 'required|string|max:255',
            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:5000',
            'allowed_ips'   => 'nullable|string',
            'is_active'     => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Vui lòng chọn chi nhánh.',
            'name.required'      => 'Tên điểm chấm công không được để trống.',
            'latitude.required'  => 'Vĩ độ (latitude) không được để trống.',
            'longitude.required' => 'Kinh độ (longitude) không được để trống.',
        ];
    }

    /**
     * Chuẩn hoá "allowed_ips" từ chuỗi nhiều dòng (textarea) sang mảng để lưu JSON.
     */
    public function allowedIpsArray(): array
    {
        $raw = (string) $this->input('allowed_ips', '');
        return collect(preg_split('/[\r\n,]+/', $raw))
            ->map(fn($ip) => trim($ip))
            ->filter()
            ->values()
            ->all();
    }
}
