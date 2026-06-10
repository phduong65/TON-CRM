<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'violation_id'              => 'required|exists:violations,id',
            'employee_id'               => 'required|exists:employees,id',
            'points_deducted'           => 'required|integer|min:0|max:100',
            'money_deducted'            => 'nullable|numeric|min:0',
            'description'               => 'nullable|string|max:1000',
            'members'                   => 'nullable|array|max:20',
            'members.*.employee_id'     => 'required|exists:employees,id',
            'members.*.points_deducted' => 'required|integer|min:0|max:100',
            'members.*.money_deducted'  => 'nullable|numeric|min:0',
            'members.*.note'            => 'nullable|string|max:500',
            'attachments'               => 'nullable|array|max:10',
            'attachments.*'             => [
                'nullable',
                'file',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$value instanceof \Illuminate\Http\UploadedFile || !$value->isValid()) {
                        return;
                    }

                    $ext  = strtolower($value->getClientOriginalExtension());
                    $mime = $value->getMimeType() ?? '';

                    $imageExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];
                    $videoExts  = ['mp4', 'mov', 'avi', 'webm'];
                    $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];
                    $videoMimes = ['video/mp4', 'video/quicktime', 'video/avi', 'video/webm', 'video/x-msvideo'];

                    $isImage = in_array($ext, $imageExts) || in_array($mime, $imageMimes);
                    $isVideo = in_array($ext, $videoExts) || in_array($mime, $videoMimes);

                    if (!$isImage && !$isVideo) {
                        $fail('Chỉ chấp nhận ảnh (jpg/png/gif/webp/heic) hoặc video (mp4/mov/avi/webm).');
                        return;
                    }

                    $maxBytes = $isVideo ? 20 * 1024 * 1024 : 10 * 1024 * 1024;
                    if ($value->getSize() > $maxBytes) {
                        $fail($isVideo ? 'Video không được vượt quá 20MB.' : 'Ảnh không được vượt quá 10MB.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'violation_id.required'          => 'Vui lòng chọn vi phạm.',
            'employee_id.required'           => 'Vui lòng chọn nhân viên vi phạm.',
            'points_deducted.required'       => 'Vui lòng nhập số điểm trừ.',
            'points_deducted.max'            => 'Điểm trừ không vượt quá 100.',
            'members.*.employee_id.required' => 'Vui lòng chọn nhân viên liên đới.',
        ];
    }
}
