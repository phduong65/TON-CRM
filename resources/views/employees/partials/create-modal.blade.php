<div id="createEmployeeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createEmployeeModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[95vh] flex flex-col">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-person-plus text-pcrm-600"></i> Thêm nhân viên
            </h3>
            <button onclick="closeModal('createEmployeeModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('employees.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4 overflow-y-auto">
            @csrf
            <input type="hidden" name="_modal" value="createEmployeeModal">

            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#f5f7fa] dark:bg-[#2d3748] border border-sky-200 dark:border-sky-700 text-sm">
                <i class="bi bi-person-badge text-sky-500 mt-0.5 shrink-0"></i>
                <p class="text-sky-700 dark:text-sky-300">Hệ thống sẽ tự động tạo tài khoản đăng nhập cho nhân viên. Email sẽ được dùng làm tên đăng nhập.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Mã nhân viên <span class="text-red-500">*</span></label>
                    <input type="text" name="code" class="form-input" value="{{ old('code') }}" placeholder="VD: NV011" required>
                    @error('code') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Chức vụ</label>
                    <input type="text" name="position" class="form-input" value="{{ old('position') }}" placeholder="VD: Pha chế trưởng">
                    @error('position') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="form-label">Họ và tên <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="form-input" value="{{ old('name') }}" placeholder="VD: Nguyễn Văn A" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" class="form-input" value="{{ old('email') }}" placeholder="email@congty.vn" required>
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" class="form-input" value="{{ old('phone') }}" placeholder="VD: 0912345678">
                    @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Chi nhánh <span class="text-red-500">*</span></label>
                    <select name="branch_id" class="form-input" required>
                        <option value="">-- Chọn chi nhánh --</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('branch_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Đội nhóm <span class="text-red-500">*</span></label>
                    <select name="team_id" class="form-input" required>
                        <option value="">-- Chọn đội nhóm --</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>
                                {{ $team->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('team_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Ngày gia nhập</label>
                    <input type="date" name="joined_at" class="form-input" value="{{ old('joined_at') }}">
                    @error('joined_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Loại hình nhân viên <span class="text-red-500">*</span></label>
                    <select name="employment_type" class="form-input" required>
                        <option value="full_time" @selected(old('employment_type', 'full_time') === 'full_time')>Chính thức</option>
                        <option value="part_time" @selected(old('employment_type') === 'part_time')>Bán thời gian (Part-time)</option>
                    </select>
                    @error('employment_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                           class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                    <span class="text-sm text-slate-700 dark:text-slate-300">Đang làm việc</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_office" value="1" {{ old('is_office') ? 'checked' : '' }}
                           class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                    <span class="text-sm text-slate-700 dark:text-slate-300">Nhân viên văn phòng (đủ điều kiện phép năm nếu chính thức)</span>
                </label>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('createEmployeeModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>
