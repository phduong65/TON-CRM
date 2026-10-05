@php
    // Danh sách tính năng cho ô search topbar — cùng danh sách trang + cùng điều kiện quyền với
    // sidebar (resources/views/components/sidebar.blade.php), để search không gợi ý trang mà
    // user không có quyền truy cập. "keywords" là các từ liên quan gõ tắt vẫn tìm ra được (VD gõ
    // "chấm công" ra cả cụm Ca làm việc & Chấm công, không chỉ đúng trang tên "Chấm công").
    $user = auth()->user();
    $topbarUnreadCount = \App\Models\Notification::where('user_id', auth()->id())
        ->whereNull('read_at')
        ->count();
    $topbarSearchItems = collect([
        ['label' => 'Bảng điều khiển', 'route' => 'dashboard', 'icon' => 'bi-speedometer2', 'perm' => null],
        ['label' => 'Thông báo', 'route' => 'notifications.index', 'icon' => 'bi-bell', 'perm' => null],
        ['label' => 'Hồ sơ của tôi', 'route' => 'profile.show', 'icon' => 'bi-person', 'perm' => null],
        ['label' => 'Nhân viên', 'route' => 'employees.index', 'icon' => 'bi-people', 'perm' => 'view-employees'],
        ['label' => 'Đội nhóm', 'route' => 'teams.index', 'icon' => 'bi-diagram-3', 'perm' => 'view-teams'],
        ['label' => 'Chi nhánh', 'route' => 'branches.index', 'icon' => 'bi-building', 'perm' => 'view-branches'],
        ['label' => 'Lịch làm việc', 'route' => 'my-schedule.index', 'icon' => 'bi-calendar3', 'perm' => 'view-own-schedule', 'keywords' => 'ca làm việc'],
        ['label' => 'Chấm công', 'route' => 'attendance.index', 'icon' => 'bi-fingerprint', 'perm' => 'checkin-attendance', 'keywords' => 'check in check out', 'requiresShift' => true],
        ['label' => 'Lịch sử chấm công', 'route' => 'my-attendance-logs.index', 'icon' => 'bi-list-check', 'perm' => 'view-own-attendance', 'keywords' => 'chấm công', 'requiresShift' => true],
        ['label' => 'Ca làm việc', 'route' => 'shifts.index', 'icon' => 'bi-clock-history', 'perm' => 'view-shifts', 'keywords' => 'mẫu ca chấm công'],
        ['label' => 'Ngày nghỉ lễ', 'route' => 'holidays.index', 'icon' => 'bi-calendar-event', 'perm' => 'view-holidays'],
        ['label' => 'Xếp ca', 'route' => 'shift-schedules.index', 'icon' => 'bi-calendar-week', 'perm' => 'view-shift-schedules', 'keywords' => 'xep ca lich cham cong'],
        ['label' => 'Điểm chấm công', 'route' => 'attendance-locations.index', 'icon' => 'bi-geo-alt', 'perm' => 'view-attendance-locations', 'keywords' => 'chấm công gps wifi'],
        ['label' => 'Báo cáo chấm công', 'route' => 'attendance-logs.index', 'icon' => 'bi-clipboard-check', 'perm' => 'view-attendance', 'keywords' => 'chấm công'],
        ['label' => 'Đơn & Phê duyệt', 'route' => 'staff-requests.index', 'icon' => 'bi-clipboard2-check', 'perm' => ['view-staff-requests', 'view-leave-requests', 'view-shift-swaps'], 'keywords' => 'nghỉ phép đổi ca tăng ca yêu cầu'],
        ['label' => 'Xử phạt', 'route' => 'penalties.index', 'icon' => 'bi-hammer', 'perm' => 'view-penalties'],
        ['label' => 'Thưởng điểm', 'route' => 'rewards.index', 'icon' => 'bi-gift', 'perm' => 'view-rewards'],
        ['label' => 'Báo cáo vi phạm', 'route' => 'reports.index', 'icon' => 'bi-flag', 'perm' => 'view-reports'],
        ['label' => 'Khiếu nại', 'route' => 'appeals.index', 'icon' => 'bi-chat-left-text', 'perm' => 'view-appeals'],
        ['label' => 'Import Chấm Công', 'route' => 'attendance-import.index', 'icon' => 'bi-file-earmark-arrow-up', 'perm' => 'import-attendance'],
        ['label' => 'Bảng xếp hạng', 'route' => 'rankings.index', 'icon' => 'bi-trophy', 'perm' => null],
        ['label' => 'Redzone', 'route' => 'redzone.index', 'icon' => 'bi-exclamation-octagon', 'perm' => 'view-redzone'],
        ['label' => 'Vi phạm', 'route' => 'violations.index', 'icon' => 'bi-book', 'perm' => 'view-violations'],
        ['label' => 'Loại thưởng', 'route' => 'reward-types.index', 'icon' => 'bi-star', 'perm' => 'view-reward-types'],
        ['label' => 'Danh sách quy chế', 'route' => 'regulations.index', 'icon' => 'bi-journal-check', 'perm' => 'view-regulations', 'keywords' => 'quy chế quy định'],
        ['label' => 'Danh mục thưởng', 'route' => 'reward-categories.index', 'icon' => 'bi-folder-check', 'perm' => 'view-reward-categories'],
        ['label' => 'Cài đặt', 'route' => 'settings.index', 'icon' => 'bi-gear', 'perm' => 'manage-settings'],
        ['label' => 'Google Sheets', 'route' => 'google-sheets.index', 'icon' => 'bi-file-earmark-spreadsheet', 'perm' => 'manage-settings'],
        ['label' => 'Nhật ký hoạt động', 'route' => 'activity.log', 'icon' => 'bi-clipboard-data', 'perm' => 'view-activity-log'],
        ['label' => 'System Logs', 'route' => 'log-viewer.index', 'icon' => 'bi-terminal', 'perm' => 'view-log-viewer'],
        ['label' => 'Tài khoản', 'route' => 'users.index', 'icon' => 'bi-people', 'perm' => 'manage-users'],
        ['label' => 'Vai trò & Quyền hạn', 'route' => 'roles.index', 'icon' => 'bi-shield-lock', 'perm' => 'manage-roles'],
    ])
    ->filter(function ($item) use ($user) {
        if (!empty($item['requiresShift']) && !$user->canSeeSelfAttendance()) {
            return false;
        }

        if (!$item['perm']) {
            return true;
        }

        return $user->canAny((array) $item['perm']);
    })
    ->map(fn($item) => [
        'label'    => $item['label'],
        'url'      => route($item['route']),
        'icon'     => $item['icon'],
        'keywords' => $item['keywords'] ?? '',
    ])
    ->values();

    $routeName = request()->route()?->getName() ?? '';
    $activeModule = match (true) {
        str_starts_with($routeName, 'employees') ||
            str_starts_with($routeName, 'teams') ||
            str_starts_with($routeName, 'branches')
            => 'nhansu',
        str_starts_with($routeName, 'penalties') ||
            str_starts_with($routeName, 'violations') ||
            str_starts_with($routeName, 'regulations') ||
            str_starts_with($routeName, 'rankings') ||
            str_starts_with($routeName, 'redzone')
            => 'thuongphat',
        str_starts_with($routeName, 'settings') || str_starts_with($routeName, 'activity') => 'caidat',
        str_starts_with($routeName, 'users') || str_starts_with($routeName, 'roles') => 'nguoidung',
        default => 'tongquan',
    };
    $thuongPhatActive = $activeModule === 'thuongphat';
