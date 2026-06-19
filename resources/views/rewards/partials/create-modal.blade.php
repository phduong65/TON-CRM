<div id="createRewardModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createRewardModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[95vh] flex flex-col">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-gift text-emerald-600"></i> Tạo phiếu thưởng
            </h3>
            <button onclick="closeModal('createRewardModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('rewards.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4 overflow-y-auto">
            @csrf
            <input type="hidden" name="_modal" value="createRewardModal">

            {{-- Target type --}}
            <div>
                <label class="form-label">Đối tượng thưởng <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach([
                        'individual' => ['bi-person', 'Cá nhân'],
                        'branch'     => ['bi-building', 'Chi nhánh'],
                        'team'       => ['bi-people', 'Đội nhóm'],
                        'all'        => ['bi-globe', 'Tất cả'],
                    ] as $val => [$icon, $label])
                    <label class="flex items-center gap-2 rounded-lg border cursor-pointer px-3 py-2 text-sm transition-colors
                                  has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-700
                                  dark:has-[:checked]:border-emerald-600 dark:has-[:checked]:bg-emerald-900/20 dark:has-[:checked]:text-emerald-400
                                  border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-600">
                        <input type="radio" name="target_type" value="{{ $val }}"
                               {{ old('target_type', 'individual') === $val ? 'checked' : '' }}
                               onchange="rwUpdateTargetUI()" class="sr-only">
                        <i class="bi {{ $icon }}"></i>
                        <span>{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
                @error('target_type') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            {{-- Individual employee --}}
            <div id="rw_target_individual" class="{{ old('target_type', 'individual') !== 'individual' ? 'hidden' : '' }}">
                <label class="form-label">Nhân viên được thưởng <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                    <select id="rw_filter_branch" class="form-input text-sm py-1.5" onchange="rwOnBranchFilter()">
                        <option value="">Tất cả chi nhánh</option>
                        @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <select id="rw_filter_team" class="form-input text-sm py-1.5" onchange="rwFilterEmployees()">
                        <option value="">Tất cả phòng ban</option>
                        @foreach($teams as $t)
                        <option value="{{ $t->id }}" data-branch="{{ $t->branch_id ?? '' }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative mb-1.5">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" id="rw_emp_search" class="form-input pl-8 text-sm"
                           placeholder="Tìm mã / tên nhân viên..." oninput="rwFilterEmployees()">
                </div>
                <select id="rw_main_employee" name="employee_id" class="form-input">
                    <option value="">-- Chọn nhân viên --</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}"
                                data-branch="{{ $emp->branch_id ?? '' }}"
                                data-team="{{ $emp->team_id ?? '' }}"
                                {{ old('employee_id') == $emp->id ? 'selected' : '' }}>
                            {{ $emp->code }} — {{ $emp->name }}
                            @if($emp->branch) · {{ $emp->branch->name }} @endif
                        </option>
                    @endforeach
                </select>
                @error('employee_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            {{-- Branch target --}}
            <div id="rw_target_branch" class="{{ old('target_type') !== 'branch' ? 'hidden' : '' }}">
                <label class="form-label">Chi nhánh <span class="text-red-500">*</span></label>
                <select name="target_id_branch" class="form-input" id="rw_branch_select">
                    <option value="">-- Chọn chi nhánh --</option>
                    @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ old('target_id') == $b->id && old('target_type') === 'branch' ? 'selected' : '' }}>
                        {{ $b->name }}
                    </option>
                    @endforeach
                </select>
                @error('target_id') <p class="form-error">{{ $message }}</p> @enderror
                <p class="text-xs text-slate-400 mt-1">Tất cả nhân viên đang hoạt động trong chi nhánh này sẽ nhận thưởng.</p>
            </div>

            {{-- Team target --}}
            <div id="rw_target_team" class="{{ old('target_type') !== 'team' ? 'hidden' : '' }}">
                <label class="form-label">Đội nhóm <span class="text-red-500">*</span></label>
                <select name="target_id_team" class="form-input" id="rw_team_select">
                    <option value="">-- Chọn đội nhóm --</option>
                    @foreach($teams as $t)
                    <option value="{{ $t->id }}" {{ old('target_id') == $t->id && old('target_type') === 'team' ? 'selected' : '' }}>
                        {{ $t->name }} @if($t->branch) · {{ $t->branch->name }} @endif
                    </option>
                    @endforeach
                </select>
                @error('target_id') <p class="form-error">{{ $message }}</p> @enderror
                <p class="text-xs text-slate-400 mt-1">Tất cả nhân viên đang hoạt động trong đội nhóm này sẽ nhận thưởng.</p>
            </div>

            {{-- All --}}
            <div id="rw_target_all" class="{{ old('target_type') !== 'all' ? 'hidden' : '' }}">
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400">
                    <i class="bi bi-info-circle mr-1"></i>
                    Tất cả nhân viên đang hoạt động sẽ nhận thưởng điểm này.
                </div>
            </div>

            {{-- Hidden target_id combined field --}}
            <input type="hidden" id="rw_target_id_hidden" name="target_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Loại thưởng <span class="text-red-500">*</span></label>
                    <select id="createRewardTypeId" name="reward_type_id" class="form-input" required>
                        <option value="">-- Chọn loại thưởng --</option>
                        @foreach($rewardTypes as $rt)
                            <option value="{{ $rt->id }}" data-points="{{ $rt->default_points }}"
                                    {{ old('reward_type_id') == $rt->id ? 'selected' : '' }}>
                                {{ $rt->name }} ({{ $rt->default_points }}đ)
                            </option>
                        @endforeach
                    </select>
                    @error('reward_type_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Điểm thưởng <span class="text-red-500">*</span></label>
                    <input type="number" id="createRewardPoints" name="total_points_awarded"
                           class="form-input" value="{{ old('total_points_awarded', 10) }}" min="1" max="9999" required>
                    @error('total_points_awarded') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="form-label">Lý do / Mô tả</label>
                <textarea name="description" class="form-input" rows="2"
                          placeholder="Mô tả lý do khen thưởng...">{{ old('description') }}</textarea>
                @error('description') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            {{-- Additional members — only for individual --}}
            <div id="rw_members_section" class="{{ old('target_type', 'individual') !== 'individual' ? 'hidden' : '' }}">
                <div class="flex items-center justify-between mb-2">
                    <label class="form-label mb-0">Thành viên thưởng thêm</label>
                    <button type="button" onclick="addRewardMemberRow()" class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                        <i class="bi bi-plus-circle"></i> Thêm nhân viên
                    </button>
                </div>
                <div id="rewardMembersContainer" class="space-y-2">
                    @if(old('members'))
                        @foreach(old('members') as $i => $m)
                        <div class="reward-member-row rounded-lg border border-slate-200 dark:border-slate-700 p-2 space-y-1.5">
                            <div class="relative">
                                <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                                <input type="text" class="form-input pl-7 text-sm py-1.5" placeholder="Tìm nhân viên..."
                                       oninput="rwFilterMemberSelect(this)">
                            </div>
                            <div class="flex items-center gap-2">
                            <select name="members[{{ $i }}][employee_id]" class="form-input flex-1 text-sm">
                                <option value="">-- Chọn nhân viên --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}"
                                            data-branch="{{ $emp->branch_id ?? '' }}"
                                            data-team="{{ $emp->team_id ?? '' }}"
                                            {{ $m['employee_id'] == $emp->id ? 'selected' : '' }}>
                                        {{ $emp->code }} — {{ $emp->name }}
                                        @if($emp->branch) · {{ $emp->branch->name }} @endif
                                    </option>
                                @endforeach
                            </select>
                            <input type="number" name="members[{{ $i }}][points_awarded]"
                                   class="form-input w-24 text-sm" value="{{ $m['points_awarded'] ?? 10 }}" min="0" placeholder="Điểm">
                            <input type="text" name="members[{{ $i }}][note]"
                                   class="form-input flex-1 text-sm" value="{{ $m['note'] ?? '' }}" placeholder="Ghi chú...">
                            <button type="button" onclick="this.closest('.reward-member-row').remove()"
                                    class="text-red-400 hover:text-red-600 shrink-0">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            </div>
                        </div>
                        @endforeach
                    @endif
                </div>
                <p class="text-xs text-slate-400 mt-1">Dùng khi muốn thưởng thêm cho nhiều nhân viên trong cùng phiếu</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('createRewardModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Tạo phiếu thưởng</button>
            </div>
        </form>
    </div>
</div>
