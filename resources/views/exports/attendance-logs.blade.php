@php
    $headers = ['STT', 'Ngày', 'Nhân viên', 'Mã NV', 'Chi nhánh', 'Đội nhóm', 'Ca', 'Check-in', 'Check-out', 'Trễ (phút)', 'Sớm (phút)', 'Phương thức'];
@endphp
<table>
    @include('exports.partials.banner', ['title' => 'BÁO CÁO CHẤM CÔNG', 'subtitle' => $rangeLabel, 'colspan' => count($headers)])

    <tr>
        @foreach($headers as $h)
            <td style="background-color:#2563eb; color:#ffffff; font-weight:bold; font-size:12px; padding:6px 8px; text-align:center; border:1px solid #1d4ed8;">
                {{ $h }}
            </td>
        @endforeach
    </tr>

    @forelse($logs as $i => $log)
        @php
            $rowBg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
            $onTime = $log->late_minutes == 0 && $log->early_minutes == 0;
        @endphp
        <tr>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $i + 1 }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $log->work_date->format('d/m/Y') }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px; font-weight:bold;">{{ $log->employee?->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $log->employee?->code }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $log->employee?->branch?->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; font-size:11px;">{{ $log->employee?->team?->name }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $log->shiftSchedule?->shift?->name ?? '—' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $log->check_in_at?->format('H:i:s') ?? '—' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px;">{{ $log->check_out_at?->format('H:i:s') ?? '—' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; color:{{ $log->late_minutes > 0 ? '#b45309' : '#94a3b8' }};">{{ $log->late_minutes ?: '' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; color:{{ $log->early_minutes > 0 ? '#b45309' : '#94a3b8' }};">{{ $log->early_minutes ?: '' }}</td>
            <td style="background-color:{{ $rowBg }}; border:1px solid #e2e8f0; text-align:center; font-size:11px; color:{{ $onTime ? '#15803d' : '#b45309' }};">
                {{ $log->check_in_method ? strtoupper($log->check_in_method) : '—' }}
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($headers) }}" style="text-align:center; padding:16px; color:#94a3b8; border:1px solid #e2e8f0;">
                Không có dữ liệu chấm công trong khoảng thời gian đã chọn.
            </td>
        </tr>
    @endforelse
</table>
