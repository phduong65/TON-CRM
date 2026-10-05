<div id="createHolidayModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-xs z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createHolidayModal')">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200/80 dark:border-slate-700/80">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-5 sm:px-7 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/80 shrink-0">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/40 text-pcrm-600 dark:text-pcrm-400 border border-pcrm-200/60 dark:border-pcrm-800 flex items-center justify-center text-lg shrink-0 shadow-xs">
                    <i class="bi bi-calendar-plus"></i>
                </span>
                <div>
                    <h3 class="font-heading text-lg font-bold text-slate-900 dark:text-white leading-tight">Thêm ngày nghỉ lễ</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Cấu hình lịch nghỉ lễ, tiền thưởng và đối tượng áp dụng</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('createHolidayModal')" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <!-- Modal Form -->
        <form action="{{ route('holidays.store') }}" method="POST" class="flex flex-col min-h-0 flex-1 overflow-hidden">
            @csrf
            <input type="hidden" name="_modal" value="createHolidayModal">

            <!-- Scrollable Body (Single Natural Scroll Area) -->
            <div class="overflow-y-auto px-5 sm:px-7 py-5 space-y-5 flex-1 min-h-0">
                <!-- Section 1: Basic Information -->
                <div class="space-y-4">
                    <div>
                        <label class="form-label flex items-center gap-1.5 font-medium text-slate-700 dark:text-slate-300">
                            <i class="bi bi-flag text-pcrm-600 dark:text-pcrm-400"></i> Tên ngày lễ <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" class="form-input text-sm font-medium mt-1" value="{{ old('name') }}" placeholder="VD: Giỗ tổ Hùng Vương, Tết Dương Lịch 2026..." required>
                        @error('name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label flex items-center gap-1.5 font-medium text-slate-700 dark:text-slate-300">
                                <i class="bi bi-calendar-event text-pcrm-600 dark:text-pcrm-400"></i> Ngày áp dụng <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="date" class="form-input text-sm font-medium mt-1" value="{{ old('date') }}" required>
                            @error('date') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label flex items-center justify-between font-medium text-slate-700 dark:text-slate-300">
                                <span class="flex items-center gap-1.5">
                                    <i class="bi bi-gift text-pcrm-600 dark:text-pcrm-400"></i> Thưởng ngày lễ
                                </span>
                                <span class="text-[11px] text-slate-400 font-normal">Không bắt buộc</span>
                            </label>
                            <div class="relative mt-1">
                                <input type="number" name="bonus_amount" class="form-input text-sm font-medium pr-12" value="{{ old('bonus_amount') }}" min="0" step="1000" placeholder="VD: 500000">
                                <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-semibold text-slate-400 dark:text-slate-500">VNĐ</span>
                            </div>
                            @error('bonus_amount') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- Section 2: Settings & Status (Two Modern Cards) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <!-- Paid Holiday Switch -->
                    <label class="relative flex items-center justify-between p-3.5 rounded-xl border border-slate-200/90 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-900/40 hover:border-slate-300 dark:hover:border-slate-600 cursor-pointer transition-colors">
                        <div class="flex items-center gap-3 min-w-0 pr-2">
                            <span class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800/50 flex items-center justify-center text-base shrink-0">
                                <i class="bi bi-cash-coin"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">Nghỉ có lương</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">Tự động tính công lễ trong kỳ</div>
                            </div>
                        </div>
                        <div class="shrink-0 flex items-center">
                            <input type="checkbox" name="is_paid" value="1" role="switch" {{ old('is_paid', true) ? 'checked' : '' }} class="holiday-toggle-input">
                            <span class="holiday-toggle-track" aria-hidden="true"></span>
                        </div>
                    </label>

                    <!-- Active Status Switch -->
                    <label class="relative flex items-center justify-between p-3.5 rounded-xl border border-slate-200/90 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-900/40 hover:border-slate-300 dark:hover:border-slate-600 cursor-pointer transition-colors">
                        <div class="flex items-center gap-3 min-w-0 pr-2">
                            <span class="w-9 h-9 rounded-xl bg-pcrm-50 dark:bg-pcrm-950/40 text-pcrm-600 dark:text-pcrm-400 border border-pcrm-100 dark:border-pcrm-800/50 flex items-center justify-center text-base shrink-0">
                                <i class="bi bi-check2-circle"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">Đang áp dụng</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">Kích hoạt hiệu lực trên hệ thống</div>
                            </div>
                        </div>
                        <div class="shrink-0 flex items-center">
                            <input type="checkbox" name="is_active" value="1" role="switch" {{ old('is_active', true) ? 'checked' : '' }} class="holiday-toggle-input">
                            <span class="holiday-toggle-track" aria-hidden="true"></span>
                        </div>
                    </label>
                </div>

                <!-- Section 3: Scope of Application -->
                @include('holidays.partials.scope-fields', ['prefix' => '', 'defaultOffice' => true])
            </div>

            <!-- Fixed Modal Footer -->
            <div class="px-5 sm:px-7 py-3.5 bg-slate-50/95 dark:bg-slate-800/95 flex items-center justify-between border-t border-slate-200 dark:border-slate-700 shrink-0">
                <span class="text-xs text-slate-400 dark:text-slate-500 hidden sm:inline">Nhấn ESC để đóng</span>
                <div class="flex items-center gap-2.5 ml-auto">
                    <button type="button" onclick="closeModal('createHolidayModal')" class="btn-secondary text-sm px-4 py-2">Hủy</button>
                    <button type="submit" class="btn-primary text-sm px-5 py-2 font-medium flex items-center gap-2 shadow-sm shadow-pcrm-500/20">
                        <i class="bi bi-floppy"></i> Lưu ngày lễ
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
