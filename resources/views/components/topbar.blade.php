@php
    $routeName = request()->route()?->getName() ?? '';
    $activeModule = match(true) {
        str_starts_with($routeName, 'employees') || str_starts_with($routeName, 'teams') || str_starts_with($routeName, 'branches') => 'nhansu',
        str_starts_with($routeName, 'penalties') || str_starts_with($routeName, 'violations') || str_starts_with($routeName, 'regulations') || str_starts_with($routeName, 'rankings') || str_starts_with($routeName, 'redzone') => 'thuongphat',
        str_starts_with($routeName, 'settings') || str_starts_with($routeName, 'activity') => 'caidat',
        str_starts_with($routeName, 'users') || str_starts_with($routeName, 'roles') => 'nguoidung',
        default => 'tongquan',
    };
    $thuongPhatActive = $activeModule === 'thuongphat';
@endphp

<header class="h-14 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-stretch sticky top-0 z-40 shrink-0">

    <!-- Logo -->
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-4 shrink-0 border-r border-slate-100 dark:border-slate-700">
        <div class="w-7 h-7 rounded-lg bg-pcrm-600 flex items-center justify-center text-white font-bold text-sm shrink-0">P</div>
        <span class="font-bold text-slate-900 dark:text-white text-[15px] tracking-tight hidden sm:block">P-CRM</span>
    </a>

    <!-- Mobile hamburger -->
    <button onclick="toggleMobilePanel()" class="lg:hidden flex items-center justify-center px-3 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
        <i class="bi bi-list text-xl"></i>
    </button>

    {{--
        Tabs BEFORE the dropdown go inside the overflow-x-auto scroller.
        The dropdown itself MUST be a direct <header> child — any overflow:auto
        ancestor clips absolutely-positioned children.
    --}}

    <!-- Scrollable tabs (left group) -->
    <div class="flex items-stretch overflow-x-auto shrink-0" style="scrollbar-width:none;-ms-overflow-style:none;">
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
    </div>

    <!-- THƯỞNG PHẠT — plain tab; sub-navigation is handled by the left sidebar -->
    <a href="{{ route('penalties.index') }}"
       class="flex items-center gap-1.5 px-4 text-sm font-medium whitespace-nowrap transition-colors border-b-2 shrink-0 {{ $thuongPhatActive ? 'border-pcrm-600 text-pcrm-700 dark:text-pcrm-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300' }}">
        Thưởng phạt
        @if($thuongPhatActive)
            <span class="w-1.5 h-1.5 rounded-full bg-pcrm-500 shrink-0"></span>
        @endif
    </a>

    <!-- Tabs after dropdown + spacer -->
    <div class="flex items-stretch flex-1">
        <span title="Sắp ra mắt" class="flex items-center px-4 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-slate-300 dark:text-slate-600 cursor-not-allowed">Báo cáo</span>
    </div>

    <!-- Người dùng tab — chỉ hiển thị với admin -->
    @canany(['manage-users', 'manage-roles'])
    <a href="{{ route('users.index') }}"
       class="flex items-center gap-1.5 px-4 text-sm font-medium whitespace-nowrap transition-colors border-b-2 shrink-0 {{ $activeModule === 'nguoidung' ? 'border-pcrm-600 text-pcrm-700 dark:text-pcrm-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300' }}">
        <i class="bi bi-people text-xs"></i>
        Người dùng
    </a>
    @endcanany

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
        <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="Thông báo">
            <i class="bi bi-bell text-base"></i>
        </button>

        <!-- User dropdown -->
        <div class="relative ml-1" id="user-dropdown">
            <button onclick="toggleUserMenu()"
                    class="flex items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <div class="w-8 h-8 rounded-full bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center text-pcrm-700 dark:text-pcrm-400 font-bold text-sm shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="hidden md:block text-left">
                    <p class="text-[13px] font-semibold text-slate-800 dark:text-slate-200 leading-tight max-w-[110px] truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-tight">
                        @if(auth()->user()->hasRole('admin')) Quản trị viên
                        @elseif(auth()->user()->hasRole('manager')) Quản lý
                        @elseif(auth()->user()->hasRole('team-leader')) Trưởng nhóm
                        @else Nhân viên
                        @endif
                    </p>
                </div>
                <i class="bi bi-chevron-down text-[10px] text-slate-400 ml-0.5"></i>
            </button>

            <!-- User menu dropdown — enlarged -->
            <div id="user-menu"
                 class="hidden absolute right-0 mt-1 w-72 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xl z-[200] overflow-hidden">

                <!-- User profile card -->
                <div class="px-5 py-4 bg-gradient-to-br from-pcrm-50 to-white dark:from-pcrm-900/20 dark:to-slate-800 border-b border-slate-100 dark:border-slate-700">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-pcrm-100 dark:bg-pcrm-900/60 flex items-center justify-center text-pcrm-700 dark:text-pcrm-400 font-bold text-lg shrink-0">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-[15px] font-bold text-slate-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
                            <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-pcrm-100 dark:bg-pcrm-900/40 text-pcrm-700 dark:text-pcrm-400">
                                <i class="bi bi-shield-check text-[9px]"></i>
                                @if(auth()->user()->hasRole('admin')) Quản trị viên
                                @elseif(auth()->user()->hasRole('manager')) Quản lý
                                @elseif(auth()->user()->hasRole('team-leader')) Trưởng nhóm
                                @else Nhân viên
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Menu items -->
                <div class="py-1.5">
                    <a href="{{ route('settings.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <span class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                            <i class="bi bi-person text-sm text-slate-500 dark:text-slate-400"></i>
                        </span>
                        <div>
                            <p class="leading-tight">Hồ sơ của tôi</p>
                            <p class="text-[11px] text-slate-400 font-normal leading-tight">Thông tin tài khoản</p>
                        </div>
                    </a>

                    <a href="{{ route('settings.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <span class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                            <i class="bi bi-gear text-sm text-slate-500 dark:text-slate-400"></i>
                        </span>
                        <div>
                            <p class="leading-tight">Cài đặt hệ thống</p>
                            <p class="text-[11px] text-slate-400 font-normal leading-tight">Cấu hình & tham số</p>
                        </div>
                    </a>

                    @can('view-activity-log')
                    <a href="{{ route('activity.log') }}"
                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <span class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                            <i class="bi bi-clipboard-data text-sm text-slate-500 dark:text-slate-400"></i>
                        </span>
                        <div>
                            <p class="leading-tight">Nhật ký hoạt động</p>
                            <p class="text-[11px] text-slate-400 font-normal leading-tight">Lịch sử thao tác hệ thống</p>
                        </div>
                    </a>
                    @endcan
                </div>

                <div class="border-t border-slate-100 dark:border-slate-700 py-1.5">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            <span class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center shrink-0">
                                <i class="bi bi-box-arrow-right text-sm text-red-500"></i>
                            </span>
                            <div>
                                <p class="leading-tight">Đăng xuất</p>
                                <p class="text-[11px] text-red-400 font-normal leading-tight">Thoát khỏi tài khoản</p>
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
    }

    document.addEventListener('click', function (e) {
        const userDropdown = document.getElementById('user-dropdown');
        const userMenu     = document.getElementById('user-menu');
        if (userDropdown && userMenu && !userDropdown.contains(e.target)) {
            userMenu.classList.add('hidden');
        }
    });
</script>
