<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Setting;

/**
 * Chặn truy cập trực tiếp vào các route "Xác nhận công" khi admin chưa bật tính năng này
 * trong Cài đặt (timesheet_confirmation_enabled) — không chỉ ẩn link sidebar.
 */
trait RequiresTimesheetConfirmationEnabled
{
    protected function ensureFeatureEnabled(): void
    {
        abort_unless(Setting::getValue('timesheet_confirmation_enabled', '0') === '1', 404);
    }
}
