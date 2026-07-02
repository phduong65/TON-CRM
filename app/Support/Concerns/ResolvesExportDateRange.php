<?php

namespace App\Support\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

trait ResolvesExportDateRange
{
    /**
     * Xác định khoảng ngày xuất Excel theo range_type (week/month/custom).
     * ref_date là ngày mốc để tính "tuần"/"tháng" (mặc định hôm nay, hoặc tuần/tháng đang xem trên trang).
     *
     * @return array{0: Carbon, 1: Carbon, 2: string} [from, to, label hiển thị]
     */
    protected function resolveExportDateRange(Request $request): array
    {
        $rangeType = $request->get('range_type', 'week');
        $refDate   = $request->filled('ref_date') ? Carbon::parse($request->ref_date) : now();

        if ($rangeType === 'month') {
            $from = $refDate->copy()->startOfMonth();
            $to   = $refDate->copy()->endOfMonth();

            return [$from, $to, 'Tháng ' . $from->format('m/Y')];
        }

        if ($rangeType === 'custom') {
            $from = $request->filled('date_from') ? Carbon::parse($request->date_from) : $refDate->copy()->startOfWeek(Carbon::MONDAY);
            $to   = $request->filled('date_to') ? Carbon::parse($request->date_to) : $refDate->copy()->endOfWeek(Carbon::SUNDAY);

            return [$from, $to, $from->format('d/m/Y') . ' – ' . $to->format('d/m/Y')];
        }

        // Mặc định: tuần (Thứ 2 → Chủ nhật) chứa ref_date
        $from = $refDate->copy()->startOfWeek(Carbon::MONDAY);
        $to   = $refDate->copy()->endOfWeek(Carbon::SUNDAY);

        return [$from, $to, 'Tuần ' . $from->format('d/m') . '–' . $to->format('d/m/Y')];
    }
}
