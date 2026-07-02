@php
    $metaHeaders = ['STT', 'Mã nhân viên', 'Tên', 'Chi nhánh', 'Phòng ban', 'Chức danh'];
    $weekdayLabels = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];

    // Mỗi nhóm cột tổng hợp: paired = true nghĩa là có 2 cột con "Chính thức" / "Thử việc".
    // Hệ thống chưa lưu trạng thái Thử việc / lịch Nghỉ lễ / tăng ca — các cột này luôn trả về 0,
    // giữ nguyên bố cục để đối chiếu với file mẫu.
    $summaryGroups = [
        ['label' => 'Ngày công thực tế', 'key' => 'actual_workdays', 'paired' => true],
        ['label' => 'Ngày công thực tế nghỉ lễ', 'key' => 'holiday_workdays', 'paired' => true],
        ['label' => 'Tổng ngày công thực tế', 'key' => 'total_actual_workdays', 'paired' => true],
        ['label' => 'Số ngày nghỉ có lương', 'key' => 'paid_leave_days', 'paired' => true],
        ['label' => 'Số ngày nghỉ không lương', 'key' => 'unpaid_leave_days', 'paired' => true],
        ['label' => 'Nghỉ lễ', 'key' => 'holiday_days', 'paired' => true],
        ['label' => 'Công tính lương', 'key' => 'payroll_workdays', 'paired' => true],
        ['label' => 'Số lần đi muộn', 'key' => 'late_count', 'paired' => true],
        ['label' => 'Số lần về sớm', 'key' => 'early_count', 'paired' => true],
        ['label' => 'Số lần không chấm công', 'key' => 'missing_total', 'paired' => true],
        ['label' => 'Số lần không chấm công vào', 'key' => 'missing_check_in', 'paired' => true],
        ['label' => 'Số lần không chấm công ra', 'key' => 'missing_check_out', 'paired' => true],
        ['label' => 'Công ca tăng ca', 'key' => 'overtime_shifts', 'paired' => true],
        ['label' => 'Tổng giờ tăng ca', 'key' => 'overtime_hours', 'paired' => true],
        ['label' => 'Tổng giờ làm thêm giờ', 'key' => 'extra_hours', 'paired' => false],
        ['label' => 'Thưởng nghỉ lễ', 'key' => 'holiday_bonus_amount', 'paired' => false],
    ];

    $pairedCols = collect($summaryGroups)->sum(fn($g) => $g['paired'] ? 2 : 1);
    $totalCols  = count($metaHeaders) + $days->count() + $pairedCols + 1; // +1 = cột Công chuẩn
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
            <td colspan="{{ $g['paired'] ? 2 : 1 }}" @if(!$g['paired']) rowspan="2" @endif
                style="background-color:#2563eb; color:#ffffff; font-weight:bold; font-size:10px; padding:4px; text-align:center; border:1px solid #1d4ed8;">{{ $g['label'] }}</td>
        @endforeach

        <td rowspan="2" style="background-color:#2563eb; color:#ffffff; font-weight:bold; font-size:10px; padding:4px; text-align:center; border:1px solid #1d4ed8;">Công chuẩn</td>
    </tr>

    {{-- Header dòng 2 --}}
    <tr>
        @foreach($days as $day)
            <td style="background-color:#dbeafe; color:#1e3a8a; font-size:9px; padding:3px 2px; text-align:center; border:1px solid #bfdbfe;">{{ $weekdayLabels[$day->dayOfWeek] }}</td>
        @endforeach

        @foreach($summaryGroups as $g)
            @if($g['paired'])
                <td style="background-color:#dbeafe; color:#1e3a8a; font-size:9px; padding:3px; text-align:center; border:1px solid #bfdbfe;">Chính thức</td>
                <td style="background-color:#dbeafe; color:#1e3a8a; font-size:9px; padding:3px; text-align:center; border:1px solid #bfdbfe;">Thử việc</td>
            @endif
        @endforeach
    </tr>

    {{-- Dữ liệu --}}
    @forelse($rows as $i => $row)
        @php $rowBg = $i % 2 === 0 ? '#ffffff' : '#f8fafc'; @endphp
        <tr>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $i + 1 }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $row['employee']->code }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px; font-weight:bold;">{{ $row['employee']->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $row['employee']->branch?->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $row['employee']->team?->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $row['employee']->position }}</td>

            @foreach($row['day_cells'] as $cell)
                <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:10px;">{{ $cell }}</td>
            @endforeach

            @foreach($summaryGroups as $g)
                <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; font-weight:bold;">{{ $row['summary'][$g['key']] }}</td>
                @if($g['paired'])
                    <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; color:#94a3b8;">0</td>
                @endif
            @endforeach

            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $standardWorkdays }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ $totalCols }}" style="text-align:center; padding:16px; color:#94a3b8; border:1px solid #e2e8f0;">
                Không có nhân viên phù hợp bộ lọc trong khoảng thời gian đã chọn.
            </td>
        </tr>
    @endforelse
</table>
