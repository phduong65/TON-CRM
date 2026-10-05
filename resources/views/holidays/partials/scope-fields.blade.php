@php $prefix = $prefix ?? ''; $defaultOffice = $defaultOffice ?? false; @endphp
{{-- Phạm vi áp dụng ngày lễ: toàn công ty hoặc theo bộ phận --}}
<div class="space-y-3 pt-1">
    <label class="form-label flex items-center justify-between font-medium text-slate-700 dark:text-slate-300">
        <span class="flex items-center gap-1.5">
            <i class="bi bi-people text-pcrm-600 dark:text-pcrm-400"></i> Phạm vi áp dụng <span class="text-red-500">*</span>
        </span>
        <span class="text-xs text-slate-400 font-normal">Chọn đối tượng được hưởng lịch nghỉ</span>
    </label>

    <!-- Scope Selector: 2 Segmented Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <!-- Toàn công ty -->
        <label class="holiday-scope-radio relative cursor-pointer block">
            <input type="radio" name="applies_to_all" value="1" id="{{ $prefix }}HolidayScopeAll"
                   onchange="toggleHolidayScope('{{ $prefix }}')" class="peer sr-only">
            <div class="scope-card p-3.5 rounded-xl border border-slate-200/90 dark:border-slate-700/80 bg-white dark:bg-slate-800 transition-all flex items-start justify-between gap-3 shadow-xs hover:border-slate-300 dark:hover:border-slate-600">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="scope-icon w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 flex items-center justify-center text-base shrink-0 transition-colors">
                        <i class="bi bi-buildings"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">Toàn công ty</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">Áp dụng mọi chi nhánh & bộ phận</div>
                    </div>
                </div>
                <div class="scope-radio-dot w-4 h-4 rounded-full border border-slate-300 dark:border-slate-600 flex items-center justify-center shrink-0 mt-0.5 transition-colors"></div>
            </div>
        </label>

        <!-- Theo bộ phận -->
        <label class="holiday-scope-radio relative cursor-pointer block">
            <input type="radio" name="applies_to_all" value="0" id="{{ $prefix }}HolidayScopeTeams"
                   onchange="toggleHolidayScope('{{ $prefix }}')" {{ $defaultOffice ? 'checked' : '' }} class="peer sr-only">
            <div class="scope-card p-3.5 rounded-xl border border-slate-200/90 dark:border-slate-700/80 bg-white dark:bg-slate-800 transition-all flex items-start justify-between gap-3 shadow-xs hover:border-slate-300 dark:hover:border-slate-600">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="scope-icon w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 flex items-center justify-center text-base shrink-0 transition-colors">
                        <i class="bi bi-diagram-3"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">Theo bộ phận</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">Chỉ định theo từng phòng ban</div>
                    </div>
                </div>
                <div class="scope-radio-dot w-4 h-4 rounded-full border border-slate-300 dark:border-slate-600 flex items-center justify-center shrink-0 mt-0.5 transition-colors"></div>
            </div>
        </label>
    </div>

    <!-- Teams Selection Container -->
    <div id="{{ $prefix }}HolidayTeamsWrap" class="space-y-2.5 mt-3 {{ $defaultOffice ? '' : 'hidden' }}">
        <!-- Quick Action Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-2 px-1">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-pcrm-50 text-pcrm-700 dark:bg-pcrm-900/40 dark:text-pcrm-300 border border-pcrm-200/80 dark:border-pcrm-800">
                    <i class="bi bi-check2-circle text-pcrm-600 dark:text-pcrm-400"></i> Đã chọn: <span id="{{ $prefix }}SelectedCount" class="font-bold">0</span> bộ phận
                </span>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" onclick="selectHolidayPreset('{{ $prefix }}', 'office')"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg border border-indigo-200/80 dark:border-indigo-800/80 bg-indigo-50/80 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 transition-colors cursor-pointer"
                        title="Chỉ chọn các bộ phận thuộc khối Văn phòng">
                    <i class="bi bi-briefcase-fill text-indigo-500 text-[11px]"></i> Khối Văn phòng
                </button>
                <button type="button" onclick="selectHolidayPreset('{{ $prefix }}', 'all')"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white hover:bg-slate-50 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700/60 dark:text-slate-300 transition-colors cursor-pointer">
                    <i class="bi bi-check-all text-xs"></i> Chọn tất cả
                </button>
                <button type="button" onclick="selectHolidayPreset('{{ $prefix }}', 'none')"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white hover:bg-slate-50 text-slate-500 dark:bg-slate-800 dark:hover:bg-slate-700/60 dark:text-slate-400 transition-colors cursor-pointer">
                    <i class="bi bi-x text-xs"></i> Bỏ chọn
                </button>
            </div>
        </div>

        <!-- Branches & Teams Full Display (No inner overflow) -->
        <div class="space-y-2.5">
            @forelse($branches as $branch)
                <div class="rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800/90 shadow-2xs overflow-hidden" data-branch-card="{{ $branch->id }}">
                    <!-- Branch Header -->
                    <div class="px-3.5 py-2.5 bg-slate-50/90 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-6 h-6 rounded-md bg-white dark:bg-slate-700 border border-slate-200/80 dark:border-slate-600 flex items-center justify-center text-slate-500 dark:text-slate-400 text-xs shrink-0">
                                <i class="bi bi-building"></i>
                            </span>
                            <span class="font-semibold text-xs sm:text-sm text-slate-800 dark:text-slate-200 truncate">{{ $branch->name }}</span>
                            <span id="{{ $prefix }}BranchBadge_{{ $branch->id }}" class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">0/{{ $branch->teams->count() }}</span>
                        </div>
                        
                        <!-- Master checkbox for this branch -->
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-slate-600 dark:text-slate-300 hover:text-pcrm-600 dark:hover:text-pcrm-400 select-none">
                            <input type="checkbox"
                                   id="{{ $prefix }}BranchMaster_{{ $branch->id }}"
                                   class="branch-master-cb rounded border-slate-300 text-pcrm-600 focus:ring-pcrm-500 dark:border-slate-600 dark:bg-slate-700 cursor-pointer"
                                   data-branch="{{ $branch->id }}"
                                   data-prefix="{{ $prefix }}"
                                   onchange="toggleHolidayBranch(this, '{{ $prefix }}')">
                            <span>Chọn cả chi nhánh</span>
                        </label>
                    </div>

                    <!-- Teams Grid -->
                    <div class="p-2.5 sm:p-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                        @forelse($branch->teams as $team)
                            @php
                                $isOffice = (bool) $team->is_office;
                                $isChecked = ($defaultOffice && in_array($team->id, $officeTeamIds));
                            @endphp
                            <label class="team-chip relative flex items-center gap-2.5 px-3 py-2 rounded-lg border text-xs cursor-pointer select-none transition-all duration-150 {{ $isChecked ? 'border-pcrm-500 bg-pcrm-50/80 text-pcrm-900 dark:bg-pcrm-900/30 dark:border-pcrm-500 dark:text-pcrm-200' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600' }}">
                                <input type="checkbox"
                                       name="team_ids[]"
                                       value="{{ $team->id }}"
                                       class="{{ $prefix ? $prefix . '-holiday-team' : 'create-holiday-team' }} {{ $prefix }}-holiday-team holiday-team-cb sr-only"
                                       data-branch="{{ $branch->id }}"
                                       data-prefix="{{ $prefix }}"
                                       data-office="{{ $isOffice ? '1' : '0' }}"
                                       {{ $isChecked ? 'checked' : '' }}
                                       onchange="onHolidayTeamChange(this, '{{ $prefix }}')">
                                
                                <span class="team-checkbox-indicator w-4 h-4 rounded border flex items-center justify-center shrink-0 transition-colors {{ $isChecked ? 'border-pcrm-600 bg-pcrm-600 text-white' : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700' }}">
                                    <i class="bi bi-check text-xs font-bold leading-none {{ $isChecked ? '' : 'hidden' }}"></i>
                                </span>

                                <span class="truncate font-medium flex-1 text-slate-800 dark:text-slate-200">{{ $team->name }}</span>

                                @if($isOffice)
                                    <span class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/80 flex items-center gap-0.5" title="Khối Văn Phòng">
                                        <i class="bi bi-briefcase-fill text-[9px]"></i> VP
                                    </span>
                                @endif
                            </label>
                        @empty
                            <div class="col-span-full py-2 text-center text-xs italic text-slate-400">Chưa có bộ phận nào thuộc chi nhánh này</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-sm text-slate-400 italic">Chưa có chi nhánh/bộ phận nào trong hệ thống.</div>
            @endforelse
        </div>

        @error('team_ids') <p class="form-error mt-1">{{ $message }}</p> @enderror

        <!-- Helper Banner -->
        <div class="flex items-start gap-2.5 p-3 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 text-xs text-blue-700 dark:text-blue-300">
            <i class="bi bi-info-circle-fill text-blue-500 shrink-0 text-sm mt-0.5"></i>
            <div class="leading-relaxed">
                <strong>Lưu ý:</strong> Các bộ phận được chọn sẽ nghỉ lễ và được tính công nghỉ lễ tự động (nếu bật có lương). Các bộ phận không chọn vẫn làm việc và chấm công theo ca bình thường.
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
    <style>
        /* Holiday Scope Radio Cards */
        .holiday-scope-radio:has(input:checked) .scope-card {
            border-color: var(--theme-accent, #2f55e7);
            background-color: color-mix(in srgb, var(--theme-accent, #2f55e7) 8%, #ffffff);
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--theme-accent, #2f55e7) 20%, transparent);
        }
        .dark .holiday-scope-radio:has(input:checked) .scope-card {
            border-color: var(--theme-accent, #5c81f6);
            background-color: color-mix(in srgb, var(--theme-accent, #5c81f6) 15%, #1e293b);
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--theme-accent, #5c81f6) 25%, transparent);
        }
        .holiday-scope-radio:has(input:checked) .scope-icon {
            background-color: color-mix(in srgb, var(--theme-accent, #2f55e7) 15%, transparent);
            color: var(--theme-accent, #2f55e7);
        }
        .dark .holiday-scope-radio:has(input:checked) .scope-icon {
            background-color: color-mix(in srgb, var(--theme-accent, #5c81f6) 20%, transparent);
            color: #93c5fd;
        }
        .holiday-scope-radio:has(input:checked) .scope-radio-dot {
            border-color: var(--theme-accent, #2f55e7);
            background-color: var(--theme-accent, #2f55e7);
        }
        .dark .holiday-scope-radio:has(input:checked) .scope-radio-dot {
            border-color: var(--theme-accent, #5c81f6);
            background-color: var(--theme-accent, #5c81f6);
        }
        .holiday-scope-radio:has(input:checked) .scope-radio-dot::after {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 9999px;
            background-color: #ffffff;
            display: block;
        }

        /* Team chip selectable card */
        .team-chip:has(input:checked) {
            border-color: var(--theme-accent, #2f55e7) !important;
            background-color: color-mix(in srgb, var(--theme-accent, #2f55e7) 8%, #ffffff) !important;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }
        .dark .team-chip:has(input:checked) {
            border-color: var(--theme-accent, #5c81f6) !important;
            background-color: color-mix(in srgb, var(--theme-accent, #5c81f6) 18%, #1e293b) !important;
        }
        .team-chip:has(input:checked) .team-checkbox-indicator {
            border-color: var(--theme-accent, #2f55e7) !important;
            background-color: var(--theme-accent, #2f55e7) !important;
            color: #ffffff !important;
        }
        .dark .team-chip:has(input:checked) .team-checkbox-indicator {
            border-color: var(--theme-accent, #5c81f6) !important;
            background-color: var(--theme-accent, #5c81f6) !important;
        }
        .team-chip:has(input:checked) .team-checkbox-indicator i {
            display: block !important;
        }

        /* Custom Modern Toggle Switch */
        .holiday-toggle-input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .holiday-toggle-track {
            position: relative;
            display: inline-block;
            flex: 0 0 44px;
            width: 44px;
            height: 24px;
            border-radius: 9999px;
            background-color: #cbd5e1;
            transition: background-color 180ms ease;
            cursor: pointer;
        }

        .holiday-toggle-track::before {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
            content: '';
            transition: transform 180ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        .holiday-toggle-input:checked + .holiday-toggle-track {
            background-color: var(--theme-accent, #2f55e7);
        }

        .holiday-toggle-input:checked + .holiday-toggle-track::before {
            transform: translateX(20px);
        }

        .holiday-toggle-input:focus-visible + .holiday-toggle-track {
            outline: 2px solid var(--theme-accent, #2f55e7);
            outline-offset: 2px;
        }

        .dark .holiday-toggle-track {
            background-color: #475569;
        }

        .dark .holiday-toggle-input:checked + .holiday-toggle-track {
            background-color: var(--theme-accent, #5c81f6);
        }
    </style>
    @endpush
@endonce
