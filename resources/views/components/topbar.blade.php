@php
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

<header
    class="h-14 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-stretch justify-between sticky top-0 z-40 shrink-0">

    <!-- Logo -->
    <div class="flex">
        <button onclick="toggleMobilePanel()"
            class="lg:hidden flex items-center justify-center px-2 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <i class="bi bi-list text-xl"></i>
        </button>
        <a href="{{ route('dashboard') }}"
            class="flex items-center gap-2.5 px-4 shrink-0 border-slate-100 dark:border-slate-700">
            <img src="{{ asset('assets/images/ton-capital-logo.png') }}" alt="TON CAPITAL"
                class="w-8 h-8 rounded-lg object-cover shrink-0">
            <span
                class="font-bold text-slate-900 dark:text-white text-[15px] tracking-tight hidden sm:block">TON-HR</span>
        </a>
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
                class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                title="{{ auth()->user()->theme === 'dark' ? 'Giao diện sáng' : 'Giao diện tối' }}">
                <i class="bi bi-sun text-base {{ auth()->user()->theme === 'dark' ? 'hidden' : '' }}"></i>
                <i class="bi bi-moon text-base {{ auth()->user()->theme === 'dark' ? '' : 'hidden' }}"></i>
            </button>
        </form>

        <!-- Notifications -->
        @php
            $topbarUnreadCount  = \App\Models\Notification::where('user_id', auth()->id())->whereNull('read_at')->count();
            $topbarNotifications = \App\Models\Notification::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->limit(6)
                ->get();
        @endphp
        <div class="relative" id="notif-dropdown">
            <button onclick="toggleNotifMenu()"
                class="relative w-8 h-8 flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                title="Thông báo">
                <i class="bi bi-bell text-base"></i>
                @if($topbarUnreadCount > 0)
                    <span class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-0.5 flex items-center justify-center rounded-full bg-red-500 text-white text-[9px] font-bold leading-none">
                        {{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}
                    </span>
                @endif
            </button>

            <!-- Notification dropdown -->
            <div id="notif-menu"
                class="hidden max-sm:fixed max-sm:top-14 max-sm:inset-x-2 max-sm:w-auto max-sm:mt-0 sm:absolute sm:right-0 sm:mt-1.5 sm:w-[360px] bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl z-[300] overflow-hidden">

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
                class="flex items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <div
                    class="w-8 h-8 rounded-full bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center text-pcrm-700 dark:text-pcrm-400 font-bold text-sm shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="hidden md:block text-left">
                    <p
                        class="text-[13px] font-semibold text-slate-800 dark:text-slate-200 leading-tight max-w-[110px] truncate">
                        {{ auth()->user()->name }}</p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-tight">
                        @if (auth()->user()->hasRole('admin'))
                            Quản trị viên
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
                        <div
                            class="w-12 h-12 rounded-xl bg-pcrm-100 dark:bg-pcrm-900/60 flex items-center justify-center text-pcrm-700 dark:text-pcrm-400 font-bold text-lg shrink-0">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
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
        document.getElementById('user-menu').classList.toggle('hidden');
        document.getElementById('notif-menu').classList.add('hidden');
    }

    function toggleNotifMenu() {
        document.getElementById('notif-menu').classList.toggle('hidden');
        document.getElementById('user-menu').classList.add('hidden');
    }

    function closeNotifMenu() {
        document.getElementById('notif-menu').classList.add('hidden');
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
</script>
