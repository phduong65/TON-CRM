@php
    $headers = ['STT', 'Nhân viên', 'Mã NV', 'Chi nhánh', 'Đội nhóm', 'Ngày', 'Thứ', 'Ca', 'Giờ làm', 'WFH', 'Loại xếp', 'Ghi chú', 'Người xếp', 'Check-in', 'Check-out', 'Trễ (phút)', 'Sớm (phút)'];
    $weekdayLabels = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
@endphp
<table>
    @include('exports.partials.banner', ['title' => 'BÁO CÁO XẾP CA LÀM VIỆC', 'subtitle' => $rangeLabel, 'colspan' => count($headers)])

    <tr>
        @foreach($headers as $h)
            <td style="background-color:#2563eb; color:#ffffff; font-weight:bold; font-size:12px; padding:6px 8px; text-align:center; border:1px solid #1d4ed8;">
                {{ $h }}
            </td>
        @endforeach
    </tr>

    @forelse($schedules as $i => $s)
        @php $rowBg = $i % 2 === 0 ? '#ffffff' : '#f8fafc'; @endphp
        <tr>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $i + 1 }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px; font-weight:bold;">{{ $s->employee?->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $s->employee?->code }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $s->employee?->branch?->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $s->employee?->team?->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $s->work_date->format('d/m/Y') }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $weekdayLabels[$s->work_date->dayOfWeek] }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $s->shift?->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ substr($s->shift?->start_time,0,5) }}–{{ substr($s->shift?->end_time,0,5) }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; color:{{ $s->shift?->isWfh() ? '#0369a1' : '#94a3b8' }};">{{ $s->shift?->isWfh() ? 'WFH' : '—' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $s->assignment_type === 'fixed' ? 'Cố định' : 'Đa ca' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $s->note }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $s->assignedBy?->name ?? 'Tự động' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; color:{{ $s->attendanceLog?->late_minutes > 0 ? '#b45309' : '#15803d' }};">
                {{ $s->attendanceLog?->check_in_at?->format('H:i:s') ?? '—' }}
            </td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; color:{{ $s->attendanceLog?->early_minutes > 0 ? '#b45309' : '#15803d' }};">
                {{ $s->attendanceLog?->check_out_at?->format('H:i:s') ?? '—' }}
            </td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $s->attendanceLog?->late_minutes ?: '' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $s->attendanceLog?->early_minutes ?: '' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($headers) }}" style="text-align:center; padding:16px; color:#94a3b8; border:1px solid #e2e8f0;">
                Không có dữ liệu xếp ca trong khoảng thời gian đã chọn.
            </td>
        </tr>
    @endforelse
</table>