@endphp

<header class="flex lg:hidden h-16 bg-white/95 dark:bg-slate-900/95 border-b border-slate-200 dark:border-slate-800 items-center justify-between sticky top-0 z-40 px-2 backdrop-blur-sm">
    <button type="button" onclick="toggleMobilePanel(event)" class="app-icon-button" aria-label="Mở menu chính" aria-controls="mobile-panel" aria-expanded="false">
        <i class="bi bi-list text-xl" aria-hidden="true"></i>
    </button>
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0 px-2" aria-label="TonHRM — Trang chủ">
        <x-tonhrm-logo type="primary" class="scale-90 origin-left" />
    </a>
    <div class="flex items-center">
        <a href="{{ route('notifications.index') }}" class="app-icon-button relative" aria-label="Thông báo">
            <i class="bi bi-bell text-lg" aria-hidden="true"></i>
            @if($topbarUnreadCount > 0)
                <span class="absolute top-1 right-1 min-w-4 h-4 px-1 flex items-center justify-center rounded-full bg-red-600 text-white text-[9px] font-bold">
                    {{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}
                </span>
            @endif
        </a>
        <a href="{{ route('profile.show') }}" class="app-icon-button" aria-label="Hồ sơ của tôi">
            <i class="bi bi-person-circle text-xl" aria-hidden="true"></i>
        </a>
    </div>
</header>

<header
    class="hidden lg:flex h-[70px] bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 items-stretch justify-between sticky top-0 z-40 shrink-0">

    <!-- Logo -->
    <div class="flex items-center">
        <a href="{{ route('menu.index') }}"
            class="lg:hidden flex items-center justify-center px-2 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <i class="bi bi-list text-xl"></i>
        </a>
        <a href="{{ route('dashboard') }}"
            class="flex items-center px-5 shrink-0" aria-label="TonHRM">
            <x-tonhrm-logo type="primary" />
        </a>
    </div>

    <!-- Search -->
    <div class="hidden md:flex flex-1 items-center px-4 max-w-xl">
        <div class="relative w-full" id="topbar-search">
            <label class="relative block">
                <span class="sr-only">Tìm kiếm</span>
                <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="topbar-search-input" autocomplete="off"
                    placeholder="Tìm chức năng: xếp ca, báo cáo chấm công..."
                    class="w-full h-11 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/40 pl-11 pr-4 text-sm text-slate-700 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:border-pcrm-500 focus:ring-4 focus:ring-pcrm-500/10 transition-colors">
            </label>
            <div id="topbar-search-results"
                class="hidden absolute left-0 right-0 mt-1.5 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xl z-[200] max-h-96 overflow-y-auto"></div>
            {{-- json_encode (không phải Js::from — cái đó bọc thành lệnh JS JSON.parse(...) để nhúng
                 vào thuộc tính/inline JS, không phải JSON thô) + JSON_HEX_TAG để "</script>" trong
                 dữ liệu không làm sập thẻ script. JS phía dưới tự JSON.parse() nội dung này. --}}
            <script type="application/json" id="topbar-search-data">{!! json_encode($topbarSearchItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
        </div>
    </div>


    <!-- Mobile hamburger -->


    {{--
        Tabs BEFORE the dropdown go inside the overflow-x-auto scroller.
        The dropdown itself MUST be a direct <header> child — any overflow:auto
        ancestor clips absolutely-positioned children.
    --}}

    <!-- Scrollable tabs (left group) -->
    {{-- <div class="flex items-stretch overflow-x-auto shrink-0" style="scrollbar-width:none;-ms-overflow-style:none;">
        <a href="{{ route('dashboard') }}"
           class="flex items-center px-4 text-sm font-medium whitespace-nowrap transition-colors border-b-2 {{ $activeModule === 'tongquan' ? 'border-pcrm-600 text-pcrm-700 dark:text-pcrm-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300' }}">
            Tổng quan
        </a>

        <a href="{{ route('employees.index') }}"
           class="flex items-center px-4 text-sm font-medium whitespace-nowrap transition-colors border-b-2 {{ $activeModule === 'nhansu' ? 'border-pcrm-600 text-pcrm-700 dark:text-pcrm-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300' }}">
            Nhân sự
        </a>

        <span title="Sắp ra mắt" class="flex items-center px-4 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-slate-300 dark:text-slate-600 cursor-not-allowed">Chấm công</span>
        <span title="Sắp ra mắt" class="flex items-center px-4 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-slate-300 dark:text-slate-600 cursor-not-allowed">Yêu cầu</span>
        <span title="Sắp ra mắt" class="flex items-center px-4 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-slate-300 dark:text-slate-600 cursor-not-allowed">Tiền lương</span>
    </div> --}}

    <!-- THƯỞNG PHẠT — plain tab; sub-navigation is handled by the left sidebar -->
    {{-- <a href="{{ route('penalties.index') }}"
       class="flex items-center gap-1.5 px-4 text-sm font-medium whitespace-nowrap transition-colors border-b-2 shrink-0 {{ $thuongPhatActive ? 'border-pcrm-600 text-pcrm-700 dark:text-pcrm-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300' }}">
        Thưởng phạt
        @if ($thuongPhatActive)
            <span class="w-1.5 h-1.5 rounded-full bg-pcrm-500 shrink-0"></span>
        @endif
    </a>

    <!-- Tabs after dropdown + spacer -->
    <div class="flex items-stretch flex-1">
        <span title="Sắp ra mắt" class="flex items-center px-4 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-slate-300 dark:text-slate-600 cursor-not-allowed">Báo cáo</span>
    </div> --}}

    <!-- Right actions -->
    <div class="flex items-center gap-1 px-3 shrink-0 border-l border-slate-100 dark:border-slate-700">

        <!-- Theme toggle -->
        <form action="{{ route('theme.toggle') }}" method="POST">
            @csrf
            <button type="submit"
                class="app-icon-button"
                aria-label="{{ auth()->user()->theme === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối' }}"
                title="{{ auth()->user()->theme === 'dark' ? 'Giao diện sáng' : 'Giao diện tối' }}">
                <i class="bi bi-sun text-base {{ auth()->user()->theme === 'dark' ? 'hidden' : '' }}"></i>
                <i class="bi bi-moon text-base {{ auth()->user()->theme === 'dark' ? '' : 'hidden' }}"></i>
            </button>
        </form>

        <!-- Bật thông báo đẩy Chrome (ẩn bởi JS nếu trình duyệt không hỗ trợ hoặc đã cấp quyền) -->
        <button id="pushEnableBtn" onclick="enablePushNotifications()"
            class="hidden app-icon-button"
            aria-label="Bật thông báo trình duyệt"
            title="Bật thông báo trình duyệt">
            <i class="bi bi-bell-slash text-base"></i>
        </button>

        <!-- Notifications -->
        @php
            $topbarNotifications = \App\Models\Notification::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->limit(6)
                ->get();
        @endphp
        <div class="relative" id="notif-dropdown">
            <button onclick="toggleNotifMenu()"
                class="app-icon-button relative"
                aria-label="Mở thông báo" aria-controls="notif-menu" aria-expanded="false"
                title="Thông báo">
                <i class="bi bi-bell text-base"></i>
                @if($topbarUnreadCount > 0)
                    <span class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-0.5 flex items-center justify-center rounded-full bg-red-500 text-white text-[9px] font-bold leading-none">
                        {{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}
                    </span>
                @endif
            </button>

            <!-- Notification dropdown -->
            {{-- Mobile: fixed + inset-x-2, anchored to the viewport (not the small bell button) so it can't run off-screen.
                 sm+: reverts to a normal absolute dropdown anchored under the bell button. --}}
            <div id="notif-menu"
                class="hidden fixed sm:absolute inset-x-2 sm:inset-x-auto top-[76px] sm:top-auto sm:right-0 sm:mt-1.5 w-auto sm:w-[360px] bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl z-[300] overflow-hidden">

                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3.5 border-b border-slate-100 dark:border-slate-700/70">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-slate-900 dark:text-white">Thông báo</span>
                        @if($topbarUnreadCount > 0)
                            <span class="min-w-[20px] h-5 px-1.5 flex items-center justify-center rounded-full text-[10px] font-bold bg-red-500 text-white leading-none">
                                {{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}
                            </span>
                        @endif
                    </div>
                    @if($topbarUnreadCount > 0)
                        <form action="{{ route('notifications.read-all') }}" method="POST">
                            @csrf
                            <button type="submit" class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:text-pcrm-700 dark:hover:text-pcrm-300 font-medium transition-colors">
                                Đọc tất cả
                            </button>
                        </form>
                    @endif
                </div>

                <!-- Notification items -->
                <div class="max-h-[360px] overflow-y-auto overscroll-contain divide-y divide-slate-50 dark:divide-slate-700/50">
                    @forelse($topbarNotifications as $n)
                        @php $unread = $n->isUnread(); @endphp
                        <a href="{{ route('notifications.show', $n) }}"
                           onclick="closeNotifMenu()"
                           class="flex items-start gap-3 px-4 py-3.5 transition-colors
                               {{ $unread
                                   ? 'bg-pcrm-50/70 dark:bg-pcrm-900/20 hover:bg-pcrm-50 dark:hover:bg-pcrm-900/30'
                                   : 'hover:bg-slate-50 dark:hover:bg-slate-700/30' }}">

                            {{-- Unread indicator --}}
                            <div class="mt-1 w-2 shrink-0 flex justify-center">
                                @if($unread)
                                    <span class="w-2 h-2 rounded-full bg-pcrm-500 shrink-0"></span>
                                @endif
                            </div>

                            {{-- Type icon --}}
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $n->typeColor() }}">
                                <i class="bi {{ $n->typeIcon() }} text-sm"></i>
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] {{ $unread ? 'font-semibold text-slate-900 dark:text-white' : 'font-medium text-slate-600 dark:text-slate-400' }} leading-snug line-clamp-1">
                                    {{ $n->title }}
                                </p>
                                @if($n->body)
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 line-clamp-2 leading-snug">{{ $n->body }}</p>
                                @endif
                                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1.5">
                                    {{ $n->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </a>
                    @empty
                        <div class="py-12 text-center text-slate-400 dark:text-slate-500">
                            <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                                <i class="bi bi-bell-slash text-xl opacity-60"></i>
                            </div>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Không có thông báo</p>
                        </div>
                    @endforelse
                </div>

                <!-- Footer -->
                <div class="border-t border-slate-100 dark:border-slate-700/70">
                    <a href="{{ route('notifications.index') }}"
                       onclick="closeNotifMenu()"
                       class="flex items-center justify-center gap-2 px-4 py-3 text-xs font-semibold text-pcrm-600 dark:text-pcrm-400 hover:bg-pcrm-50 dark:hover:bg-pcrm-900/20 transition-colors">
                        Xem tất cả thông báo
                        <i class="bi bi-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- User dropdown -->
        <div class="relative ml-1" id="user-dropdown">
            <button onclick="toggleUserMenu()"
                class="flex min-h-11 items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                aria-label="Mở menu tài khoản" aria-controls="user-menu" aria-expanded="false">
                @if(auth()->user()->avatar)
                    <img src="{{ asset(auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}"
                        class="w-8 h-8 rounded-full object-cover shrink-0">
                @else
                    <div
                        class="w-8 h-8 rounded-full bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center text-pcrm-700 dark:text-pcrm-400 font-bold text-sm shrink-0">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                @endif
                <div class="hidden md:block text-left">
                    <p
                        class="text-[13px] font-semibold text-slate-800 dark:text-slate-200 leading-tight max-w-[110px] truncate">
                        {{ auth()->user()->name }}</p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-tight">
                        @if (auth()->user()->hasRole('admin'))
                            Quản trị viên
                        @elseif(auth()->user()->hasRole('director'))
                            Giám đốc
                        @elseif(auth()->user()->hasRole('manager'))
                            Quản lý
                        @elseif(auth()->user()->hasRole('team-leader'))
                            Trưởng nhóm
                        @else
                            Nhân viên
                        @endif
                    </p>
                </div>
                <i class="bi bi-chevron-down text-[10px] text-slate-400 ml-0.5"></i>
            </button>

            <!-- User menu dropdown — enlarged -->
            <div id="user-menu"
                class="hidden absolute right-0 mt-1 w-72 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xl z-[200] overflow-hidden">

                <!-- User profile card -->
                <div
                    class="px-5 py-4 bg-gradient-to-br from-pcrm-50 to-white dark:from-pcrm-900/20 dark:to-slate-800 border-b border-slate-100 dark:border-slate-700">
                    <div class="flex items-center gap-3">
                        @if(auth()->user()->avatar)
                            <img src="{{ asset(auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}"
                                class="w-12 h-12 rounded-xl object-cover shrink-0">
                        @else
                            <div
                                class="w-12 h-12 rounded-xl bg-pcrm-100 dark:bg-pcrm-900/60 flex items-center justify-center text-pcrm-700 dark:text-pcrm-400 font-bold text-lg shrink-0">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <p class="text-[15px] font-bold text-slate-900 dark:text-white truncate">
                                {{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}
                            </p>
                            <span
                                class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-pcrm-100 dark:bg-pcrm-900/40 text-pcrm-700 dark:text-pcrm-400">
                                <i class="bi bi-shield-check text-[9px]"></i>
                                @if (auth()->user()->hasRole('admin'))
                                    Quản trị viên
                                @elseif(auth()->user()->hasRole('manager'))
                                    Quản lý
                                @elseif(auth()->user()->hasRole('team-leader'))
                                    Trưởng nhóm
                                @else
                                    Nhân viên
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Menu items -->
                <div class="py-1.5">
                    <a href="{{ route('profile.show') }}"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <span
                            class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                            <i class="bi bi-person text-sm text-slate-500 dark:text-slate-400"></i>
                        </span>
                        <div>
                            <p class="leading-tight">Hồ sơ của tôi</p>
                        </div>
                    </a>

                    @can('manage-settings')
                    <a href="{{ route('settings.index') }}"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <span
                            class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                            <i class="bi bi-gear text-sm text-slate-500 dark:text-slate-400"></i>
                        </span>
                        <div>
                            <p class="leading-tight">Cài đặt hệ thống</p>
                        </div>
                    </a>
                    @endcan

                    @can('view-activity-log')
                        <a href="{{ route('activity.log') }}"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <span
                                class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                                <i class="bi bi-clipboard-data text-sm text-slate-500 dark:text-slate-400"></i>
                            </span>
                            <div>
                                <p class="leading-tight">Nhật ký hoạt động</p>
                            </div>
                        </a>
                    @endcan
                </div>

                <div class="border-t border-slate-100 dark:border-slate-700 py-1.5">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            <span
                                class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center shrink-0">
                                <i class="bi bi-box-arrow-right text-sm text-red-500"></i>
                            </span>
                            <div>
                                <p class="leading-tight">Đăng xuất</p>
                            </div>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</header>

<script>
    function toggleUserMenu() {
        const menu = document.getElementById('user-menu');
        const button = document.querySelector('[aria-controls="user-menu"]');
        menu.classList.toggle('hidden');
        if (button) button.setAttribute('aria-expanded', menu.classList.contains('hidden') ? 'false' : 'true');
        document.getElementById('notif-menu').classList.add('hidden');
    }

    function toggleNotifMenu() {
        const menu = document.getElementById('notif-menu');
        const button = document.querySelector('[aria-controls="notif-menu"]');
        menu.classList.toggle('hidden');
        if (button) button.setAttribute('aria-expanded', menu.classList.contains('hidden') ? 'false' : 'true');
        document.getElementById('user-menu').classList.add('hidden');
    }

    function closeNotifMenu() {
        document.getElementById('notif-menu').classList.add('hidden');
        const button = document.querySelector('[aria-controls="notif-menu"]');
        if (button) button.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', function(e) {
        const userDropdown = document.getElementById('user-dropdown');
        const userMenu     = document.getElementById('user-menu');
        const notifDropdown = document.getElementById('notif-dropdown');
        const notifMenu    = document.getElementById('notif-menu');

        if (userDropdown && userMenu && !userDropdown.contains(e.target)) {
            userMenu.classList.add('hidden');
        }
        if (notifDropdown && notifMenu && !notifDropdown.contains(e.target)) {
            notifMenu.classList.add('hidden');
        }
    });

    // ── Search topbar: tìm chức năng theo tên (không phân biệt hoa/thường, có dấu/không dấu) ──
    (function () {
        const wrap = document.getElementById('topbar-search');
        if (!wrap) return;

        const input   = document.getElementById('topbar-search-input');
        const results = document.getElementById('topbar-search-results');
        const items   = JSON.parse(document.getElementById('topbar-search-data').textContent || '[]');

        // Xây range mã Unicode 0x0300-0x036f (dấu kết hợp — combining marks tách ra sau
        // normalize('NFD')) bằng String.fromCharCode thay vì gõ ký tự dấu trực tiếp vào regex
        // literal, để tránh lẫn ký tự không in được vào mã nguồn.
        const combiningMarksPattern = new RegExp('[' + String.fromCharCode(0x0300) + '-' + String.fromCharCode(0x036f) + ']', 'g');

        function normalize(str) {
            return (str || '')
                .normalize('NFD').replace(combiningMarksPattern, '')
                .replace(/đ/g, 'd').replace(/Đ/g, 'D')
                .toLowerCase();
        }

        const searchIndex = items.map(function (item) {
            return { item: item, haystack: normalize(item.label + ' ' + item.keywords) };
        });

        function renderResults(matches) {
            if (!matches.length) {
                results.innerHTML = '<div class="px-4 py-6 text-center text-sm text-slate-400">Không tìm thấy chức năng phù hợp</div>';
                results.classList.remove('hidden');
                return;
            }

            results.innerHTML = matches.slice(0, 8).map(function (item) {
                return '<a href="' + item.url + '" ' +
                    'class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">' +
                    '<span class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">' +
                    '<i class="bi ' + item.icon + ' text-sm text-slate-500 dark:text-slate-400"></i></span>' +
                    '<span>' + item.label + '</span></a>';
            }).join('');
            results.classList.remove('hidden');
        }

        function search(query) {
            const q = normalize(query.trim());
            if (!q) {
                results.classList.add('hidden');
                return;
            }
            renderResults(searchIndex.filter(function (row) { return row.haystack.includes(q); }).map(function (row) { return row.item; }));
        }

        input.addEventListener('input', function () { search(input.value); });
        input.addEventListener('focus', function () { if (input.value.trim()) search(input.value); });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                results.classList.add('hidden');
                input.blur();
            } else if (e.key === 'Enter') {
                const first = results.querySelector('a');
                if (first) {
                    e.preventDefault();
                    window.location.href = first.getAttribute('href');
                }
            }
        });

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) {
                results.classList.add('hidden');
            }
        });
    })();
</script>
