<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRewardCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit-reward-categories');
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255', Rule::unique('reward_categories', 'name')->ignore($this->rewardCategory)],
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
