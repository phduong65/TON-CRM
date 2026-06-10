@extends('layouts.admin')

@section('title', 'Hồ sơ của tôi')
@section('page-title', 'Hồ sơ của tôi')
@section('breadcrumb', 'Tài khoản')

@section('content')
@php
    $canEditEmail = auth()->user()->can('edit-employees') || auth()->user()->hasRole('admin');
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── Cột trái: thẻ hồ sơ ── --}}
    <div class="lg:col-span-1 space-y-4">
        <div class="card text-center">
            <div class="card-body">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center">
                    <span class="text-2xl font-bold text-pcrm-700 dark:text-pcrm-400">
                        {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                    </span>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $user->name }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    @if($user->hasRole('admin')) Quản trị viên
                    @elseif($user->hasRole('manager')) Quản lý
                    @elseif($user->hasRole('team-leader')) Trưởng nhóm
                    @else Nhân viên
                    @endif
                </p>

                @if($employee)
                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 text-left space-y-2.5">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Mã nhân viên</span>
                        <span class="font-mono font-medium text-slate-800 dark:text-white">{{ $employee->code ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Chức vụ</span>
                        <span class="font-medium">{{ $employee->position ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Chi nhánh</span>
                        <span class="font-medium">{{ $employee->branch->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Đội nhóm</span>
                        <span class="font-medium">{{ $employee->team->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Ngày vào làm</span>
                        <span class="font-medium">{{ $employee->joined_at?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                </div>
                @endif
            </div>
        </div>

        @if($employee)
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm text-slate-500">Tổng điểm</span>
                    <span class="text-xl font-bold text-pcrm-600 dark:text-pcrm-400">{{ number_format($employee->total_score) }}</span>
                </div>
                <a href="{{ route('employees.show', $employee) }}"
                   class="mt-3 flex items-center justify-center gap-2 w-full py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    <i class="bi bi-person-lines-fill text-sm"></i> Xem hồ sơ nhân viên
                </a>
            </div>
        </div>
        @endif
    </div>

    {{-- ── Cột phải: form chỉnh sửa ── --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Thông tin cá nhân --}}
        <div class="card">
            <div class="card-header">
                <h4 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="bi bi-person-gear text-pcrm-600"></i> Thông tin cá nhân
                </h4>
            </div>
            <div class="card-body">
                <form action="{{ route('profile.update') }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Họ và tên <span class="text-red-500">*</span></label>
                            <input type="text" name="name" class="form-input" value="{{ old('name', $user->name) }}" required>
                            @error('name') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">
                                Email
                                @if(!$canEditEmail)
                                <span class="ml-1 text-[10px] font-normal text-slate-400 normal-case">(chỉ admin/nhân sự được đổi)</span>
                                @else
                                <span class="text-red-500">*</span>
                                @endif
                            </label>
                            <input type="email" name="email"
                                   class="form-input {{ !$canEditEmail ? 'bg-slate-50 dark:bg-slate-700/50 cursor-not-allowed text-slate-400 dark:text-slate-500' : '' }}"
                                   value="{{ old('email', $user->email) }}"
                                   {{ !$canEditEmail ? 'disabled' : '' }}
                                   title="{{ !$canEditEmail ? 'Liên hệ quản trị viên hoặc nhân sự để thay đổi email' : '' }}">
                            @error('email') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if($employee)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="phone" class="form-input" value="{{ old('phone', $employee->phone) }}" placeholder="VD: 0912345678">
                            @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Chức vụ</label>
                            <input type="text" name="position" class="form-input" value="{{ old('position', $employee->position) }}" placeholder="VD: Pha chế">
                            @error('position') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    @endif

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn-primary">
                            <i class="bi bi-floppy"></i> Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Đổi mật khẩu --}}
        <div class="card" id="password-section">
            <div class="card-header">
                <h4 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="bi bi-shield-lock text-pcrm-600"></i> Đổi mật khẩu
                </h4>
            </div>
            <div class="card-body">
                <form action="{{ route('profile.password') }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="form-label">Mật khẩu hiện tại <span class="text-red-500">*</span></label>
                        <input type="password" name="current_password" class="form-input" autocomplete="current-password">
                        @if($errors->password->has('current_password'))
                            <p class="form-error">{{ $errors->password->first('current_password') }}</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Mật khẩu mới <span class="text-red-500">*</span></label>
                            <input type="password" name="password" class="form-input" autocomplete="new-password">
                            @if($errors->password->has('password'))
                                <p class="form-error">{{ $errors->password->first('password') }}</p>
                            @endif
                        </div>
                        <div>
                            <label class="form-label">Xác nhận mật khẩu mới <span class="text-red-500">*</span></label>
                            <input type="password" name="password_confirmation" class="form-input" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn-primary">
                            <i class="bi bi-key"></i> Đổi mật khẩu
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
@if(session('scroll_to_password') || $errors->password->any())
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('password-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
@endif
</script>
@endpush
