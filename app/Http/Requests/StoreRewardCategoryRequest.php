<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRewardCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-reward-categories');
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255', 'unique:reward_categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên danh mục thưởng là bắt buộc.',
            'name.unique'   => 'Tên danh mục thưởng đã tồn tại.',
            'name.max'      => 'Tên danh mục thưởng không quá 255 ký tự.',
        ];
    }
}
