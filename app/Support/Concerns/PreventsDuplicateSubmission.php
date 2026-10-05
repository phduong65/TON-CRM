<?php

namespace App\Support\Concerns;

trait PreventsDuplicateSubmission
{
    /**
     * True nếu đã tồn tại bản ghi khớp $conditions được tạo trong $withinSeconds giây gần nhất —
     * dùng để chặn double-submit (double-click / bấm gửi lại khi mạng chậm) tạo trùng 2 bản ghi
     * giống hệt nhau. Không dùng unique constraint/DB lock vì mỗi lần tạo đều sinh "code" mới
     * (không trùng) — bản thân bản ghi vẫn hợp lệ về mặt dữ liệu, chỉ là KHÔNG mong muốn có 2 cái
     * giống nhau do cùng 1 lần người dùng bấm gửi. Gọi TRƯỚC khi tạo bản ghi mới; nếu true, coi
     * như đã xử lý xong (trả về thành công) thay vì tạo thêm.
     */
    protected function wasJustSubmitted(string $modelClass, array $conditions, int $withinSeconds = 10): bool
    {
        return $this->findJustSubmitted($modelClass, $conditions, $withinSeconds) !== null;
    }

    /**
     * Như wasJustSubmitted() nhưng trả về bản ghi trùng tìm được (mới nhất) thay vì bool — dùng
     * khi cần tham chiếu lại bản ghi đó (VD redirect về đúng trang chi tiết của lần tạo đầu).
     */
    protected function findJustSubmitted(string $modelClass, array $conditions, int $withinSeconds = 10)
    {
        return $modelClass::where($conditions)
            ->where('created_at', '>=', now()->subSeconds($withinSeconds))
            ->latest('id')
            ->first();
    }
}
