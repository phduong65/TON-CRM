@php
    $metaHeaders = ['STT', 'Mã nhân viên', 'Tên', 'Chức danh'];
    $weekdayLabels = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];

    $isPartTime = $isPartTime ?? false;
    if ($isPartTime) {
        $summaryGroups = [
            ['label' => 'Tổng giờ làm', 'key' => 'worked_hours'],
            ['label' => 'Số lần không chấm công', 'key' => 'missing_total'],
            ['label' => 'Số lần không chấm công vào', 'key' => 'missing_check_in'],
            ['label' => 'Số lần không chấm công ra', 'key' => 'missing_check_out'],
            ['label' => 'Tổng giờ tăng ca', 'key' => 'overtime_hours'],
            ['label' => 'Thưởng nghỉ lễ', 'key' => 'holiday_bonus_amount'],
        ];
    } else {
        $summaryGroups = [
            ['label' => 'Ngày công thực tế', 'key' => 'actual_workdays'],
            ['label' => 'Ngày công thực tế nghỉ lễ', 'key' => 'holiday_workdays'],
            ['label' => 'Tổng ngày công thực tế', 'key' => 'total_actual_workdays'],
            ['label' => 'Tổng giờ làm', 'key' => 'worked_hours'],
            ['label' => 'Số ngày nghỉ có lương', 'key' => 'paid_leave_days'],
            ['label' => 'Số ngày nghỉ không lương', 'key' => 'unpaid_leave_days'],
            ['label' => 'Nghỉ lễ', 'key' => 'holiday_days'],
            ['label' => 'Công tính lương', 'key' => 'payroll_workdays'],
            ['label' => 'Số lần không chấm công', 'key' => 'missing_total'],
            ['label' => 'Số lần không chấm công vào', 'key' => 'missing_check_in'],
            ['label' => 'Số lần không chấm công ra', 'key' => 'missing_check_out'],
            ['label' => 'Công ca tăng ca', 'key' => 'overtime_shifts'],
            ['label' => 'Tổng giờ tăng ca', 'key' => 'overtime_hours'],
            ['label' => 'Thưởng nghỉ lễ', 'key' => 'holiday_bonus_amount'],
            ['label' => 'Công chuẩn', 'key' => 'standard_workdays'],
        ];
    }

    $totalCols = count($metaHeaders) + $days->count() + count($summaryGroups);
@endphp
<table>
    @include('exports.partials.banner', ['title' => 'BẢNG CHẤM CÔNG', 'subtitle' => $rangeLabel, 'colspan' => $totalCols])

    {{-- Header dòng 1 --}}
    <tr>
        @foreach($metaHeaders as $h)
            <td rowspan="2" style="background-color:#2563eb; color:#ffffff; font-weight:bold; font-size:11px; padding:6px 4px; text-align:center; border:1px solid #1d4ed8;">{{ $h }}</td>
        @endforeach

        @foreach($days as $day)
            <td style="background-color:#1e40af; color:#ffffff; font-weight:bold; font-size:10px; padding:4px 2px; text-align:center; border:1px solid #1d4ed8;">{{ $day->format('d/m') }}</td>
        @endforeach

        @foreach($summaryGroups as $g)
            <td rowspan="2" style="background-color:#2563eb; color:#ffffff; font-weight:bold; font-size:10px; padding:4px; text-align:center; border:1px solid #1d4ed8;">{{ $g['label'] }}</td>
        @endforeach
    </tr>

    {{-- Header dòng 2 --}}
    <tr>
        @foreach($days as $day)
            <td style="background-color:#dbeafe; color:#1e3a8a; font-size:9px; padding:3px 2px; text-align:center; border:1px solid #bfdbfe;">{{ $weekdayLabels[$day->dayOfWeek] }}</td>
        @endforeach
    </tr>

    {{-- Dữ liệu --}}
    @forelse($rows as $i => $row)
        @php $rowBg = $i % 2 === 0 ? '#ffffff' : '#f8fafc'; @endphp
        <tr>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $i + 1 }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $row['employee']->code }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px; font-weight:bold;">{{ $row['employee']->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $row['employee']->position?->name }}</td>

            @php $cellsToRender = $isPartTime ? $row['day_cells_hours'] : $row['day_cells']; @endphp
            @foreach($cellsToRender as $cell)
                <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:10px;">{{ $cell }}</td>
            @endforeach

            @foreach($summaryGroups as $g)
                <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; font-weight:bold;">{{ $row['summary'][$g['key']] }}</td>
            @endforeach
        </tr>
    @empty
        <tr>
            <td colspan="{{ $totalCols }}" style="text-align:center; padding:16px; color:#94a3b8; border:1px solid #e2e8f0;">
                Không có nhân viên phù hợp bộ lọc trong khoảng thời gian đã chọn.
            </td>
        </tr>
    @endforelse

    {{-- Ghi chú viết tắt --}}
    <tr><td colspan="{{ $totalCols }}" style="padding:6px; border:none;"></td></tr>
    <tr>
        <td colspan="{{ $totalCols }}" style="font-size:11px; font-weight:bold; padding:4px 2px; border:none; text-align:left;">Ghi chú các ký hiệu viết tắt trong bảng:</td>
    </tr>
    <tr>
        <td colspan="{{ $totalCols }}" style="font-size:10px; padding:2px 2px; border:none; text-align:left;">
            <b>NC</b> = Nghỉ có lương &nbsp;·&nbsp; <b>NK</b> = Nghỉ không lương &nbsp;·&nbsp; <b>NL</b> = Nghỉ lễ (có lương)
        </td>
    </tr>
    <tr>
        <td colspan="{{ $totalCols }}" style="font-size:10px; padding:2px 2px; border:none; text-align:left;">
            Ô trống = không có lịch làm việc/chưa đến ngày &nbsp;·&nbsp; Số đơn lẻ (VD "1") = công thực tế ngày đó
        </td>
    </tr>
    <tr>
        <td colspan="{{ $totalCols }}" style="font-size:10px; padding:2px 2px; border:none; text-align:left;">
            "X, NL" = đi làm đúng ngày nghỉ lễ, công thực tế X &nbsp;·&nbsp; "X, NC+Y" hoặc "X, NK+Y" = nghỉ theo giờ (nửa ca): công thực tế X + Y ngày phép (NC=có lương/NK=không lương)
        </td>
    </tr>
</table>
