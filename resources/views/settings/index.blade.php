@extends('layouts.admin')

@section('title', 'Cài đặt')
@section('page-title', 'Cài đặt')
@section('breadcrumb', 'Hệ thống / Cài đặt')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Settings Form -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white">Cấu hình hệ thống</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="settings[redzone_threshold]" class="form-label">Ngưỡng Redzone (điểm)</label>
                            <input type="number" name="settings[redzone_threshold]" id="settings[redzone_threshold]"
                                class="form-input" value="{{ old('settings.redzone_threshold', $settings->get('redzone_threshold')->value ?? 50) }}"
                                min="0" placeholder="50">
                            <p class="text-xs text-slate-400 mt-1">Nhân viên có tổng điểm dưới ngưỡng này sẽ xuất hiện trong Redzone.</p>
                        </div>

                        <div>
                            <label for="settings[default_score_per_month]" class="form-label">Điểm mặc định hàng tháng</label>
                            <input type="number" name="settings[default_score_per_month]" id="settings[default_score_per_month]"
                                class="form-input" value="{{ old('settings.default_score_per_month', $settings->get('default_score_per_month')->value ?? 100) }}"
                                min="0" placeholder="100">
                            <p class="text-xs text-slate-400 mt-1">Số điểm mỗi nhân viên được cấp đầu tháng.</p>
                        </div>

                        <div>
                            <label for="settings[company_name]" class="form-label">Tên công ty</label>
                            <input type="text" name="settings[company_name]" id="settings[company_name]"
                                class="form-input" value="{{ old('settings.company_name', $settings->get('company_name')->value ?? '') }}"
                                placeholder="Công ty TNHH...">
                        </div>

                        <div>
                            <label for="settings[rows_per_page]" class="form-label">Số dòng mỗi trang</label>
                            <select name="settings[rows_per_page]" id="settings[rows_per_page]" class="form-select">
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
                                <span>Lưu cài đặt</span>
                            </button>
                        </div>
                    </form>
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
