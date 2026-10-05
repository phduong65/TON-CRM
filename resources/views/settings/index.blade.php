@extends('layouts.admin')

@section('title', 'Cài đặt')
@section('page-title', 'Cài đặt')
@section('page-subtitle', 'Cấu hình điểm, Redzone, công ty và các tham số hệ thống')
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
                            <select name="settings[rows_per_page]" id="s_rows_per_page" class="form-input form-select">
                                @foreach([10, 15, 20, 25, 50] as $n)
                                    <option value="{{ $n }}" {{ (old('settings.rows_per_page', $settings->get('rows_per_page')->value ?? 15) == $n) ? 'selected' : '' }}>
                                        {{ $n }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="s_report_reward_points" class="form-label">Điểm thưởng báo cáo vi phạm</label>
                            <input type="number" name="settings[report_reward_points]" id="s_report_reward_points"
                                class="form-input" value="{{ old('settings.report_reward_points', $settings->get('report_reward_points')->value ?? 5) }}"
                                min="0" max="100" placeholder="5">
                            <p class="text-xs text-slate-400 mt-1">Số điểm cộng cho nhân viên khi báo cáo vi phạm của họ được duyệt.</p>
                        </div>

                        <div>
                            <label for="s_service_charge" class="form-label">Service Charge (VNĐ)</label>
                            <input type="number" name="settings[service_charge]" id="s_service_charge"
                                class="form-input" value="{{ old('settings.service_charge', $settings->get('service_charge')->value ?? 0) }}"
                                min="0" placeholder="0">
                            <p class="text-xs text-slate-400 mt-1">Phí dịch vụ hiển thị trên bảng điều khiển.</p>
                        </div>

                        <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
                            <button type="submit" class="btn-primary">
                                <i class="ph-floppy-disk"></i>
                                <span>Lưu cài đặt chung</span>
                            </button>
                        </div>
                    </form>

                    @if(auth()->user()->hasRole('admin'))
                    <div class="mt-5 pt-5 border-t border-slate-200 dark:border-slate-700">
                        <label class="form-label flex items-center gap-2">
                            <i class="bi bi-lock-fill text-slate-400 text-xs"></i>
                            Tổng tiền đã trừ (VNĐ)
                        </label>
                        <input type="text" disabled
                            class="form-input bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 cursor-not-allowed"
                            value="{{ number_format($totalMoneyDeducted ?? 0, 0, ',', '.') }}">
                        <p class="text-xs text-slate-400 mt-1">Tổng tiền phạt từ tất cả phiếu phạt đã được duyệt. Chỉ đọc.</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── Xác thực chấm công ── --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="ph-map-pin text-pcrm-500"></i> Xác thực chấm công
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                        @csrf

                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Chọn phương thức xác thực vị trí khi nhân viên check-in/check-out. Bật cái nào thì hệ thống kiểm tra cái đó;
                            bật cả hai thì phải đạt <strong>cả hai</strong> mới cho chấm công. Nếu tắt cả hai, hệ thống bỏ qua xác thực vị trí
                            (áp dụng cho mọi ca trừ ca WFH — ca WFH luôn bỏ qua xác thực).
                        </p>

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="settings[attendance_verify_gps]" value="0">
                            <input type="checkbox" id="s_verify_gps" name="settings[attendance_verify_gps]" value="1"
                                class="mt-1 rounded border-slate-300 dark:border-slate-600 text-pcrm-600"
                                {{ old('settings.attendance_verify_gps', $settings->get('attendance_verify_gps')->value ?? '1') != '0' ? 'checked' : '' }}>
                            <label for="s_verify_gps" class="text-sm">
                                <span class="font-medium text-slate-900 dark:text-white">Xác thực bằng GPS</span>
                                <p class="text-xs text-slate-400">Yêu cầu vị trí GPS nằm trong bán kính cho phép của điểm chấm công.</p>
                            </label>
                        </div>

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="settings[attendance_verify_wifi]" value="0">
                            <input type="checkbox" id="s_verify_wifi" name="settings[attendance_verify_wifi]" value="1"
                                class="mt-1 rounded border-slate-300 dark:border-slate-600 text-pcrm-600"
                                {{ old('settings.attendance_verify_wifi', $settings->get('attendance_verify_wifi')->value ?? '1') != '0' ? 'checked' : '' }}>
                            <label for="s_verify_wifi" class="text-sm">
                                <span class="font-medium text-slate-900 dark:text-white">Xác thực bằng WiFi văn phòng</span>
                                <p class="text-xs text-slate-400">Yêu cầu IP kết nối khớp danh sách IP văn phòng đã cấu hình cho chi nhánh.</p>
                            </label>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn-primary">
                                <i class="ph-floppy-disk"></i>
                                <span>Lưu cài đặt xác thực</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ── Xác nhận công ── --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="ph-calendar-check text-pcrm-500"></i> Xác nhận công
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                        @csrf

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="settings[timesheet_confirmation_enabled]" value="0">
                            <input type="checkbox" id="s_timesheet_confirmation_enabled" name="settings[timesheet_confirmation_enabled]" value="1"
                                class="mt-1 rounded border-slate-300 dark:border-slate-600 text-pcrm-600"
                                {{ old('settings.timesheet_confirmation_enabled', $settings->get('timesheet_confirmation_enabled')->value ?? '0') != '0' ? 'checked' : '' }}>
                            <label for="s_timesheet_confirmation_enabled" class="text-sm">
                                <span class="font-medium text-slate-900 dark:text-white">Bật tính năng "Xác nhận công"</span>
                                <p class="text-xs text-slate-400">Khi bật, nhân viên có thể xem/xác nhận bảng công tháng của mình; HR có thể xem trạng thái xác nhận của tất cả nhân viên và xác nhận hộ.</p>
                            </label>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn-primary">
                                <i class="ph-floppy-disk"></i>
                                <span>Lưu cài đặt</span>
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

            {{-- ── Kiểm tra kết nối Firebase (Web Push) ── --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="ph-bell-ringing text-pcrm-500"></i> Thông báo đẩy (Firebase Cloud Messaging)
                    </h3>
                </div>
                <div class="card-body space-y-3">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-slate-500 dark:text-slate-400">Trạng thái cấu hình server:</span>
                        @if($firebaseEnabled)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
                                <i class="bi bi-check-circle-fill"></i> Đã cấu hình
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400">
                                <i class="bi bi-x-circle-fill"></i> Chưa cấu hình
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Bấm nút bên dưới để gửi 1 thông báo test tới trình duyệt hiện tại của bạn qua Firebase.
                        Nếu chưa bật nhận thông báo, hãy bấm chuông <i class="bi bi-bell"></i> trên thanh Topbar trước.
                    </p>
                    <button type="button" id="testFirebaseBtn" onclick="testFirebaseConnection()" class="btn-secondary">
                        <i class="bi bi-broadcast"></i>
                        <span>Kiểm tra kết nối</span>
                    </button>
                    <div id="testFirebaseResult" class="hidden text-sm rounded-lg px-3 py-2"></div>
                </div>
            </div>

            {{-- ── Kiểm tra Cron Job (Laravel Scheduler) ── --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="ph-clock-countdown text-pcrm-500"></i> Cron Job (Scheduler)
                    </h3>
                </div>
                <div class="card-body space-y-3">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Kiểm tra xem cron job trên server có đang gọi <code>php artisan schedule:run</code>
                        mỗi phút hay không — cần thiết để các tính năng tự động (reset điểm hàng tháng,
                        nhắc check-in/check-out, sinh ca lặp lại...) hoạt động đúng lịch.
                    </p>
                    <button type="button" id="testSchedulerBtn" onclick="testScheduler()" class="btn-secondary">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Kiểm tra Cron Job</span>
                    </button>
                    <div id="testSchedulerResult" class="hidden text-sm rounded-lg px-3 py-2"></div>
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

    @push('scripts')
        <script>
            function testFirebaseConnection(isRetry) {
                var btn = document.getElementById('testFirebaseBtn');
                var box = document.getElementById('testFirebaseResult');

                btn.disabled = true;
                btn.classList.add('opacity-60', 'cursor-not-allowed');
                box.className = 'text-sm rounded-lg px-3 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
                box.textContent = 'Đang gửi thông báo test...';

                return fetch('{{ route('settings.test-firebase') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            box.className = 'text-sm rounded-lg px-3 py-2 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400';
                            box.textContent = 'Kết nối thành công! Đã gửi tới ' + data.success_count + '/' + data.token_count + ' trình duyệt. Kiểm tra thông báo vừa nhận được.';
                            return;
                        }

                        // Token hiện tại bị Firebase từ chối (stale/unregistered) — server đã tự
                        // xoá token đó khỏi DB, nhưng trình duyệt vẫn cache đúng token cũ đó. Tự
                        // làm mới token 1 lần rồi test lại, tránh bắt người dùng tự xoá cache.
                        if (data.reason === 'send_failed' && !isRetry && window.refreshPushToken) {
                            box.textContent = 'Token cũ không còn hợp lệ, đang tự làm mới token...';
                            return window.refreshPushToken()
                                .then(function () { return testFirebaseConnection(true); })
                                .catch(function () {
                                    box.className = 'text-sm rounded-lg px-3 py-2 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
                                    box.textContent = 'Không thể tự làm mới token. Hãy bấm chuông thông báo trên thanh Topbar để đăng ký lại.';
                                });
                        }

                        var messages = {
                            not_configured: 'Server chưa cấu hình Firebase (thiếu file service-account.json). Xem FIREBASE_CREDENTIALS trong .env.',
                            no_tokens: 'Trình duyệt này chưa đăng ký nhận thông báo. Hãy bấm chuông thông báo trên thanh Topbar rồi thử lại.',
                            send_failed: 'Firebase từ chối gửi (' + data.failure_count + '/' + data.token_count + ' token lỗi): ' + (data.error || 'Không rõ lý do.'),
                            exception: 'Gửi thất bại: ' + (data.error || 'Lỗi không xác định từ Firebase.'),
                        };

                        box.className = 'text-sm rounded-lg px-3 py-2 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
                        box.textContent = messages[data.reason] || 'Không thể xác định lỗi.';
                    })
                    .catch(function () {
                        box.className = 'text-sm rounded-lg px-3 py-2 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
                        box.textContent = 'Không thể kết nối tới server. Vui lòng thử lại.';
                    })
                    .finally(function () {
                        box.classList.remove('hidden');
                        btn.disabled = false;
                        btn.classList.remove('opacity-60', 'cursor-not-allowed');
                    });
            }

            function testScheduler() {
                var btn = document.getElementById('testSchedulerBtn');
                var box = document.getElementById('testSchedulerResult');

                btn.disabled = true;
                btn.classList.add('opacity-60', 'cursor-not-allowed');
                box.className = 'text-sm rounded-lg px-3 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
                box.textContent = 'Đang kiểm tra...';

                return fetch('{{ route('settings.check-scheduler') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            box.className = 'text-sm rounded-lg px-3 py-2 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400';
                            box.textContent = 'Cron job đang hoạt động bình thường! Lần chạy gần nhất: ' + data.minutes_ago + ' phút trước (' + data.last_heartbeat_at + ').';
                            return;
                        }

                        var messages = {
                            never_ran: 'Cron job chưa từng chạy lần nào — kiểm tra lại cấu hình Cron Job trên server (đường dẫn PHP, đường dẫn artisan).',
                            stale: 'Cron job có vẻ KHÔNG chạy đều — lần cuối ghi nhận cách đây ' + data.minutes_ago + ' phút (' + data.last_heartbeat_at + '). Kiểm tra lại Cron Job trên server.',
                        };

                        box.className = 'text-sm rounded-lg px-3 py-2 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
                        box.textContent = messages[data.reason] || 'Không thể xác định trạng thái cron job.';
                    })
                    .catch(function () {
                        box.className = 'text-sm rounded-lg px-3 py-2 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
                        box.textContent = 'Không thể kết nối tới server. Vui lòng thử lại.';
                    })
                    .finally(function () {
                        box.classList.remove('hidden');
                        btn.disabled = false;
                        btn.classList.remove('opacity-60', 'cursor-not-allowed');
                    });
            }
        </script>
    @endpush
@endsection
