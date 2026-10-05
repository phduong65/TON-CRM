@props([
    'type' => 'primary', // 'primary' (full horizontal) or 'submark' (icon square)
    'option' => null,   // '2-a', '2-b', '2-c', '1', '2', '3'
    'class' => '',
])

@php
    $selectedOption = $option ?? \App\Models\Setting::getValue('tonhrm_logo_option', '2-a');
    $submarkFile = "assets/images/logos/tonhrm-submark-opt{$selectedOption}.svg";

    // Icon nhúng inline (thay vì <img>) để: bỏ ô nền + bóng đổ của file SVG gốc, và nét màu tối
    // đổi theo màu chữ (currentColor) → vẫn thấy rõ trên topbar tối ở dark mode.
    // ID gradient được đánh hậu tố riêng mỗi lần render: topbar render logo 2 lần (mobile/desktop),
    // trùng ID trong bản đang bị ẩn sẽ làm gradient của bản còn lại không hiển thị.
    $submarkSvg = null;
    $submarkPath = public_path($submarkFile);
    if (is_file($submarkPath)) {
        $svg = file_get_contents($submarkPath);
        $svg = preg_replace('/<rect x="2" y="2" width="48" height="48"[^>]*\/>\s*/', '', $svg);
        $svg = preg_replace('/\sfilter="url\(#[^)]+\)"/', '', $svg);
        $svg = str_replace('fill="#0F172A"', 'fill="currentColor"', $svg);
        $uid = 'l' . substr(md5(uniqid('', true)), 0, 6);
        $svg = preg_replace('/\bid="([^"]+)"/', 'id="$1-' . $uid . '"', $svg);
        $svg = preg_replace('/url\(#([^)]+)\)/', 'url(#$1-' . $uid . ')', $svg);
        $svg = preg_replace('/<svg\b/', '<svg aria-hidden="true" focusable="false"', $svg, 1);
        $submarkSvg = $svg;
    }
@endphp

@if($type === 'submark')
    @if ($submarkSvg)
        <span role="img" aria-label="TonHRM" class="inline-block shrink-0 text-slate-900 dark:text-white {{ $class ?: 'w-9 h-9' }}">{!! $submarkSvg !!}</span>
    @else
        <img src="{{ asset($submarkFile) }}" alt="TonHRM Icon" class="shrink-0 {{ $class ?: 'w-9 h-9' }}" loading="eager">
    @endif
@else
    {{-- Full primary brand mark with guaranteed Bricolage Grotesque rendering --}}
    <div class="inline-flex items-center gap-2 select-none {{ $class }}">
        @if ($submarkSvg)
            <span class="inline-block w-10 h-10 shrink-0 text-slate-900 dark:text-white" aria-hidden="true">{!! $submarkSvg !!}</span>
        @else
            <img src="{{ asset($submarkFile) }}" alt="" class="w-10 h-10 shrink-0" loading="eager">
        @endif
        <div class="flex flex-col justify-center leading-none">
            <div class="flex items-baseline leading-none" aria-label="TonHRM">
                <span class="font-heading font-extrabold text-[22px] tracking-[-0.03em] text-slate-950 dark:text-white">Ton</span>
                <span class="font-heading font-extrabold text-[22px] tracking-[-0.02em] ml-0.5 text-[#2F55E7] dark:text-[#809ff9]">HRM</span>
            </div>
            <span class="text-[9px] font-bold tracking-[0.16em] text-slate-400 dark:text-slate-400 uppercase mt-1 leading-none">
                HUMAN RESOURCE HUB
            </span>
        </div>
    </div>
@endif
