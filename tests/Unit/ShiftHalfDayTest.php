<?php

namespace Tests\Unit;

use App\Models\Shift;
use PHPUnit\Framework\TestCase;

/**
 * Logic tách "nghỉ nửa ngày" theo GIỜ CÔNG thực của Shift: halfDaySplitTime() (điểm chia sáng/
 * chiều) và workMinutesInWindow() (số phút công trong 1 khung giờ, đã trừ giờ nghỉ giữa ca).
 * Không đụng DB — chỉ khởi tạo model in-memory nên kế thừa PHPUnit\TestCase cho nhẹ.
 */
class ShiftHalfDayTest extends TestCase
{
    private function shift(array $attrs): Shift
    {
        return new Shift(array_merge([
            'start_time'    => '09:00',
            'end_time'      => '18:00',
            'break_minutes' => 60,
        ], $attrs));
    }

    public function test_split_time_accounts_for_configured_break_position(): void
    {
        // Ca 09–18, nghỉ 12:00–13:00 → 8h công, nửa = 4h → sáng 09–14, điểm chia 14:00.
        $shift = $this->shift(['break_start_time' => '12:00']);

        $this->assertEquals('14:00', $shift->halfDaySplitTime());
    }

    public function test_split_time_falls_back_to_work_midpoint_when_break_position_unknown(): void
    {
        // Không cấu hình giờ nghỉ cụ thể → chia tại nửa giờ công tính từ đầu ca: 09:00 + 240' = 13:00.
        $shift = $this->shift(['break_start_time' => null]);

        $this->assertEquals('13:00', $shift->halfDaySplitTime());
    }

    public function test_split_time_with_no_break_is_plain_midpoint(): void
    {
        // Ca 08–12 không nghỉ → nửa = 2h → 10:00.
        $shift = $this->shift(['start_time' => '08:00', 'end_time' => '12:00', 'break_minutes' => 0, 'break_start_time' => null]);

        $this->assertEquals('10:00', $shift->halfDaySplitTime());
    }

    public function test_work_minutes_subtracts_break_overlap(): void
    {
        $shift = $this->shift(['break_start_time' => '12:00']);

        // 09:00–14:00 = 300' đồng hồ − 60' nghỉ trùng = 240' công.
        $this->assertEquals(240, $shift->workMinutesInWindow('09:00', '14:00'));
        // 14:00–18:00 không trùng giờ nghỉ = 240' công.
        $this->assertEquals(240, $shift->workMinutesInWindow('14:00', '18:00'));
        // Cả ca 09:00–18:00 = 540' − 60' = 480' công.
        $this->assertEquals(480, $shift->workMinutesInWindow('09:00', '18:00'));
    }

    public function test_work_minutes_without_break_position_does_not_subtract(): void
    {
        // break_minutes = 60 nhưng chưa biết vị trí → không trừ khỏi khung cụ thể.
        $shift = $this->shift(['break_start_time' => null]);

        $this->assertEquals(180, $shift->workMinutesInWindow('09:00', '12:00'));
    }
}
