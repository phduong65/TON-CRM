<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Nội quy Thưởng &amp; Phạt áp dụng toàn bộ nhân sự {{ $companyName }} — tra cứu danh mục xử phạt kỷ luật và khen thưởng thành tích.">
    <title>Nội Quy Thưởng &amp; Phạt — {{ $companyName }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    @vite(['resources/css/app.css'])

    <style>
        @media print {
            .no-print { display: none !important; }
            .policy-panel { max-height: none !important; opacity: 1 !important; }
            body { background: #fff; }
        }
        .policy-reveal { opacity: 0; transform: translateY(14px); transition: opacity .5s ease, transform .5s ease; }
        .policy-reveal.is-visible { opacity: 1; transform: translateY(0); }
        @media (prefers-reduced-motion: reduce) {
            .policy-reveal { opacity: 1; transform: none; transition: none; }
        }
        .policy-panel { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .3s ease; }
        .policy-panel > div { overflow: hidden; }
        .policy-panel.is-open { grid-template-rows: 1fr; }
        .policy-chevron { transition: transform .25s ease; }
        .is-open .policy-chevron { transform: rotate(180deg); }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 antialiased" style="font-family:'Be Vietnam Pro',ui-sans-serif,system-ui,sans-serif;">

    {{-- ========== TOP STRIP — thể thức văn bản hành chính ========== --}}
    <div class="no-print bg-slate-900 text-slate-300 text-xs">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-2 flex flex-wrap items-center justify-between gap-x-6 gap-y-1">
            <div class="flex items-center gap-4">
                <span class="font-semibold text-white uppercase tracking-wide">{{ $companyName }}</span>
                <span class="text-slate-500">Số: 01/2026/NQ-TC</span>
            </div>
            <div class="text-slate-400">Cộng hòa Xã hội Chủ nghĩa Việt Nam — Độc lập, Tự do, Hạnh phúc</div>
        </div>
    </div>

    {{-- ========== STICKY SEARCH BAR ========== --}}
    <div class="no-print sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center gap-3">
            <div class="relative flex-1 min-w-0">
                <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
                <input type="text" id="policySearch"
                       class="w-full h-11 pl-10 pr-4 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-pcrm-500 focus:border-pcrm-500"
                       placeholder="Tra cứu lỗi hoặc hạng mục thưởng — ví dụ: đi trễ, chấm công, KPI...">
            </div>
            <button type="button" onclick="window.print()"
                    class="hidden sm:inline-flex h-11 px-4 items-center gap-2 rounded-xl border border-slate-200 text-sm font-medium text-slate-600 hover:bg-slate-50 shrink-0">
                <i class="bi bi-printer"></i> In
            </button>
        </div>
    </div>

    {{-- ========== HERO ========== --}}
    <header class="max-w-5xl mx-auto px-4 sm:px-6 pt-14 pb-10 text-center">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">
            Nội Quy Thưởng &amp; Phạt<br class="hidden sm:block"> Nhân Viên
        </h1>
        <p class="mt-4 max-w-2xl mx-auto text-base text-slate-500 leading-relaxed">
            Áp dụng cho toàn bộ nhân sự cơ sở và khối văn phòng {{ $companyName }}, hiệu lực từ ngày 01/01/2026.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <div class="inline-flex items-center gap-2 rounded-full bg-pcrm-50 text-pcrm-700 px-4 py-2 text-sm font-semibold">
                <i class="bi bi-stars"></i> {{ $defaultPoints }} điểm / tháng / nhân viên
            </div>
            <div class="inline-flex items-center gap-2 rounded-full bg-red-50 text-red-700 px-4 py-2 text-sm font-semibold">
                <i class="bi bi-exclamation-octagon"></i> {{ $violationCount }} hành vi vi phạm
            </div>
            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-700 px-4 py-2 text-sm font-semibold">
                <i class="bi bi-trophy"></i> {{ $rewardCount }} hạng mục khen thưởng
            </div>
        </div>

        <div class="no-print mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="#xu-phat" class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-pcrm-600 text-white text-sm font-semibold hover:bg-pcrm-700 transition-colors">
                Xem danh mục xử phạt
            </a>
            <a href="#khen-thuong" class="inline-flex items-center gap-2 h-11 px-5 rounded-xl border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition-colors">
                Xem danh mục khen thưởng
            </a>
        </div>
    </header>

    {{-- ========== ĐIỂM HÀNH VI — ZONE ========== --}}
    <section class="policy-reveal max-w-5xl mx-auto px-4 sm:px-6 pb-14">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
            <h2 class="text-lg font-bold text-slate-900">Cơ Chế Điểm Hành Vi</h2>
            <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">
                Hệ thống cấp {{ $defaultPoints }} điểm vào ngày 1 hàng tháng cho mỗi nhân sự. Điểm số biến động dựa trên biểu hành vi thực tế và là căn cứ xếp hạng hiệu suất cuối kỳ.
            </p>

            <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 text-center">
                    <div class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Greenzone</div>
                    <div class="mt-1 text-xl font-extrabold text-emerald-700">{{ $zones['green'] }}&ndash;100đ</div>
                    <div class="mt-1 text-xs text-slate-500">Xuất sắc, đạt xét thưởng</div>
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-4 text-center">
                    <div class="text-xs font-semibold text-amber-700 uppercase tracking-wide">Yellowzone</div>
                    <div class="mt-1 text-xl font-extrabold text-amber-700">{{ $zones['yellow'] }}&ndash;{{ $zones['green'] - 1 }}đ</div>
                    <div class="mt-1 text-xs text-slate-500">Tiêu chuẩn, ổn định</div>
                </div>
                <div class="rounded-xl border border-orange-100 bg-orange-50/60 p-4 text-center">
                    <div class="text-xs font-semibold text-orange-700 uppercase tracking-wide">Orangezone</div>
                    <div class="mt-1 text-xl font-extrabold text-orange-700">{{ $zones['orange'] }}&ndash;{{ $zones['yellow'] - 1 }}đ</div>
                    <div class="mt-1 text-xs text-slate-500">Yếu, cần cải thiện</div>
                </div>
                <div class="rounded-xl border border-red-100 bg-red-50/60 p-4 text-center">
                    <div class="text-xs font-semibold text-red-700 uppercase tracking-wide">Redzone</div>
                    <div class="mt-1 text-xl font-extrabold text-red-700">&lt; {{ $zones['orange'] }}đ</div>
                    <div class="mt-1 text-xs text-slate-500">Báo động, xử lý kỷ luật</div>
                </div>
            </div>
        </div>
    </section>

    @php
        $severityLabels = [
            'low'      => 'Nhẹ',
            'medium'   => 'Trung bình',
            'high'     => 'Nặng',
            'critical' => 'Nghiêm trọng',
            'extreme'  => 'Đặc biệt NT',
        ];
        $severityBadges = [
            'low'      => 'bg-slate-100 text-slate-600',
            'medium'   => 'bg-amber-100 text-amber-700',
            'high'     => 'bg-orange-100 text-orange-700',
            'critical' => 'bg-red-100 text-red-700',
            'extreme'  => 'bg-rose-200 text-rose-900',
        ];
    @endphp

    {{-- ========== PHẦN II — XỬ PHẠT ========== --}}
    <section id="xu-phat" class="policy-reveal max-w-5xl mx-auto px-4 sm:px-6 pb-14 scroll-mt-20">
        <div class="flex items-center gap-3 mb-2">
            <span class="w-9 h-9 rounded-lg bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                <i class="bi bi-exclamation-octagon-fill"></i>
            </span>
            <h2 class="text-xl font-bold text-slate-900">Danh Mục Xử Phạt Kỷ Luật</h2>
        </div>
        <p class="text-sm text-slate-500 leading-relaxed mb-6">
            {{ $regulations->count() }} quy chế, {{ $violationCount }} hành vi vi phạm — nhấn vào từng quy chế để xem chi tiết mức khấu trừ điểm và phạt tiền (nếu có).
        </p>

        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-3">
            <strong>Lưu ý bồi hoàn tài sản:</strong> khung xử lý dưới đây là mức cơ bản. Hành vi gây hư hỏng/mất mát thiết bị, nhân sự chịu trách nhiệm bồi thường theo giá trị thẩm định thực tế của hội đồng kỹ thuật.
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 mb-6">
            <strong>Quan trọng:</strong> không báo cáo hoặc cố tình che giấu sai phạm của bản thân hoặc đồng nghiệp bị trừ <strong>5 điểm/người</strong>.
        </div>

        <div class="space-y-3" id="violationGroups">
            @foreach($regulations as $i => $regulation)
            <div class="policy-group rounded-xl border border-slate-200 bg-white overflow-hidden">
                <button type="button" onclick="togglePanel('vio-panel-{{ $regulation->id }}')"
                        class="policy-toggle {{ $i === 0 ? 'is-open' : '' }} w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 transition-colors">
                    <span class="flex items-center gap-3 min-w-0">
                        <span class="shrink-0 w-7 h-7 rounded-lg bg-red-50 text-red-600 text-xs font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-slate-800 truncate">{{ $regulation->name }}</span>
                            <span class="block text-xs text-slate-400 mt-0.5">{{ $regulation->violations->count() }} hành vi</span>
                        </span>
                    </span>
                    <i class="bi bi-chevron-down policy-chevron text-slate-400 shrink-0"></i>
                </button>
                <div id="vio-panel-{{ $regulation->id }}" class="policy-panel {{ $i === 0 ? 'is-open' : '' }}">
                    <div>
                        @php $hasMoney = $regulation->violations->contains(fn($v) => $v->penalty_type === 'both'); @endphp
                        <div class="table-container border-0 rounded-none border-t border-slate-100">
                            <table class="table-base">
                                <thead>
                                    <tr>
                                        <th class="table-th">Hành vi vi phạm</th>
                                        <th class="table-th">Mô tả chi tiết</th>
                                        <th class="table-th w-28 text-center">Mức độ</th>
                                        @if($hasMoney)
                                        <th class="table-th text-right">Phạt tiền</th>
                                        @endif
                                        <th class="table-th text-right">Khấu trừ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($regulation->violations as $violation)
                                    <tr class="policy-row table-tr-hover">
                                        <td class="table-td whitespace-normal font-medium text-slate-800">{{ $violation->name }}</td>
                                        <td class="table-td whitespace-normal text-xs text-slate-500">{{ $violation->description }}</td>
                                        <td class="table-td text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $severityBadges[$violation->severity] ?? 'bg-slate-100 text-slate-600' }}">
                                                {{ $severityLabels[$violation->severity] ?? $violation->severity }}
                                            </span>
                                        </td>
                                        @if($hasMoney)
                                        <td class="table-td text-right font-semibold text-amber-700">
                                            @if($violation->penalty_type === 'both')
                                                @if((float) $violation->money_deducted > 0)
                                                    {{ number_format((float) $violation->money_deducted, 0, ',', '.') }}đ
                                                @else
                                                    Theo GTTT
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        @endif
                                        <td class="table-td text-right font-bold text-red-600">&minus;{{ $violation->points_deducted }}đ</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <p id="violationNoResult" class="hidden text-center text-sm text-slate-400 py-8">
            <i class="bi bi-search text-2xl block mb-2"></i> Không tìm thấy hành vi phù hợp.
        </p>
    </section>

    {{-- ========== PHẦN III — KHEN THƯỞNG ========== --}}
    <section id="khen-thuong" class="policy-reveal max-w-5xl mx-auto px-4 sm:px-6 pb-14 scroll-mt-20">
        <div class="flex items-center gap-3 mb-2">
            <span class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                <i class="bi bi-trophy-fill"></i>
            </span>
            <h2 class="text-xl font-bold text-slate-900">Danh Mục Khen Thưởng Thành Tích</h2>
        </div>
        <p class="text-sm text-slate-500 leading-relaxed mb-6">
            {{ $rewardCategories->count() }} bộ phận, {{ $rewardCount }} hạng mục thành tích — điểm thưởng chỉ ghi nhận khi kết quả đo lường được và quản lý trực tiếp phê duyệt.
        </p>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 mb-6">
            Nhân sự đạt từ <strong>{{ $defaultPoints }} điểm</strong> trở lên được ghi tên lên <strong>Bảng Xếp Hạng Nhân Viên Xuất Sắc</strong>. Người điểm cao nhất mỗi tháng nhận danh hiệu <strong>Nhân Viên Của Tháng</strong>.
        </div>

        <div class="space-y-3" id="rewardGroups">
            @foreach($rewardCategories as $i => $category)
            <div class="policy-group rounded-xl border border-slate-200 bg-white overflow-hidden">
                <button type="button" onclick="togglePanel('rew-panel-{{ $category->id }}')"
                        class="policy-toggle w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 transition-colors">
                    <span class="flex items-center gap-3 min-w-0">
                        <span class="shrink-0 w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 text-xs font-bold flex items-center justify-center">
                            <i class="bi bi-award"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-slate-800 truncate">{{ $category->name }}</span>
                            <span class="block text-xs text-slate-400 mt-0.5">{{ $category->rewardTypes->count() }} hạng mục</span>
                        </span>
                    </span>
                    <i class="bi bi-chevron-down policy-chevron text-slate-400 shrink-0"></i>
                </button>
                <div id="rew-panel-{{ $category->id }}" class="policy-panel">
                    <div>
                        <div class="table-container border-0 rounded-none border-t border-slate-100">
                            <table class="table-base">
                                <thead>
                                    <tr>
                                        <th class="table-th">Hạng mục thành tích</th>
                                        <th class="table-th">Tiêu chí &amp; điều kiện đạt chuẩn</th>
                                        <th class="table-th text-right">Điểm thưởng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($category->rewardTypes as $rewardType)
                                    <tr class="policy-row table-tr-hover">
                                        <td class="table-td whitespace-normal font-medium text-slate-800">{{ $rewardType->name }}</td>
                                        <td class="table-td whitespace-normal text-xs text-slate-500">{{ $rewardType->description }}</td>
                                        <td class="table-td text-right font-bold text-emerald-600">+{{ $rewardType->default_points }}đ</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <p id="rewardNoResult" class="hidden text-center text-sm text-slate-400 py-8">
            <i class="bi bi-search text-2xl block mb-2"></i> Không tìm thấy hạng mục phù hợp.
        </p>
    </section>

    {{-- ========== PHẦN IV — QUY TRÌNH ========== --}}
    <section class="policy-reveal max-w-5xl mx-auto px-4 sm:px-6 pb-14">
        <h2 class="text-xl font-bold text-slate-900 mb-6">Quy Trình Phê Duyệt Hệ Thống</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                <h3 class="flex items-center gap-2 text-sm font-bold text-red-600 uppercase tracking-wide">
                    <i class="bi bi-file-earmark-text"></i> Phiếu Phạt Điện Tử
                </h3>
                <ol class="mt-4 space-y-3 text-sm text-slate-600">
                    <li class="flex gap-3"><span class="shrink-0 font-bold text-slate-300">1</span>Quản lý trực tiếp lập phiếu phạt kèm minh chứng số (ảnh, log hệ thống).</li>
                    <li class="flex gap-3"><span class="shrink-0 font-bold text-slate-300">2</span>Hệ thống chuyển tiếp tự động đến cấp thẩm quyền đối soát.</li>
                    <li class="flex gap-3"><span class="shrink-0 font-bold text-slate-300">3</span>Sau khi duyệt, quỹ điểm nhân sự tự động bị khấu trừ.</li>
                    <li class="flex gap-3"><span class="shrink-0 font-bold text-slate-300">4</span>Tiếp nhận khiếu nại phản hồi ngược trong tối đa 48 giờ.</li>
                </ol>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                <h3 class="flex items-center gap-2 text-sm font-bold text-emerald-600 uppercase tracking-wide">
                    <i class="bi bi-file-earmark-check"></i> Phiếu Thưởng Điện Tử
                </h3>
                <ol class="mt-4 space-y-3 text-sm text-slate-600">
                    <li class="flex gap-3"><span class="shrink-0 font-bold text-slate-300">1</span>Quản lý đề xuất phiếu thưởng trực tiếp trên phần mềm.</li>
                    <li class="flex gap-3"><span class="shrink-0 font-bold text-slate-300">2</span>Hội đồng thi đua phê duyệt trực tuyến đề xuất.</li>
                    <li class="flex gap-3"><span class="shrink-0 font-bold text-slate-300">3</span>Điểm thưởng tự động phân bổ vào tài khoản nhân viên.</li>
                    <li class="flex gap-3"><span class="shrink-0 font-bold text-slate-300">4</span>Bảng vinh danh toàn mạng lưới tự động cập nhật công khai.</li>
                </ol>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <strong>Cơ chế chế tài Redzone:</strong> nhân viên có tổng điểm dưới mốc {{ $zones['orange'] }}đ duy trì trong 2 tháng liên tiếp sẽ chuyển hồ sơ sang diện kiểm soát đặc biệt để xem xét kỷ luật hoặc chấm dứt hợp đồng lao động.
        </div>
    </section>

    {{-- ========== FOOTER — CHỮ KÝ ========== --}}
    <footer class="border-t border-slate-200 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-14">
            <div class="grid sm:grid-cols-3 gap-8 text-center">
                <div>
                    <div class="text-sm font-bold text-slate-800">Đại Diện Công Ty</div>
                    <div class="mt-10 pt-3 border-t border-dashed border-slate-300 text-xs text-slate-400 mx-auto max-w-[180px]">(Ký và ghi rõ họ tên)</div>
                </div>
                <div>
                    <div class="text-sm font-bold text-slate-800">Quản Lý Vận Hành</div>
                    <div class="mt-10 pt-3 border-t border-dashed border-slate-300 text-xs text-slate-400 mx-auto max-w-[180px]">(Ký và ghi rõ họ tên)</div>
                </div>
                <div>
                    <div class="text-sm font-bold text-slate-800">Xác Nhận Nhân Viên</div>
                    <div class="mt-10 pt-3 border-t border-dashed border-slate-300 text-xs text-slate-400 mx-auto max-w-[180px]">(Ký và ghi rõ họ tên)</div>
                </div>
            </div>
            <div class="no-print mt-12 pt-6 border-t border-slate-100 text-center text-xs text-slate-400">
                {{ $companyName }} — Số 01/2026/NQ-TC — Cập nhật trực tiếp từ hệ thống P-CRM.
            </div>
        </div>
    </footer>

    <script>
        function togglePanel(id) {
            var panel = document.getElementById(id);
            var toggle = panel.previousElementSibling;
            panel.classList.toggle('is-open');
            toggle.classList.toggle('is-open');
        }

        function policySearch() {
            var q = document.getElementById('policySearch').value.trim().toLowerCase();

            [{ groupId: 'violationGroups', emptyId: 'violationNoResult' },
             { groupId: 'rewardGroups', emptyId: 'rewardNoResult' }].forEach(function (cfg) {
                var groupsWrap = document.getElementById(cfg.groupId);
                var groups = groupsWrap.querySelectorAll('.policy-group');
                var anyVisible = false;

                groups.forEach(function (group) {
                    var rows = group.querySelectorAll('.policy-row');
                    var panel = group.querySelector('.policy-panel');
                    var toggle = group.querySelector('.policy-toggle');
                    var groupMatches = false;

                    rows.forEach(function (row) {
                        var match = q === '' || row.textContent.toLowerCase().indexOf(q) !== -1;
                        row.style.display = match ? '' : 'none';
                        if (match) groupMatches = true;
                    });

                    group.style.display = groupMatches ? '' : 'none';
                    if (groupMatches) anyVisible = true;

                    if (q !== '') {
                        panel.classList.toggle('is-open', groupMatches);
                        toggle.classList.toggle('is-open', groupMatches);
                    }
                });

                document.getElementById(cfg.emptyId).classList.toggle('hidden', anyVisible || q === '');
            });
        }

        document.getElementById('policySearch').addEventListener('input', policySearch);

        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
            var revealObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.policy-reveal').forEach(function (el) {
                revealObserver.observe(el);
            });
        } else {
            document.querySelectorAll('.policy-reveal').forEach(function (el) {
                el.classList.add('is-visible');
            });
        }
    </script>

</body>

</html>
