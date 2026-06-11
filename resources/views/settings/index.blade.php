@extends('layouts.admin')

@section('title', 'Cài đặt')
@section('page-title', 'Cài đặt')
@section('breadcrumb', 'Hệ thống / Cài đặt')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Settings Form -->
        <div class="lg:col-span-2 space-y-6">

            {{-- ── Thông tin chung ── --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="ph-gear text-pcrm-500"></i> Cấu hình chung
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="s_company_name" class="form-label">Tên công ty</label>
                            <input type="text" name="settings[company_name]" id="s_company_name"
                                class="form-input" value="{{ old('settings.company_name', $settings->get('company_name')->value ?? '') }}"
                                placeholder="Công ty TNHH...">
                        </div>

                        <div>
                            <label for="s_default_score" class="form-label">Điểm mặc định hàng tháng</label>
                            <input type="number" name="settings[default_score_per_month]" id="s_default_score"
                                class="form-input" value="{{ old('settings.default_score_per_month', $settings->get('default_score_per_month')->value ?? 100) }}"
                                min="1" max="1000" placeholder="100">
                            <p class="text-xs text-slate-400 mt-1">Số điểm mỗi nhân viên được cấp đầu tháng. Reset vào ngày 1 hàng tháng.</p>
                        </div>

                        <div>
                            <label for="s_rows_per_page" class="form-label">Số dòng mỗi trang</label>
                            <select name="settings[rows_per_page]" id="s_rows_per_page" class="form-select">
                                @foreach([10, 15, 20, 25, 50] as $n)
                                    <option value="{{ $n }}" {{ (old('settings.rows_per_page', $settings->get('rows_per_page')->value ?? 15) == $n) ? 'selected' : '' }}>
                                        {{ $n }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
                            <button type="submit" class="btn-primary">
                                <i class="ph-floppy-disk"></i>
                                <span>Lưu cài đặt chung</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ── Cấu hình Zone điểm ── --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="ph-chart-bar text-pcrm-500"></i> Phân vùng điểm (Zone System)
                    </h3>
                </div>
                <div class="card-body">

                    {{-- Zone preview bar --}}
                    <div class="grid grid-cols-4 gap-2 mb-5 text-center text-xs">
                        <div class="rounded-xl p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
                            <div class="text-2xl mb-1">🟢</div>
                            <div class="font-bold text-emerald-700 dark:text-emerald-400">Greenzone</div>
                            <div class="text-emerald-600 dark:text-emerald-500 mt-0.5">
                                ≥ {{ $settings->get('greenzone_min')->value ?? 90 }}đ
                            </div>
                        </div>
                        <div class="rounded-xl p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
                            <div class="text-2xl mb-1">🟡</div>
                            <div class="font-bold text-yellow-700 dark:text-yellow-400">Yellowzone</div>
                            <div class="text-yellow-600 dark:text-yellow-500 mt-0.5">
                                {{ $settings->get('yellowzone_min')->value ?? 80 }}–{{ ($settings->get('greenzone_min')->value ?? 90) - 1 }}đ
                            </div>
                        </div>
                        <div class="rounded-xl p-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
                            <div class="text-2xl mb-1">🟠</div>
                            <div class="font-bold text-orange-700 dark:text-orange-400">Orangezone</div>
                            <div class="text-orange-600 dark:text-orange-500 mt-0.5">
                                {{ $settings->get('orangezone_min')->value ?? 70 }}–{{ ($settings->get('yellowzone_min')->value ?? 80) - 1 }}đ
                            </div>
                        </div>
                        <div class="rounded-xl p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                            <div class="text-2xl mb-1">🔴</div>
                            <div class="font-bold text-red-700 dark:text-red-400">Redzone</div>
                            <div class="text-red-600 dark:text-red-500 mt-0.5">
                                < {{ $settings->get('orangezone_min')->value ?? 70 }}đ
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                        @csrf

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label for="s_greenzone_min" class="form-label text-emerald-700 dark:text-emerald-400">
                                    🟢 Ngưỡng Greenzone (điểm tối thiểu)
                                </label>
                                <input type="number" name="settings[greenzone_min]" id="s_greenzone_min"
                                    class="form-input border-emerald-300 dark:border-emerald-700 focus:ring-emerald-500"
                                    value="{{ old('settings.greenzone_min', $settings->get('greenzone_min')->value ?? 90) }}"
                                    min="1" max="100">
                            </div>
                            <div>
                                <label for="s_yellowzone_min" class="form-label text-yellow-700 dark:text-yellow-400">
                                    🟡 Ngưỡng Yellowzone (điểm tối thiểu)
                                </label>
                                <input type="number" name="settings[yellowzone_min]" id="s_yellowzone_min"
                                    class="form-input border-yellow-300 dark:border-yellow-700 focus:ring-yellow-500"
                                    value="{{ old('settings.yellowzone_min', $settings->get('yellowzone_min')->value ?? 80) }}"
                                    min="1" max="100">
                            </div>
                            <div>
                                <label for="s_orangezone_min" class="form-label text-orange-700 dark:text-orange-400">
                                    🟠 Ngưỡng Orangezone (điểm tối thiểu)
                                </label>
                                <input type="number" name="settings[orangezone_min]" id="s_orangezone_min"
                                    class="form-input border-orange-300 dark:border-orange-700 focus:ring-orange-500"
                                    value="{{ old('settings.orangezone_min', $settings->get('orangezone_min')->value ?? 70) }}"
                                    min="1" max="100">
                            </div>
                        </div>
                        <p class="text-xs text-slate-400">
                            Redzone = điểm &lt; Ngưỡng Orangezone. Thứ tự: Greenzone &gt; Yellowzone &gt; Orangezone &gt; Redzone.
                        </p>

                        <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                            <label for="s_consecutive" class="form-label">
                                Số tháng Redzone liên tiếp để cảnh báo xử phạt đặc biệt
                            </label>
                            <div class="flex items-center gap-3">
                                <input type="number" name="settings[consecutive_redzone_months]" id="s_consecutive"
                                    class="form-input w-28"
                                    value="{{ old('settings.consecutive_redzone_months', $settings->get('consecutive_redzone_months')->value ?? 2) }}"
                                    min="1" max="12">
                                <span class="text-sm text-slate-500 dark:text-slate-400">tháng</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">
                                Khi nhân viên đạt đủ số tháng này trong Redzone liên tiếp, hệ thống sẽ gửi cảnh báo cho quản lý.
                                Chạy lệnh <code class="bg-slate-100 dark:bg-slate-700 px-1 rounded text-[11px]">php artisan scores:check-consecutive-redzone</code> để kiểm tra.
                            </p>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn-primary">
                                <i class="ph-floppy-disk"></i>
                                <span>Lưu cài đặt zone</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ── Reset điểm hàng tháng ── --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="ph-calendar-check text-pcrm-500"></i> Reset điểm hàng tháng
                    </h3>
                </div>
                <div class="card-body space-y-3">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Mỗi đầu tháng, hệ thống tự động cấp lại <strong>{{ $settings->get('default_score_per_month')->value ?? 100 }} điểm</strong>
                        cho mỗi nhân viên đang hoạt động. Dữ liệu tháng cũ được giữ nguyên để đánh giá.
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Để chạy thủ công, sử dụng lệnh artisan:
                    </p>
                    <div class="bg-slate-100 dark:bg-slate-800 rounded-lg p-3 font-mono text-xs text-slate-700 dark:text-slate-300 space-y-1">
                        <div># Khởi tạo điểm cho tháng hiện tại</div>
                        <div class="text-pcrm-600 dark:text-pcrm-400">php artisan scores:reset-monthly</div>
                        <div class="mt-2"># Khởi tạo cho tháng cụ thể (vd: tháng 7/2026)</div>
                        <div class="text-pcrm-600 dark:text-pcrm-400">php artisan scores:reset-monthly --month=7 --year=2026</div>
                        <div class="mt-2"># Kiểm tra Redzone liên tiếp</div>
                        <div class="text-pcrm-600 dark:text-pcrm-400">php artisan scores:check-consecutive-redzone</div>
                    </div>
                    <p class="text-xs text-slate-400">
                        Lệnh reset được tự động chạy vào <strong>00:00 ngày 1 hàng tháng</strong> qua Laravel Scheduler.
                    </p>
                </div>
            </div>

        </div>

        <!-- Sidebar Info -->
        <div class="space-y-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Thông tin hệ thống</h4>
                </div>
                <div class="card-body space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Phiên bản</span>
                        <span class="font-medium">1.0.0</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Laravel</span>
                        <span class="font-medium">{{ app()->version() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">PHP</span>
                        <span class="font-medium">{{ PHP_VERSION }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Giao diện</span>
                        <span class="font-medium">{{ auth()->user()->theme === 'dark' ? 'Tối' : 'Sáng' }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Tài khoản</h4>
                </div>
                <div class="card-body space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Tên</span>
                        <span class="font-medium">{{ auth()->user()->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Email</span>
                        <span class="font-medium">{{ auth()->user()->email }}</span>
                    </div>
                </div>
            </div>

            <!-- Zone summary this month -->
            <div class="card">
                <div class="card-header">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Zone tháng này</h4>
                </div>
                <div class="card-body space-y-2 text-sm">
                    <a href="{{ route('redzone.index') }}" class="flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg px-2 py-1 transition-colors">
                        <span class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>Greenzone
                        </span>
                        <i class="ph-arrow-right text-slate-400 text-xs"></i>
                    </a>
                    <a href="{{ route('redzone.index') }}" class="flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg px-2 py-1 transition-colors">
                        <span class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 inline-block"></span>Yellowzone
                        </span>
                        <i class="ph-arrow-right text-slate-400 text-xs"></i>
                    </a>
                    <a href="{{ route('redzone.index') }}" class="flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg px-2 py-1 transition-colors">
                        <span class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <span class="w-2.5 h-2.5 rounded-full bg-orange-500 inline-block"></span>Orangezone
                        </span>
                        <i class="ph-arrow-right text-slate-400 text-xs"></i>
                    </a>
                    <a href="{{ route('redzone.index') }}" class="flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg px-2 py-1 transition-colors">
                        <span class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span>Redzone
                        </span>
                        <i class="ph-arrow-right text-slate-400 text-xs"></i>
                    </a>
                </div>
            </div>

            <!-- Theme toggle shortcut -->
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('theme.toggle') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-secondary w-full">
                            <i class="ph-sun {{ auth()->user()->theme === 'dark' ? '' : 'hidden' }}"></i>
                            <i class="ph-moon {{ auth()->user()->theme === 'dark' ? 'hidden' : '' }}"></i>
                            <span>Chuyển sang giao diện {{ auth()->user()->theme === 'dark' ? 'sáng' : 'tối' }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
