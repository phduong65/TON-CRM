@extends('layouts.admin')

@section('title', $employee->name)
@section('page-title', $employee->name)
@section('page-subtitle', 'Hồ sơ, điểm số và lịch sử kỷ luật của nhân viên')
@section('breadcrumb', 'Nhân viên / Chi tiết')

@section('content')
    @if(session('new_account'))
    @php $acc = session('new_account'); @endphp
    <div id="newAccountBanner" class="mb-6 rounded-xl border-2 border-emerald-400 dark:border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-3 bg-emerald-400/20 dark:bg-emerald-500/10 border-b border-emerald-300 dark:border-emerald-600">
            <i class="bi bi-shield-check text-emerald-600 dark:text-emerald-400 text-lg"></i>
            <span class="font-semibold text-emerald-800 dark:text-emerald-300">Tài khoản đã được tạo thành công!</span>
            <button onclick="document.getElementById('newAccountBanner').remove()"
                    class="ml-auto w-7 h-7 flex items-center justify-center rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-200 dark:hover:bg-emerald-700 transition">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <div class="px-5 py-4 space-y-3">
            <p class="text-sm text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-amber-500"></i>
                Ghi lại thông tin này ngay — mật khẩu <strong>chỉ hiển thị một lần</strong>.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="rounded-lg bg-white dark:bg-slate-800 border border-emerald-200 dark:border-slate-600 px-4 py-3 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Nhân viên</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-white">{{ $acc['name'] }}</p>
                    <p class="text-xs text-slate-500 font-mono">Mã: {{ $acc['code'] }}</p>
                </div>
                <div class="rounded-lg bg-white dark:bg-slate-800 border border-emerald-200 dark:border-slate-600 px-4 py-3 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Thông tin đăng nhập</p>
                    <div class="flex items-center gap-2">
                        <i class="bi bi-envelope text-slate-400 text-xs"></i>
                        <span class="text-sm font-mono text-slate-800 dark:text-white select-all">{{ $acc['email'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="bi bi-key text-slate-400 text-xs"></i>
                        <span id="newAccPassword" class="text-sm font-mono font-bold text-pcrm-600 dark:text-pcrm-400 select-all tracking-widest">{{ $acc['password'] }}</span>
                        <button onclick="navigator.clipboard.writeText('{{ $acc['password'] }}').then(()=>{this.innerHTML='<i class=\'bi bi-check2\'></i>';setTimeout(()=>{this.innerHTML='<i class=\'bi bi-clipboard\'></i>'},1500)})"
                                class="ml-1 p-1 rounded text-slate-400 hover:text-pcrm-600 dark:hover:text-pcrm-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="Sao chép mật khẩu">
                            <i class="bi bi-clipboard text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <!-- Left Column: Compact Profile & Leave -->
        <div class="lg:col-span-1 space-y-3">
            <!-- Profile Card (Compact SaaS Layout) -->
            <div class="card p-5">
                <div class="flex items-start gap-3">
                    @if(auth()->id() === $employee->user_id)
                        <div class="relative w-16 h-16 shrink-0 group cursor-pointer" onclick="document.getElementById('empAvatarFileInput').click()" title="Bấm để thay đổi ảnh đại diện">
                            <div class="w-full h-full rounded-2xl overflow-hidden bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center border border-slate-200/80 dark:border-slate-700 shadow-sm">
                                @if($employee->user?->avatar)
                                    <img src="{{ asset($employee->user->avatar) }}" alt="{{ $employee->name }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-2xl font-black text-pcrm-750 dark:text-pcrm-400">
                                        {{ strtoupper(substr($employee->name, 0, 2)) }}
                                    </span>
                                @endif
                            </div>
                            <div class="absolute inset-0 bg-black/50 rounded-2xl flex flex-col items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <i class="bi bi-camera text-base"></i>
                                <span class="text-[8px] font-bold uppercase mt-0.5">Đổi ảnh</span>
                            </div>
                        </div>
                        <form id="empAvatarUploadForm" action="{{ route('profile.avatar') }}" method="POST" enctype="multipart/form-data" class="hidden">
                            @csrf
                            <input type="file" name="avatar" id="empAvatarFileInput" accept="image/*" onchange="this.form.submit()">
                        </form>
                    @else
                        <div class="w-16 h-16 shrink-0 rounded-2xl overflow-hidden bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center border border-slate-200/80 dark:border-slate-700 shadow-sm">
                            @if($employee->user?->avatar)
                                <img src="{{ asset($employee->user->avatar) }}" alt="{{ $employee->name }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-2xl font-bold text-pcrm-700 dark:text-pcrm-400">
                                    {{ strtoupper(substr($employee->name, 0, 2)) }}
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white truncate">{{ $employee->name }}</h3>
                            @if($employee->is_active)
                                <span class="badge badge-success text-[10px] px-1.5 py-0.5">Đang làm việc</span>
                            @else
                                <span class="badge badge-neutral text-[10px] px-1.5 py-0.5">Đã nghỉ</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $employee->position?->name ?? 'Chưa có chức vụ' }}
                        </p>
                        <p class="mt-1.5">
                            <span class="inline-flex items-center gap-1 font-mono text-xs font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-2 py-0.5 rounded-md">
                                <i class="bi bi-person-badge text-[11px] text-slate-400"></i> {{ $employee->code ?? '—' }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-700/80 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 dark:text-slate-500">Chi nhánh</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $employee->branch->name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 dark:text-slate-500">Đội nhóm</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $employee->team->name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 dark:text-slate-500">Email</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200 truncate max-w-[12rem]">{{ $employee->email ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 dark:text-slate-500">Điện thoại</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200 font-mono">{{ $employee->phone ?? '—' }}</span>
                    </div>
                </div>
            </div>

            @if($canViewSensitive)
            <!-- Annual Leave (Phép năm) -->
            @if($annualLeave)
                @php
                    $percent = $annualLeave['entitled'] > 0
                        ? min(100, round($annualLeave['used'] / $annualLeave['entitled'] * 100))
                        : 0;
                @endphp
                <div class="card p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-semibold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            Phép năm {{ $annualLeave['year'] ?? now()->year }}
                        </h4>
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            Còn {{ rtrim(rtrim(number_format($annualLeave['remaining'], 1), '0'), '.') }} ngày
                        </span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full bg-pcrm-500 rounded-full" style="width: {{ $percent }}%"></div>
                    </div>
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Đã dùng {{ rtrim(rtrim(number_format($annualLeave['used'], 1), '0'), '.') }}/{{ rtrim(rtrim(number_format($annualLeave['entitled'], 1), '0'), '.') }} ngày</span>
                        @can('view-leave-requests')
                        <a href="{{ route('leave-requests.index', ['employee_id' => $employee->id]) }}"
                           class="text-pcrm-600 dark:text-pcrm-400 hover:underline">
                            Đơn nghỉ <i class="bi bi-arrow-right text-[10px]"></i>
                        </a>
                        @endcan
                    </div>
                </div>
            @else
                <!-- Compact notice when not eligible -->
                <div class="rounded-xl border border-slate-200/70 dark:border-slate-700/60 bg-white dark:bg-slate-800 p-3.5 flex items-center gap-3 text-slate-500 dark:text-slate-400 shadow-sm">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center shrink-0">
                        <i class="bi bi-lock text-sm text-slate-400"></i>
                    </div>
                    <div class="text-xs min-w-0">
                        <span class="font-medium text-slate-700 dark:text-slate-300 block">Phép năm 2026: Không áp dụng</span>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">Chỉ áp dụng cho NV chính thức, khối văn phòng</span>
                    </div>
                </div>
            @endif
            @endif

            @if(auth()->id() === $employee->user_id)
                <div class="card p-3 border border-red-100 dark:border-red-950 bg-red-50/20 dark:bg-red-950/10">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="flex items-center justify-center gap-2 w-full py-2 rounded-xl bg-red-600 hover:bg-red-700 active:bg-red-800 text-white font-semibold text-xs shadow-sm transition">
                            <i class="bi bi-box-arrow-right text-sm"></i> Đăng xuất tài khoản
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <!-- Right Column: Performance Overview & History -->
        <div class="lg:col-span-2 space-y-3">
            @if($canViewSensitive)
            <!-- Performance KPI Strip (4 Metrics Overview) -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <!-- KPI 1: Tổng điểm / Quỹ điểm -->
                <div class="card p-4 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tổng điểm</span>
                        <span class="{{ $performanceStats['zone_badge'] ?? 'badge badge-success' }} text-[11px] font-semibold">
                            {{ $performanceStats['zone_label'] ?? 'Tốt' }}
                        </span>
                    </div>
                    <div class="my-2">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tabular-nums">
                                {{ number_format($performanceStats['total_score'] ?? $employee->total_score) }}
                            </span>
                            <span class="text-xs font-semibold text-slate-400">/ {{ $performanceStats['default_score'] ?? 100 }}</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden mt-2">
                            <div class="h-full rounded-full transition-all duration-500 {{ ($performanceStats['total_score'] ?? 100) >= 90 ? 'bg-emerald-500' : (($performanceStats['total_score'] ?? 100) >= 80 ? 'bg-amber-500' : 'bg-red-500') }}"
                                 style="width: {{ $performanceStats['score_percent'] ?? 100 }}%"></div>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-400 flex items-center justify-between">
                        <span>Quỹ: {{ $performanceStats['score_percent'] ?? 100 }}%</span>
                        @if(isset($performanceStats['monthly_trend_label']))
                            <span class="font-medium {{ ($performanceStats['monthly_trend_delta'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $performanceStats['monthly_trend_label'] }}
                            </span>
                        @else
                            @if(($performanceStats['total_score'] ?? 100) >= 90)
                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">An toàn</span>
                            @else
                                <span class="text-amber-600 dark:text-amber-400 font-medium">Cần lưu ý</span>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- KPI 2: Số lần vi phạm -->
                <div class="card p-4 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Số lần vi phạm</span>
                        <i class="bi bi-shield-exclamation text-amber-500 text-sm"></i>
                    </div>
                    <div class="my-2">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl sm:text-3xl font-black {{ ($performanceStats['total_penalties_count'] ?? 0) > 0 ? 'text-redzone-600 dark:text-redzone-400' : 'text-slate-900 dark:text-white' }} tabular-nums">
                                {{ $performanceStats['total_penalties_count'] ?? $employee->penalties->count() }}
                            </span>
                            <span class="text-xs font-medium text-slate-400">lần</span>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-400">
                        @if(($performanceStats['pending_penalties_count'] ?? 0) > 0)
                            <span class="text-amber-600 dark:text-amber-400 font-medium">
                                {{ $performanceStats['pending_penalties_count'] }} chờ duyệt
                            </span>
                        @else
                            <span>Không có phiếu chờ duyệt</span>
                        @endif
                    </div>
                </div>

                <!-- KPI 3: Điểm phạt -->
                <div class="card p-4 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Điểm phạt</span>
                        <i class="bi bi-arrow-down-right text-red-500 text-sm"></i>
                    </div>
                    <div class="my-2">
                        <span class="text-2xl sm:text-3xl font-black text-red-600 dark:text-red-400 tabular-nums">
                            -{{ number_format($performanceStats['deducted_points'] ?? 0) }}đ
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-400">
                        @if(($performanceStats['pending_points'] ?? 0) > 0)
                            <span class="text-amber-600 dark:text-amber-400">(-{{ number_format($performanceStats['pending_points']) }}đ treo)</span>
                        @else
                            <span>Đã trừ vào quỹ</span>
                        @endif
                    </div>
                </div>

                <!-- KPI 4: Điểm thưởng -->
                <div class="card p-4 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Điểm thưởng</span>
                        <i class="bi bi-gift text-emerald-500 text-sm"></i>
                    </div>
                    <div class="my-2">
                        <span class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                            +{{ number_format($performanceStats['rewarded_points'] ?? 0) }}đ
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-400">
                        <span>Tích lũy khen thưởng</span>
                    </div>
                </div>
            </div>

            <!-- Penalty History Card with Pattern Summary & Actions -->
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h4 class="font-semibold text-slate-900 dark:text-white">Lịch sử xử phạt</h4>
                        <span class="badge badge-neutral text-xs">{{ $employee->penalties->count() }}</span>
                    </div>
                    <a href="{{ route('employees.penalties', $employee) }}" class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                        Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                    </a>
                </div>

                <!-- Pattern Summary Bar -->
                @if(isset($performanceStats['violation_patterns']) && count($performanceStats['violation_patterns']) > 0)
                <div class="bg-slate-50/70 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700/60 px-6 py-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-slate-400 dark:text-slate-500 font-medium">Lỗi phổ biến:</span>
                            @foreach($performanceStats['violation_patterns'] as $pattern)
                                <span class="inline-flex items-center gap-1 bg-white dark:bg-slate-700 px-2 py-0.5 rounded-md border border-slate-200/80 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-medium text-[11px]">
                                    <span>{{ $pattern['name'] }}</span>
                                    <span class="font-bold text-red-600 dark:text-red-400">({{ $pattern['count'] }})</span>
                                </span>
                            @endforeach
                            @if(($performanceStats['total_penalties_deducted'] ?? 0) > 0)
                                <span class="inline-flex items-center gap-1 bg-red-50 dark:bg-red-950/40 px-2 py-0.5 rounded-md border border-red-200/80 dark:border-red-800/40 text-red-700 dark:text-red-300 font-semibold text-[11px]">
                                    Tổng trừ: -{{ number_format($performanceStats['total_penalties_deducted']) }}đ
                                </span>
                            @endif
                        </div>
                        @if(($performanceStats['pending_penalties_count'] ?? 0) > 0)
                            <span class="inline-flex items-center gap-1.5 text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/40 px-2 py-0.5 rounded-md font-medium text-[11px]">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                {{ $performanceStats['pending_penalties_count'] }} phiếu chờ duyệt
                            </span>
                        @endif
                    </div>
                </div>
                @endif

                <div class="card-body p-0">
                    @if($employee->penalties->count() > 0)
                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($employee->penalties->take(5) as $penalty)
                            <div class="flex items-center justify-between px-6 py-3.5 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors gap-3">
                                <div class="flex-1 min-w-0 pr-2">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('penalties.show', $penalty) }}" class="text-sm font-semibold text-slate-900 dark:text-white hover:text-pcrm-600 dark:hover:text-pcrm-400 truncate">
                                            {{ $penalty->violation->name ?? 'N/A' }}
                                        </a>
                                        @if($penalty->status === 'pending')
                                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500 shrink-0" title="Chờ quản lý phê duyệt"></span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 flex items-center gap-1.5 mt-0.5 flex-wrap">
                                        <span>{{ $penalty->created_at->format('d/m/Y H:i') }}</span>
                                        @if($penalty->code)
                                            <span class="font-mono text-[11px] text-slate-400 dark:text-slate-500">· {{ $penalty->code }}</span>
                                        @endif
                                        @if($penalty->description)
                                            <span class="truncate max-w-xs text-slate-500 dark:text-slate-400">· {{ $penalty->description }}</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-sm font-semibold tabular-nums text-red-600 dark:text-red-400 text-right min-w-[3.5rem]">-{{ number_format($penalty->total_points_deducted) }}đ</span>

                                    @if($penalty->status === 'pending' && auth()->user()->can('approve-penalties'))
                                        <!-- Actionable buttons for Manager / Admin -->
                                        <div class="flex items-center gap-1.5">
                                            <form method="POST" action="{{ route('penalties.approve', $penalty) }}" class="inline" onsubmit="return confirm('Xác nhận duyệt phiếu phạt này và trừ {{ $penalty->total_points_deducted }} điểm của nhân viên?')">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs transition shadow-sm" title="Duyệt phiếu phạt">
                                                    Duyệt
                                                </button>
                                            </form>
                                            <button type="button" onclick="openRejectModal({{ $penalty->id }}, '{{ addslashes($penalty->code ?? '#' . $penalty->id) }}')" class="px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800/40 font-medium text-xs transition" title="Từ chối phiếu phạt">
                                                Từ chối
                                            </button>
                                        </div>
                                    @else
                                        @php
                                            $statusMap = [
                                                'approved' => ['badge badge-success', 'Đã duyệt', ''],
                                                'rejected' => ['badge badge-danger', 'Từ chối', $penalty->rejected_reason ?? ''],
                                                'revoked'  => ['badge badge-neutral', 'Đã thu hồi', ''],
                                                'pending'  => ['badge badge-warning', 'Chờ duyệt', 'Chờ quản lý xem xét và phê duyệt'],
                                            ];
                                            [$cls, $lbl, $hint] = $statusMap[$penalty->status] ?? ['badge badge-warning', 'Chờ duyệt', ''];
                                        @endphp
                                        <span class="{{ $cls }} text-xs min-w-[5.25rem] justify-center" @if($hint) title="{{ $hint }}" @endif>{{ $lbl }}</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-6 text-center text-sm text-slate-400">
                            <i class="ph-check-circle text-2xl mb-2 block"></i>
                            <p>Chưa có vi phạm nào</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Consolidated Score History Table with Type Badges & Tabs Filter -->
            <div class="card">
                <div class="card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h4 class="font-semibold text-slate-900 dark:text-white">Lịch sử điểm thưởng/phạt</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Biến động điểm kỷ luật và khen thưởng</p>
                    </div>

                    <!-- Filter Controls -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="inline-flex items-center h-8 p-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-xs font-medium">
                            <button type="button" onclick="filterScoreTable('all')" id="tabFilterAll" class="h-full px-3 inline-flex items-center justify-center rounded-[6px] bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-xs font-semibold transition-all">
                                Tất cả
                            </button>
                            <button type="button" onclick="filterScoreTable('reward')" id="tabFilterReward" class="h-full px-3 inline-flex items-center justify-center rounded-[6px] text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white font-medium transition-all">
                                Thưởng (+)
                            </button>
                            <button type="button" onclick="filterScoreTable('penalty')" id="tabFilterPenalty" class="h-full px-3 inline-flex items-center justify-center rounded-[6px] text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white font-medium transition-all">
                                Phạt (-)
                            </button>
                        </div>
                        <div class="relative flex items-center">
                            <i class="bi bi-search absolute left-2.5 text-slate-400 text-xs pointer-events-none"></i>
                            <input type="text" id="scoreSearchInput" oninput="handleScoreSearch(this.value)" placeholder="Tìm lý do..."
                                   class="h-8 text-xs pl-8 pr-7 w-36 sm:w-44 rounded-lg bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-slate-800 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-pcrm-500 focus:border-pcrm-500 transition-all shadow-2xs">
                            <button type="button" id="scoreSearchClearBtn" onclick="clearScoreSearch()" class="hidden absolute right-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition" title="Xóa tìm kiếm">
                                <i class="bi bi-x-circle-fill text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    @if($employee->scores->count() > 0)
                        <div class="table-container border-0 rounded-none overflow-x-auto">
                            <table class="table-base" id="scoreHistoryTable">
                                <thead>
                                    <tr>
                                        <th class="table-th w-28">Ngày</th>
                                        <th class="table-th w-28">Loại</th>
                                        <th class="table-th">Lý do</th>
                                        <th class="table-th text-right w-24">Điểm</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employee->scores->sortByDesc('created_at') as $score)
                                    @php
                                        $isInitial = str_contains(mb_strtolower($score->reason ?? ''), 'khởi tạo') || $score->points === 100;
                                        $rowType = $isInitial ? 'initial' : ($score->points > 0 ? 'reward' : 'penalty');
                                    @endphp
                                    <tr class="table-tr-hover score-row" data-type="{{ $rowType }}" data-reason="{{ mb_strtolower($score->reason ?? '') }}">
                                        <td class="table-td text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                            {{ $score->created_at->format('d/m/Y') }}
                                        </td>
                                        <td class="table-td whitespace-nowrap">
                                            @if($isInitial)
                                                <span class="badge badge-info text-[11px]">Khởi tạo</span>
                                            @elseif($score->points > 0)
                                                <span class="badge badge-success text-[11px]">Thưởng điểm</span>
                                            @else
                                                <span class="badge badge-danger text-[11px]">Xử phạt</span>
                                            @endif
                                        </td>
                                        <td class="table-td whitespace-normal min-w-[200px] text-sm font-medium text-slate-800 dark:text-slate-200">
                                            {{ $score->reason }}
                                        </td>
                                        <td class="table-td text-right font-bold tabular-nums {{ $isInitial ? 'text-pcrm-600 dark:text-pcrm-400' : ($score->points >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400') }}">
                                            {{ $score->points >= 0 ? '+' : '' }}{{ number_format($score->points) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                    <tr id="scoreEmptyRow" class="hidden">
                                        <td colspan="4" class="table-td text-center py-6 text-slate-400 text-xs">
                                            Không có dữ liệu phù hợp với bộ lọc
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-6 text-center text-sm text-slate-400">
                            <i class="ph-coins text-2xl mb-2 block"></i>
                            <p>Chưa có ghi nhận điểm</p>
                        </div>
                    @endif
                </div>
            </div>
            @else
            <!-- Privacy notice for non-privileged viewers -->
            <div class="card">
                <div class="card-body py-12 text-center">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-lock text-2xl text-slate-400"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Thông tin điểm và lịch sử vi phạm được bảo mật</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Chỉ bản thân nhân viên hoặc quản lý mới có thể xem.</p>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Reject Penalty Modal -->
    <div id="penaltyRejectModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 max-w-md w-full p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="bi bi-x-circle text-red-500"></i> Từ chối phiếu phạt <span id="rejectModalCode" class="font-mono text-pcrm-600"></span>
                </h3>
                <button type="button" onclick="closeRejectModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="penaltyRejectForm" method="POST" action="" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Lý do từ chối <span class="text-red-500">*</span>
                    </label>
                    <textarea name="rejected_reason" rows="3" required class="form-input text-sm w-full rounded-xl" placeholder="Nhập lý do từ chối xử phạt..."></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeRejectModal()" class="btn-ghost btn-sm">Hủy</button>
                    <button type="submit" class="btn-danger btn-sm">Xác nhận từ chối</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Vanilla JS for Reject Modal & Score History Filtering -->
    <script>
        function openRejectModal(id, code) {
            const modal = document.getElementById('penaltyRejectModal');
            const form = document.getElementById('penaltyRejectForm');
            const codeSpan = document.getElementById('rejectModalCode');
            form.action = '/penalties/' + id + '/reject';
            codeSpan.textContent = code;
            modal.classList.remove('hidden');
        }

        function closeRejectModal() {
            document.getElementById('penaltyRejectModal').classList.add('hidden');
        }

        document.getElementById('penaltyRejectModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });

        // Score History Filtering & Searching
        let currentScoreFilter = 'all';
        let currentScoreQuery = '';

        function filterScoreTable(type) {
            currentScoreFilter = type;
            const tabs = {
                all: document.getElementById('tabFilterAll'),
                reward: document.getElementById('tabFilterReward'),
                penalty: document.getElementById('tabFilterPenalty')
            };

            const activeCls = 'h-full px-3 inline-flex items-center justify-center rounded-[6px] bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-xs font-semibold transition-all';
            const inactiveCls = 'h-full px-3 inline-flex items-center justify-center rounded-[6px] text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white font-medium transition-all';

            Object.keys(tabs).forEach(k => {
                if (tabs[k]) {
                    tabs[k].className = (k === type) ? activeCls : inactiveCls;
                }
            });

            applyScoreTableFilter();
        }

        function handleScoreSearch(query) {
            currentScoreQuery = (query || '').toLowerCase().trim();
            const clearBtn = document.getElementById('scoreSearchClearBtn');
            if (clearBtn) {
                if (currentScoreQuery.length > 0) {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }
            }
            applyScoreTableFilter();
        }

        function clearScoreSearch() {
            const input = document.getElementById('scoreSearchInput');
            if (input) {
                input.value = '';
                handleScoreSearch('');
                input.focus();
            }
        }

        function applyScoreTableFilter() {
            const rows = document.querySelectorAll('.score-row');
            const emptyRow = document.getElementById('scoreEmptyRow');
            let visibleCount = 0;

            rows.forEach(row => {
                const type = row.getAttribute('data-type');
                const reason = row.getAttribute('data-reason') || '';

                let matchType = true;
                if (currentScoreFilter === 'reward') {
                    matchType = (type === 'reward' || type === 'initial');
                } else if (currentScoreFilter === 'penalty') {
                    matchType = (type === 'penalty');
                }

                let matchQuery = true;
                if (currentScoreQuery) {
                    matchQuery = reason.includes(currentScoreQuery);
                }

                if (matchType && matchQuery) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                }
            });

            if (emptyRow) {
                if (visibleCount === 0) {
                    emptyRow.classList.remove('hidden');
                } else {
                    emptyRow.classList.add('hidden');
                }
            }
        }
    </script>
@endsection
