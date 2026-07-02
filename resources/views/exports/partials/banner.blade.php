{{-- Banner dùng chung cho các file Excel xuất ra — 1 dòng tiêu đề + 1 dòng phụ đề, merge theo colspan --}}
<tr>
    <td colspan="{{ $colspan }}" style="background-color:#1d4ed8; color:#ffffff; font-size:16px; font-weight:bold; padding:10px 8px; text-align:left;">
        {{ $title }}
    </td>
</tr>
<tr>
    <td colspan="{{ $colspan }}" style="background-color:#eff6ff; color:#1e3a8a; font-size:11px; padding:6px 8px; text-align:left;">
        {{ $subtitle }} &nbsp;·&nbsp; Xuất lúc: {{ now()->format('d/m/Y H:i') }}
    </td>
</tr>
<tr>
    <td colspan="{{ $colspan }}" style="padding:2px; border:none;"></td>
</tr>
