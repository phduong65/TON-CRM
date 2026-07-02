@php
    $headers = ['STT', 'Ngày', 'Thứ', 'Loại', 'Nội dung', 'Giờ làm', 'Trạng thái', 'Check-in', 'Check-out', 'Trễ (phút)', 'Sớm (phút)'];
    $weekdayLabels = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
    $statusColors = [
        'Đã hoàn thành'  => '#15803d',
        'Đang trong ca'  => '#0369a1',
        'Chưa chấm công' => '#b91c1c',
        'Sắp tới'        => '#64748b',
        'Đã duyệt'       => '#64748b',
    ];
@endphp
<table>
    @include('exports.partials.banner', [
        'title' => 'LỊCH LÀM VIỆC CÁ NHÂN — ' . mb_strtoupper($employee->name),
        'subtitle' => $rangeLabel,
        'colspan' => count($headers),
    ])

    <tr>
        @foreach($headers as $h)
            <td style="background-color:#2563eb; color:#ffffff; font-weight:bold; font-size:12px; padding:6px 8px; text-align:center; border:1px solid #1d4ed8;">
                {{ $h }}
            </td>
        @endforeach
    </tr>

    @forelse($rows as $i => $row)
        @php $rowBg = $i % 2 === 0 ? '#ffffff' : '#f8fafc'; @endphp
        <tr>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $i + 1 }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $row['date']->format('d/m/Y') }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $weekdayLabels[$row['date']->dayOfWeek] }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $row['type'] }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px; font-weight:bold;">{{ $row['label'] }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $row['time'] }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; font-weight:bold; color:{{ $statusColors[$row['status']] ?? '#64748b' }};">{{ $row['status'] }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $row['checkIn'] ?? '—' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $row['checkOut'] ?? '—' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $row['lateMinutes'] ?: '' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $row['earlyMinutes'] ?: '' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($headers) }}" style="text-align:center; padding:16px; color:#94a3b8; border:1px solid #e2e8f0;">
                Không có dữ liệu trong khoảng thời gian đã chọn.
            </td>
        </tr>
    @endforelse
</table>
