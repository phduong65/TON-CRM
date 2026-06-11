<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRewardTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reward_category_id' => 'nullable|exists:reward_categories,id',
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string|max:1000',
            'default_points'     => 'required|integer|min:0|max:9999',
            'is_active'          => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'Tên loại thưởng không được để trống.',
            'default_points.required' => 'Điểm thưởng mặc định không được để trống.',
            'default_points.min'      => 'Điểm thưởng phải lớn hơn hoặc bằng 0.',
        ];
    }
}
