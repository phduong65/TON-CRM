@extends('layouts.admin')

@section('title', 'Tổng quan')

@section('content')
    @php
        // Phòng vệ: view mobile dùng chung 'dashboard.index' — $employee được tham chiếu ở khối
        // thông tin nhân viên và script GPS/thời tiết. Nếu controller render mà chưa truyền
        // $employee/$isAdmin (VD nhánh admin dashboard), lấy fallback tại chỗ để tránh
        // "Undefined variable $employee".
        $isAdmin  = $isAdmin ?? auth()->user()->hasRole(['admin', 'manager', 'director']);
        $employee = $employee ?? auth()->user()->employee;
        $theme = $activeTheme ?? null;
        $hasSeasonalTheme = $theme && !empty($theme['id']) && ($theme['slug'] ?? 'default') !== 'default';
        $slug = $theme['slug'] ?? '';
        $isTet = str_contains($slug, 'tet');
        $isQuocKhanh = str_contains($slug, 'quoc-khanh') || str_contains($slug, '2-9');
        $holidayBanners = $theme['visual']['banners'] ?? [];
        $mobileHeroBanner = $holidayBanners['login_banner_mobile'] ?? $holidayBanners['dashboard_horizontal'] ?? null;
        $holidayAccent = $theme['colors']['accent'] ?? '#DC2626';
        $holidayTag = $isTet ? 'Xuân Bính Ngọ · TON Capital' : ($isQuocKhanh ? 'Kỷ Niệm 2/9 · Tự Hào Non Sông' : ($theme['name'] ?? 'Lễ Hội'));
        $holidayGreeting = $theme['content']['loginGreeting'] ?? $theme['content']['dashboardGreeting'] ?? 'Chào mừng ngày lễ';
        $holidaySubtitle = $theme['content']['dashboardSubtitle'] ?? $theme['content']['loginSubtitle'] ?? '';
    @endphp
    <div class="relative min-h-screen pb-12">

        <div class="relative space-y-6 z-10">
            @if ($isAdmin)
                {{-- ══════════════════════════════════════════════════════════════════════ --}}
                {{-- ADMIN/MANAGER MOBILE DASHBOARD                                       --}}
                {{-- ══════════════════════════════════════════════════════════════════════ --}}
                @if ($hasSeasonalTheme)
                    <!-- Festive Admin Header Greeting with Holiday Banner & Badges -->
                    <div class="rounded-3xl p-5 pt-6 pb-16 text-white shadow-lg relative overflow-hidden bg-[#6B0612] border border-red-900/30">
                        @if($mobileHeroBanner && file_exists(public_path($mobileHeroBanner)))
                            <img src="{{ asset($mobileHeroBanner) }}"
                                 alt="{{ $theme['name'] }}"
                                 class="absolute inset-0 w-full h-full object-cover object-center select-none pointer-events-none">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/55 to-black/60 pointer-events-none"></div>
                        @else
                            <div class="absolute inset-0 bg-gradient-to-tr from-red-900 to-amber-900 pointer-events-none"></div>
                        @endif

                        <div class="relative z-10 min-w-0 space-y-1">
                            <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-400 text-red-950 shadow-xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-ping"></span>
                                    {{ $theme['name'] }}
                                </span>
                                <span class="text-[11px] text-amber-200 font-semibold">{{ $holidayTag }}</span>
                            </div>
                            <p class="text-xs text-amber-200/90 font-bold flex items-center gap-1">
                                {{ $holidayGreeting }}
                            </p>
                            <h2 class="text-xl font-black truncate tracking-tight">Quản trị viên TON-HR</h2>
                            <p class="text-[10px] text-white/70 flex items-center gap-1 pt-0.5">
                                <i class="bi bi-calendar3"></i> Hôm nay: {{ now()->format('d/m/Y') }} · {{ $holidaySubtitle ?: 'Hệ thống vận hành thông suốt' }}
                            </p>
                        </div>
                    </div>
                @else
                    <!-- Standard Header greeting (Dark Slate elegant banner) -->
                    <div class="rounded-3xl bg-gradient-to-tr from-slate-800 to-slate-950 p-5 pt-6 pb-16 text-white shadow-sm relative overflow-hidden">
                        <div class="absolute -right-10 -top-10 w-36 h-36 rounded-full bg-white/10 blur-xl"></div>
                        <div class="relative min-w-0">
                            <p class="text-xs text-white/70 font-medium">Hệ thống điều hành</p>
                            <h2 class="text-xl font-black mt-1 truncate">Quản trị viên TON-HR</h2>
                            <p class="mt-2 text-[10px] text-white/50 flex items-center gap-1">
                                <i class="bi bi-calendar3"></i> Hôm nay: {{ now()->format('d/m/Y') }}
                            </p>
                        </div>
                    </div>
                @endif

                <!-- Overview Stat Cards (OVERLAPPING glassmorphic layout) -->
                <div class="grid grid-cols-2 gap-3 -mt-12 relative z-10 mx-3">
                    <!-- Employees -->
                    <a href="{{ route('employees.index') }}" class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/50 dark:border-slate-800/60 shadow-sm flex flex-col justify-between hover:scale-[1.02] transition-transform duration-200">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                            <i class="bi bi-people text-xl"></i>
                        </div>
                        <div class="mt-3">
                            <p class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ $totalEmployees ?? 0 }}</p>
                            <p class="text-[11px] font-bold text-slate-400 dark:text-slate-500 mt-0.5">Tổng nhân viên</p>
                        </div>
                    </a>

                    <!-- Attendance today -->
                    <a href="{{ route('attendance-logs.index') }}" class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/50 dark:border-slate-800/60 shadow-sm flex flex-col justify-between hover:scale-[1.02] transition-transform duration-200">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <i class="bi bi-calendar-check text-xl"></i>
                        </div>
                        <div class="mt-3">
                            <p class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                                {{ $checkedInTodayCount ?? 0 }}<span class="text-xs text-slate-400 font-normal">/{{ $scheduledTodayCount ?? 0 }}</span>
                            </p>
                            <p class="text-[11px] font-bold text-slate-400 dark:text-slate-500 mt-0.5">Đi ca hôm nay</p>
                        </div>
                    </a>

                    <!-- On duty staff -->
                    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/50 dark:border-slate-800/60 shadow-sm flex flex-col justify-between">
                        <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center text-violet-600 dark:text-violet-400">
                            <i class="bi bi-clock-history text-xl"></i>
                        </div>
                        <div class="mt-3">
                            <p class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ $onShiftTotalCount ?? 0 }}</p>
                            <p class="text-[11px] font-bold text-slate-400 dark:text-slate-500 mt-0.5">Đang làm việc</p>
                        </div>
                    </div>

                    <!-- Pending Penalties -->
                    <a href="{{ route('staff-requests.index') }}" class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/50 dark:border-slate-800/60 shadow-sm flex flex-col justify-between hover:scale-[1.02] transition-transform duration-200 relative">
                        <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-900/20 flex items-center justify-center text-rose-600 dark:text-rose-400">
                            <i class="bi bi-hammer text-xl"></i>
                        </div>
                        @if(($pendingPenalties ?? 0) > 0)
                            <span class="absolute top-4 right-4 w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                        @endif
                        <div class="mt-3">
                            <p class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ $pendingPenalties ?? 0 }}</p>
                            <p class="text-[11px] font-bold text-slate-400 dark:text-slate-500 mt-0.5">Phiếu phạt chờ duyệt</p>
                        </div>
                    </a>
                </div>

                <!-- Quick Management Tools (Glass cards) -->
                <div class="space-y-3">
                    <h3 class="text-xs font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-1">Truy cập nhanh</h3>
                    <div class="grid grid-cols-4 gap-2.5">
                        <a href="{{ route('penalties.index') }}" class="bg-white/70 dark:bg-slate-900/50 backdrop-blur-md border border-slate-200/30 dark:border-slate-800/50 p-2 rounded-2xl flex flex-col items-center justify-center text-center shadow-none hover:scale-105 transition-transform duration-200">
                            <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 flex items-center justify-center mb-1 shadow-sm">
                                <i class="bi bi-hammer text-lg"></i>
                            </div>
                            <span class="text-[9px] font-bold text-slate-650 dark:text-slate-350">Xử phạt</span>
                        </a>
                        <a href="{{ route('rewards.index') }}" class="bg-white/70 dark:bg-slate-900/50 backdrop-blur-md border border-slate-200/30 dark:border-slate-800/50 p-2 rounded-2xl flex flex-col items-center justify-center text-center shadow-none hover:scale-105 transition-transform duration-200">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-1 shadow-sm">
                                <i class="bi bi-gift text-lg"></i>
                            </div>
                            <span class="text-[9px] font-bold text-slate-650 dark:text-slate-350">Thưởng</span>
                        </a>
                        <a href="{{ route('attendance-locations.index') }}" class="bg-white/70 dark:bg-slate-900/50 backdrop-blur-md border border-slate-200/30 dark:border-slate-800/50 p-2 rounded-2xl flex flex-col items-center justify-center text-center shadow-none hover:scale-105 transition-transform duration-200">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-1 shadow-sm">
                                <i class="bi bi-geo-alt text-lg"></i>
                            </div>
                            <span class="text-[9px] font-bold text-slate-650 dark:text-slate-350">Điểm CC</span>
                        </a>
                        <a href="{{ route('settings.index') }}" class="bg-white/70 dark:bg-slate-900/50 backdrop-blur-md border border-slate-200/30 dark:border-slate-800/50 p-2 rounded-2xl flex flex-col items-center justify-center text-center shadow-none hover:scale-105 transition-transform duration-200">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 flex items-center justify-center mb-1 shadow-sm">
                                <i class="bi bi-gear text-lg"></i>
                            </div>
                            <span class="text-[9px] font-bold text-slate-650 dark:text-slate-350">Cài đặt</span>
                        </a>
                    </div>
                </div>

                <!-- Employees currently in shift -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/50 dark:border-slate-800/60 shadow-sm">
                    <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                        <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Nhân viên đang trong ca</h3>
                        <span class="text-[11px] font-bold text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-900/20 px-2 py-0.5 rounded-full">
                            {{ $onShiftTotalCount }} người
                        </span>
                    </div>

                    @forelse($onShiftByBranch as $branchName => $logs)
                        <div class="mb-4 last:mb-0">
                            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-1.5">
                                <i class="bi bi-building text-slate-400"></i> {{ $branchName }}
                            </h4>
                            <div class="space-y-2">
                                @foreach($logs as $log)
                                    <div class="flex items-center justify-between p-2.5 bg-slate-50/50 dark:bg-slate-950/20 rounded-xl border border-slate-100 dark:border-slate-800/30">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <x-employee-avatar :employee="$log->employee" size="w-8 h-8" :initials="2"
                                                fallback="bg-pcrm-100 dark:bg-pcrm-900/50 text-pcrm-700 dark:text-pcrm-400" />
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ $log->employee->name }}</p>
                                                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5 truncate">{{ $log->employee->employee_code }} • {{ $log->employee->team->name ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">Vào: {{ substr($log->check_in_at, 11, 5) }}</p>
                                            <p class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $log->shiftSchedule->shift->name ?? 'Mẫu ca' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-400 dark:text-slate-500">
                            <i class="bi bi-clock text-2xl opacity-60"></i>
                            <p class="text-xs mt-2 font-medium">Hiện không có ai đang trong ca làm việc</p>
                        </div>
                    @endforelse
                </div>

                <!-- Recent Penalties list -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/50 dark:border-slate-800/60 shadow-sm">
                    <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                        <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Vi phạm gần đây</h3>
                        <a href="{{ route('penalties.index') }}" class="text-[11px] text-pcrm-600 dark:text-pcrm-400 font-semibold hover:underline">Xem tất cả</a>
                    </div>
                    <div class="space-y-2">
                        @forelse($recentPenalties as $p)
                            <div class="flex items-start justify-between p-2.5 bg-slate-50/50 dark:bg-slate-950/20 rounded-xl border border-slate-100 dark:border-slate-800/30">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ $p->employee->name }}</p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1">{{ $p->violation->name }}</p>
                                    <p class="text-[9px] text-slate-400 dark:text-slate-500 mt-1">{{ $p->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <div class="text-right shrink-0 ml-3">
                                    <span class="inline-flex px-1.5 py-0.5 text-[10px] font-bold rounded bg-red-100 dark:bg-red-950/40 text-red-700 dark:text-red-400">
                                        -{{ $p->total_points_deducted }}đ
                                    </span>
                                    @if($p->total_money_deducted > 0)
                                        <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 mt-1">-{{ number_format($p->total_money_deducted) }}đ</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-4 text-center text-slate-400 dark:text-slate-500 text-xs">
                                Không có dữ liệu vi phạm gần đây
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                {{-- ══════════════════════════════════════════════════════════════════════ --}}
                {{-- EMPLOYEE PERSONAL MOBILE DASHBOARD                                    --}}
                {{-- ══════════════════════════════════════════════════════════════════════ --}}
                @php
                    $score = $myTotalScore ?? 100;
                    $gradient = match (true) {
                        $score >= 90 => 'from-emerald-500 to-emerald-700 dark:from-emerald-600 dark:to-emerald-800',
                        $score >= 80 => 'from-amber-500 to-amber-700 dark:from-amber-600 dark:to-amber-800',
                        $score >= 70 => 'from-orange-500 to-orange-700 dark:from-orange-600 dark:to-orange-800',
                        default => 'from-rose-500 to-rose-700 dark:from-rose-600 dark:to-rose-800',
                    };
                    $now = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
                    $hour = $now->hour;
                    $greeting = match (true) {
                        $hour >= 5 && $hour < 11 => 'Chào buổi sáng',
                        $hour >= 11 && $hour < 13 => 'Chào buổi trưa',
                        $hour >= 13 && $hour < 18 => 'Chào buổi chiều',
                        default => 'Chào buổi tối',
                    };
                    $greetIcon = match (true) {
                        $hour >= 5 && $hour < 11 => 'bi-sun-fill text-amber-300',
                        $hour >= 11 && $hour < 13 => 'bi-brightness-high-fill text-yellow-200',
                        $hour >= 13 && $hour < 18 => 'bi-cloud-sun-fill text-sky-200',
                        default => 'bi-moon-stars-fill text-indigo-200',
                    };
                    $dateLabel = $now->isoFormat('dddd, DD/MM/YYYY');
                    $roleName = auth()->user()->getRoleNames()->first() ?? 'Nhân viên';

                    // ── Calculate On-Time Streak (Chuỗi đi làm đúng giờ liên tục) ──
                    $streak = 0;
                    $todayDay = $now->day;
                    $monthSchedules = $myMonthlySchedules ?? collect();
                    $monthLogs = $myMonthlyLogs ?? collect();

                    for ($d = $todayDay; $d >= 1; $d--) {
                        $dSchedule = $monthSchedules->get($d);
                        if ($dSchedule) {
                            if ($dSchedule->attendanceLog && $dSchedule->attendanceLog->check_in_at) {
                                if ($dSchedule->attendanceLog->late_minutes <= 0) {
                                    $streak++;
                                } else {
                                    break;
                                }
                            } else {
                                if ($d === $todayDay) {
                                    continue;
                                } else {
                                    break;
                                }
                            }
                        } else {
                            continue;
                        }
                    }
                @endphp

                @if ($hasSeasonalTheme)
                    {{-- Banner lễ hội gọn (giữ tính năng theme theo mùa) --}}
                    <div class="rounded-2xl p-4 text-white relative overflow-hidden bg-[#6B0612]">
                        @if($mobileHeroBanner && file_exists(public_path($mobileHeroBanner)))
                            <img src="{{ asset($mobileHeroBanner) }}" alt="{{ $theme['name'] }}" class="absolute inset-0 w-full h-full object-cover object-center select-none pointer-events-none">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/40 pointer-events-none"></div>
                        @endif
                        <div class="relative z-10">
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-amber-400 text-red-950">{{ $theme['name'] }}</span>
                            <p class="text-xs text-amber-200 font-semibold mt-1">{{ $holidayTag }}</p>
                            <p class="text-sm font-bold mt-0.5">{{ $holidayGreeting }}</p>
                        </div>
                    </div>
                @endif

                @include('components.mobile-greeting')

                {{-- Thẻ chấm công hôm nay --}}
                <section aria-labelledby="todayAttTitle" class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-blue-100 dark:border-slate-800 shadow-[0_4px_20px_rgba(37,99,235,0.08)]">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <i class="bi bi-calendar-week text-blue-600 text-xl"></i>
                            <h3 id="todayAttTitle" class="text-[13px] font-extrabold uppercase tracking-wide text-[#0B1F5C] dark:text-slate-200">Chấm công hôm nay</h3>
                        </div>
                    </div>

                    <div class="text-center pt-1 pb-2">
                        <span id="quickAttClock" class="text-[40px] leading-none font-extrabold tabular-nums tracking-tight text-[#0B1F5C] dark:text-white inline-block">{{ now()->setTimezone('Asia/Ho_Chi_Minh')->format('H:i:s') }}</span>
                    </div>

                    @php
                        $primarySched = $todayShiftSchedules->first(fn ($x) => !$x->isMissed() && !($x->attendanceLog?->check_in_at && $x->attendanceLog?->check_out_at))
                            ?? $todayShiftSchedules->last();
                    @endphp
                    <div class="space-y-3">
                        @forelse ($todayShiftSchedules->sortBy(fn ($x) => $x->id === $primarySched?->id ? 0 : 1) as $sched)
                            @if ($sched->id !== $primarySched->id)
                                @php
                                    $oShift = $sched->effectiveShift();
                                    $oLog = $sched->attendanceLog;
                                    $oLabel = $sched->isMissed() ? 'Vắng mặt' : (($oLog?->check_in_at && $oLog?->check_out_at) ? 'Hoàn thành' : ($oLog?->check_in_at ? 'Đã vào ca' : 'Chưa vào ca'));
                                @endphp
                                <a href="{{ route('my-schedule.index') }}" class="flex items-center gap-3 min-h-[44px] rounded-xl bg-slate-50 dark:bg-slate-800/50 px-3 py-2">
                                    <i class="bi bi-briefcase text-blue-600"></i>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold text-[#0B1F5C] dark:text-white truncate">{{ $oShift->name ?? ($sched->isFlexible() ? 'Ca linh hoạt' : 'Ca làm việc') }}</span>
                                        <span class="block text-xs text-slate-500 tabular-nums">{{ substr($oShift->start_time ?? '00:00', 0, 5) }} – {{ substr($oShift->end_time ?? '00:00', 0, 5) }}</span>
                                    </span>
                                    <span class="text-xs font-bold {{ $oLabel === 'Vắng mặt' ? 'text-red-600' : 'text-blue-700' }}">{{ $oLabel }}</span>
                                </a>
                                @continue
                            @endif
                            @php
                                $sid = $sched->id;
                                $attLog = $sched->attendanceLog;
                                $isMissed = $sched->isMissed();
                                $effShift = $sched->effectiveShift();
                                $schedOvernight = (bool) $effShift?->is_overnight;
                                $isWfh = ($effShift->work_mode ?? 'onsite') === 'wfh';
                                $checkBoundaries = [];
                                if ($effShift && !$schedOvernight) {
                                    $checkBoundaries = [
                                        'startTime' => substr($effShift->start_time, 0, 5),
                                        'endTime' => substr($effShift->end_time, 0, 5),
                                        'earlyCheckoutBoundary' => \Carbon\Carbon::parse($effShift->end_time)->subMinutes($effShift->grace_early_minutes ?? 0)->format('H:i'),
                                    ];
                                }
                                $isDone = $attLog?->check_in_at && $attLog?->check_out_at;
                            @endphp
                            <div>
                                <div class="flex justify-center mb-4">
                                    @if ($isMissed)
                                        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-400"><i class="bi bi-x-circle-fill"></i> Vắng mặt</span>
                                    @elseif ($isDone)
                                        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"><i class="bi bi-check-circle-fill"></i> Hoàn thành</span>
                                    @elseif ($attLog?->check_in_at)
                                        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> Đã vào ca</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><i class="bi bi-hourglass-split"></i> Chưa vào ca</span>
                                    @endif
                                </div>

                                <a href="{{ route('my-schedule.index') }}" class="flex items-center gap-3 min-h-[56px] py-3 border-t border-slate-100 dark:border-slate-800">
                                    <i class="bi bi-briefcase-fill text-[28px] text-blue-600 w-10 text-center shrink-0"></i>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-base font-bold text-[#0B1F5C] dark:text-white truncate">{{ $effShift->name ?? ($sched->isFlexible() ? 'Ca linh hoạt' : 'Ca làm việc') }}</span>
                                        <span class="block text-sm text-slate-500 dark:text-slate-400 truncate">
                                            {{ substr($effShift->start_time ?? '00:00', 0, 5) }} – {{ substr($effShift->end_time ?? '00:00', 0, 5) }}
                                            • {{ $isWfh ? 'WFH' : 'Tại chỗ' }}
                                        </span>
                                    </span>
                                    <i class="bi bi-chevron-right text-slate-400"></i>
                                </a>

                                <div class="grid grid-cols-2 py-3 border-t border-slate-100 dark:border-slate-800">
                                    <div class="flex items-center justify-center gap-2.5 border-r border-slate-100 dark:border-slate-800">
                                        <i class="bi bi-box-arrow-in-right text-2xl text-blue-500"></i>
                                        <div>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Vào ca:</p>
                                            <p class="text-xl font-extrabold text-[#0B1F5C] dark:text-white tabular-nums leading-tight">{{ $attLog?->check_in_at ? $attLog->check_in_at->format('H:i') : substr($effShift->start_time ?? '00:00', 0, 5) }}</p>
                                            @if(($attLog->late_minutes ?? 0) > 0)<p class="text-xs font-bold text-rose-600">Trễ {{ $attLog->late_minutes }} phút</p>@endif
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-center gap-2.5">
                                        <i class="bi bi-box-arrow-right text-2xl text-blue-500"></i>
                                        <div>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Ra ca:</p>
                                            <p class="text-xl font-extrabold text-[#0B1F5C] dark:text-white tabular-nums leading-tight">{{ $attLog?->check_out_at ? $attLog->check_out_at->format('H:i') : substr($effShift->end_time ?? '00:00', 0, 5) }}</p>
                                            @if(($attLog->early_minutes ?? 0) > 0)<p class="text-xs font-bold text-amber-600">Sớm {{ $attLog->early_minutes }} phút</p>@endif
                                        </div>
                                    </div>
                                </div>

                                <div id="attendanceMessage-{{ $sid }}" role="alert" class="hidden p-2.5 rounded-lg text-xs font-semibold mt-3"></div>

                                @if ($canCheckinAttendance && !$isMissed && !$isDone)
                                    <div class="mt-3 grid grid-cols-2 gap-3">
                                        @if ($attLog?->check_in_at)
                                            <button type="button" disabled class="min-h-[48px] rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 font-bold text-sm flex items-center justify-center gap-1.5 cursor-not-allowed"><i class="bi bi-check-circle-fill"></i> Đã vào ca</button>
                                        @else
                                            <button type="button" id="btnCheckIn-{{ $sid }}"
                                                onclick="quickDoAttendance('check-in', {{ $sid }}, {{ json_encode($checkBoundaries) }})"
                                                class="min-h-[48px] rounded-xl bg-white dark:bg-slate-900 border-2 border-blue-500 text-blue-600 dark:text-blue-300 hover:bg-blue-50 font-bold text-sm flex items-center justify-center gap-1.5 active:scale-95 transition">
                                                <i class="bi bi-box-arrow-in-right text-lg"></i> Vào ca
                                            </button>
                                        @endif

                                        @if ($attLog?->check_in_at)
                                            <button type="button" id="btnCheckOut-{{ $sid }}"
                                                onclick="quickDoAttendance('check-out', {{ $sid }}, {{ json_encode($checkBoundaries) }})"
                                                class="min-h-[48px] rounded-xl bg-white dark:bg-slate-900 border-2 border-blue-500 text-blue-600 dark:text-blue-300 hover:bg-blue-50 font-bold text-sm flex items-center justify-center gap-1.5 active:scale-95 transition">
                                                <i class="bi bi-box-arrow-right text-lg"></i> Ra ca
                                            </button>
                                        @else
                                            <button type="button" disabled class="min-h-[48px] rounded-xl border-2 border-blue-200 dark:border-slate-700 text-blue-300 dark:text-slate-600 font-bold text-sm flex items-center justify-center gap-1.5 cursor-not-allowed"><i class="bi bi-box-arrow-right text-lg"></i> Ra ca</button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            @if ($unscheduledLog)
                                <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
                                    <div class="flex items-center gap-3">
                                        <span class="w-11 h-11 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0"><i class="bi bi-briefcase-fill text-lg"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-base font-bold text-[#0B1F5C] dark:text-white">Ca làm tự do</span>
                                            <span class="block text-sm text-slate-500 dark:text-slate-400">Không theo lịch xếp ca</span>
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 mt-3 py-3 border-y border-slate-100 dark:border-slate-800 text-center">
                                        <div class="border-r border-slate-100 dark:border-slate-800">
                                            <p class="text-xs text-slate-500 font-medium">Vào ca</p>
                                            <p class="text-xl font-extrabold text-[#0B1F5C] dark:text-white tabular-nums">{{ $unscheduledLog->check_in_at ? \Carbon\Carbon::parse($unscheduledLog->check_in_at)->format('H:i') : '--:--' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500 font-medium">Ra ca</p>
                                            <p class="text-xl font-extrabold text-[#0B1F5C] dark:text-white tabular-nums">{{ $unscheduledLog->check_out_at ? \Carbon\Carbon::parse($unscheduledLog->check_out_at)->format('H:i') : '--:--' }}</p>
                                        </div>
                                    </div>
                                    @if ($canCheckinAttendance)
                                        <div id="attendanceMessage-0" role="alert" class="hidden p-2.5 rounded-lg text-xs font-semibold mt-3"></div>
                                        @if ($unscheduledLog->check_out_at)
                                            <p class="mt-3 text-center text-sm font-bold text-blue-700 dark:text-blue-300"><i class="bi bi-check-circle-fill"></i> Hoàn thành</p>
                                        @else
                                            <div class="mt-3">
                                                <button type="button" id="btnCheckOut-0" onclick="quickDoAttendance('check-out', null, {})"
                                                    class="w-full min-h-[48px] rounded-xl bg-white dark:bg-slate-900 border-2 border-blue-500 text-blue-600 hover:bg-blue-50 font-bold text-sm flex items-center justify-center gap-1.5 active:scale-95 transition">
                                                    <i class="bi bi-box-arrow-right text-lg"></i> Ra ca tự do
                                                </button>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @else
                                <div class="border-t border-slate-100 dark:border-slate-800 pt-4 pb-1 text-center">
                                    <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Hôm nay bạn chưa được xếp ca.</p>
                                    @can('view-own-schedule')
                                        <a href="{{ route('my-schedule.index') }}" class="inline-flex items-center gap-1 mt-2 min-h-[44px] text-sm font-bold text-blue-600">Xem lịch làm <i class="bi bi-chevron-right"></i></a>
                                    @endcan
                                </div>
                            @endif
                        @endforelse
                    </div>
                </section>

                {{-- Truy cập nhanh --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between px-1">
                        <h3 class="font-heading text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Truy cập nhanh</h3>
                        <a href="{{ route('menu.index') }}" class="font-heading text-sm font-bold text-blue-600 min-h-[44px] inline-flex items-center">Xem thêm</a>
                    </div>
                    <div class="grid grid-cols-4 gap-2.5 -mt-2">
                        @php
                            $quick = [
                                ['route' => 'my-schedule.index', 'icon' => 'bi-calendar3', 'label' => 'Lịch làm'],
                                ['route' => 'staff-requests.index', 'icon' => 'bi-file-earmark-text', 'label' => 'Đơn từ'],
                                auth()->user()->can('view-employees')
                                    ? ['route' => 'employees.index', 'icon' => 'bi-people', 'label' => 'Nhân sự']
                                    : ['route' => 'my-attendance-logs.index', 'icon' => 'bi-list-check', 'label' => 'Lịch sử công'],
                                ['route' => 'menu.index', 'icon' => 'bi-grid', 'label' => 'Tất cả'],
                            ];
                        @endphp
                        @foreach ($quick as $q)
                            <a href="{{ route($q['route']) }}" class="bg-white dark:bg-slate-900 border border-blue-50 dark:border-slate-800 rounded-2xl min-h-[80px] py-3 px-1 flex flex-col items-center justify-center text-center shadow-[0_2px_10px_rgba(37,99,235,0.06)] active:scale-95 transition">
                                <i class="bi {{ $q['icon'] }} text-[26px] text-blue-600"></i>
                                <span class="font-heading text-[13px] font-bold text-[#0B1F5C] dark:text-slate-200 mt-1.5 leading-tight">{{ $q['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- GitHub-style Monthly Attendance Tracker -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/50 dark:border-slate-800/60 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2 mb-3">
                        <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tần suất đi ca & Điểm danh</h3>
                        <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 bg-slate-100/50 dark:bg-slate-800/40 px-2 py-0.5 rounded-full border border-slate-200/30 dark:border-slate-700/30">Tháng {{ $now->month }}/{{ $now->year }}</span>
                    </div>

                    <div class="flex flex-col items-center gap-3">
                        <!-- GitHub style 7-column grid representing days of the month -->
                        <div class="grid grid-cols-7 gap-1.5 w-full max-w-xs mx-auto">
                            @php
                                $daysInMonth = $now->daysInMonth;
                                $monthSchedules = $myMonthlySchedules ?? collect();
                                $monthLogs = $myMonthlyLogs ?? collect();
                            @endphp
                            
                            @for ($d = 1; $d <= $daysInMonth; $d++)
                                @php
                                    $dSchedule = $monthSchedules->get($d);
                                    $dLog = $monthLogs->get($d);
                                    
                                    $tooltip = "Ngày $d: ";
                                    $colorClass = "bg-slate-100/50 dark:bg-slate-800/40 border border-slate-200/40 dark:border-slate-700/40 text-slate-400 dark:text-slate-500";
                                    
                                    if ($d > $now->day) {
                                        $colorClass = "bg-white/10 dark:bg-slate-900/20 border border-dashed border-slate-200 dark:border-slate-800";
                                        $tooltip .= "Chưa diễn ra";
                                    } else {
                                        if ($dSchedule) {
                                            if ($dSchedule->attendanceLog) {
                                                $log = $dSchedule->attendanceLog;
                                                if ($log->late_minutes > 0) {
                                                    $colorClass = "bg-rose-500 text-white border border-rose-600";
                                                    $tooltip .= "Đi trễ " . $log->late_minutes . " phút";
                                                } else {
                                                    $colorClass = "bg-emerald-500 text-white border border-emerald-600";
                                                    $tooltip .= "Đúng giờ (Vào lúc " . substr($log->check_in_at, 11, 5) . ")";
                                                }
                                            } else {
                                                if ($d === $now->day) {
                                                    $colorClass = "bg-blue-100/80 dark:bg-blue-900/30 border border-blue-400 text-blue-600 dark:text-blue-400";
                                                    $tooltip .= "Có ca hôm nay (Chưa check-in)";
                                                } else {
                                                    $colorClass = "bg-red-800 text-white border border-red-950";
                                                    $tooltip .= "Vắng mặt / Không đi ca";
                                                }
                                            }
                                        } else {
                                            if ($dLog) {
                                                $colorClass = "bg-emerald-400 text-white border border-emerald-500";
                                                $tooltip .= "Tăng ca / Đi làm tự do";
                                            } else {
                                                $colorClass = "bg-slate-200/60 dark:bg-slate-700/50 border border-slate-300/40 dark:border-slate-600/30";
                                                $tooltip .= "Ngày nghỉ";
                                            }
                                        }
                                    }
                                @endphp
                                
                                <!-- Box with onclick alert -->
                                <div class="relative group aspect-square flex items-center justify-center rounded-md {{ $colorClass }} text-[10px] font-bold shadow-none cursor-pointer hover:scale-105 transition-transform duration-150" 
                                     onclick="pcrmAlert('info', 'Chi tiết ngày {{ $d }}/{{ $now->month }}', '{{ $tooltip }}')">
                                    {{ $d }}
                                    <div class="hidden md:group-hover:block absolute bottom-full mb-1.5 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] rounded py-1 px-1.5 whitespace-nowrap z-50 shadow-md">
                                        {{ $tooltip }}
                                    </div>
                                </div>
                            @endfor
                        </div>

                        <!-- Legend -->
                        <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1.5 mt-2 border-t border-slate-100 dark:border-slate-800/80 pt-2.5 w-full text-[9px] font-bold text-slate-500 dark:text-slate-400">
                            <div class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded bg-emerald-500 border border-emerald-600 block"></span>
                                <span>Đúng giờ</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded bg-emerald-400 border border-emerald-500 block"></span>
                                <span>Tự do</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded bg-rose-500 border border-rose-600 block"></span>
                                <span>Đi trễ</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded bg-red-800 border border-red-950 block"></span>
                                <span>Vắng</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded bg-slate-200 dark:bg-slate-700 border border-slate-350 dark:border-slate-600 block"></span>
                                <span>Nghỉ</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Personal Penalties history -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/50 dark:border-slate-800/60 shadow-sm">
                    <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                        <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Lịch sử vi phạm</h3>
                        <span class="text-[11px] font-bold text-rose-500 bg-rose-50 dark:bg-rose-950/20 px-2 py-0.5 rounded-full">
                            {{ $myRecentPenalties->count() }} vi phạm
                        </span>
                    </div>
                    <div class="space-y-2.5">
                        @forelse($myRecentPenalties as $p)
                            <div class="flex items-start justify-between p-2.5 bg-slate-50/50 dark:bg-slate-950/20 rounded-xl border border-slate-100 dark:border-slate-800/30">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200 line-clamp-1">{{ $p->violation->name }}</p>
                                    <p class="text-[9px] text-slate-400 dark:text-slate-500 mt-1">{{ $p->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <div class="text-right shrink-0 ml-3">
                                    <span class="inline-flex px-1.5 py-0.5 text-[9px] font-bold rounded bg-red-100 dark:bg-red-950/40 text-red-700 dark:text-red-400">
                                        -{{ $p->total_points_deducted }}đ
                                    </span>
                                    @if($p->total_money_deducted > 0)
                                        <p class="text-[9px] font-bold text-slate-500 dark:text-slate-400 mt-1">-{{ number_format($p->total_money_deducted) }}đ</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-slate-400 dark:text-slate-500 text-xs">
                                <i class="bi bi-emoji-smile text-2xl opacity-60"></i>
                                <p class="text-xs mt-2 font-medium">Tuyệt vời! Bạn không có vi phạm nào gần đây.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@if (!$isAdmin)
    @can('checkin-attendance')
        @push('modals')
            <div id="quickEarlyConfirmModal"
                class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
                onclick="if(event.target===this)closeModal('quickEarlyConfirmModal')">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm p-4 sm:p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                            <i class="bi bi-clock-history text-amber-600 dark:text-amber-400 text-xl"></i>
                        </div>
                        <h3 class="font-semibold text-slate-900 dark:text-white" id="quickEarlyConfirmTitle">Xác nhận</h3>
                    </div>
                    <p class="text-sm text-slate-700 dark:text-slate-300 mb-5" id="quickEarlyConfirmMessage"></p>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('quickEarlyConfirmModal')"
                            class="btn-secondary flex-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold py-2 rounded-lg text-xs">Hủy</button>
                        <button type="button" id="quickEarlyConfirmProceedBtn" class="btn-primary flex-1 bg-pcrm-600 hover:bg-pcrm-700 text-white font-bold py-2 rounded-lg text-xs">Xác nhận</button>
                    </div>
                </div>
            </div>
        @endpush

        @push('scripts')
            <script>
                setInterval(function() {
                    var el = document.getElementById('quickAttClock');
                    if (el) el.textContent = new Date().toLocaleTimeString('vi-VN');
                }, 1000);

                function quickShowAttendanceMessage(shiftScheduleId, message, isError) {
                    const el = document.getElementById('attendanceMessage-' + (shiftScheduleId ?? 0));
                    if (!el) return;
                    el.textContent = message;
                    el.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-emerald-50', 'text-emerald-700', 'dark:bg-red-950/20', 'dark:text-red-400', 'dark:bg-emerald-950/20', 'dark:text-emerald-400');
                    if (isError) {
                        el.classList.add('bg-red-50', 'text-red-700', 'dark:bg-red-950/20', 'dark:text-red-400');
                    } else {
                        el.classList.add('bg-emerald-50', 'text-emerald-700', 'dark:bg-emerald-950/20', 'dark:text-emerald-400');
                    }
                    el.classList.remove('hidden');
                }

                function quickOpenEarlyConfirmModal(message, onConfirm) {
                    document.getElementById('quickEarlyConfirmMessage').textContent = message;
                    const btn = document.getElementById('quickEarlyConfirmProceedBtn');
                    const freshBtn = btn.cloneNode(true);
                    btn.parentNode.replaceChild(freshBtn, btn);
                    freshBtn.addEventListener('click', function() {
                        closeModal('quickEarlyConfirmModal');
                        onConfirm();
                    });
                    openModal('quickEarlyConfirmModal');
                }

                function quickDoAttendance(type, shiftScheduleId, boundaries) {
                    const suffix = shiftScheduleId ?? 0;
                    const btn = document.getElementById(
                        (type === 'check-in' ? 'btnCheckIn-' : 'btnCheckOut-') + suffix
                    );

                    function proceed() {
                        if (btn) {
                            btn.disabled = true;
                            btn.classList.add('opacity-60', 'cursor-not-allowed');
                        }

                        // Use actual user coordinates if fetched
                        function submit(lat, lng) {
                            fetch('{{ url('/attendance') }}/' + type, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        lat: lat,
                                        lng: lng,
                                        shift_schedule_id: shiftScheduleId
                                    }),
                                })
                                .then(res => res.json().then(data => ({
                                    status: res.status,
                                    body: data
                                })))
                                .then(({
                                    status,
                                    body
                                }) => {
                                    quickShowAttendanceMessage(shiftScheduleId, body.message, status !== 200);
                                    if (status === 200) {
                                        setTimeout(() => window.location.reload(), 1000);
                                    } else if (btn) {
                                        btn.disabled = false;
                                        btn.classList.remove('opacity-60', 'cursor-not-allowed');
                                    }
                                })
                                .catch(() => {
                                    quickShowAttendanceMessage(shiftScheduleId, 'Có lỗi xảy ra, vui lòng thử lại.', true);
                                    if (btn) {
                                        btn.disabled = false;
                                        btn.classList.remove('opacity-60', 'cursor-not-allowed');
                                    }
                                });
                        }

                        if (!navigator.geolocation) {
                            submit(null, null);
                            return;
                        }

                        navigator.geolocation.getCurrentPosition(
                            pos => submit(pos.coords.latitude, pos.coords.longitude),
                            () => {
                                quickShowAttendanceMessage(shiftScheduleId,
                                    'Không thể lấy vị trí GPS. Vui lòng cấp quyền định vị cho trình duyệt.', true);
                                if (btn) {
                                    btn.disabled = false;
                                    btn.classList.remove('opacity-60', 'cursor-not-allowed');
                                }
                            }, {
                                enableHighAccuracy: true,
                                timeout: 10000
                            }
                        );
                    }

                    const nowHM = new Date().toTimeString().slice(0, 5);

                    if (type === 'check-out' && boundaries.earlyCheckoutBoundary && nowHM < boundaries.earlyCheckoutBoundary) {
                        quickOpenEarlyConfirmModal(
                            `Hiện tại là ${nowHM}, ca kết thúc lúc ${boundaries.endTime}. Bạn có chắc muốn ra ca sớm không?`,
                            proceed
                        );
                        return;
                    }

                    proceed();
                }
            </script>
        @endpush
    @endcan
@endif
