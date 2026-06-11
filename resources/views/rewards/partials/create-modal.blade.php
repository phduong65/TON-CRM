<div id="createRewardModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('createRewardModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-gift text-emerald-600"></i> Tạo phiếu thưởng
            </h3>
            <button onclick="closeModal('createRewardModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('rewards.store') }}" method="POST" class="px-6 py-5 space-y-4 overflow-y-auto">
            @csrf
            <input type="hidden" name="_modal" value="createRewardModal">

            <div class="grid grid-cols-2 gap-4">
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
                <label class="form-label">Nhân viên được thưởng <span class="text-red-500">*</span></label>
                <select name="employee_id" class="form-input" required>
                    <option value="">-- Chọn nhân viên --</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>
                            {{ $emp->name }} ({{ $emp->code }}) — {{ $emp->branch?->name }}
                        </option>
                    @endforeach
                </select>
                @error('employee_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Lý do / Mô tả</label>
                <textarea name="description" class="form-input" rows="2"
                          placeholder="Mô tả lý do khen thưởng...">{{ old('description') }}</textarea>
                @error('description') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            {{-- Additional members --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="form-label mb-0">Thành viên thưởng thêm</label>
                    <button type="button" onclick="addRewardMemberRow()" class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                        <i class="bi bi-plus-circle"></i> Thêm nhân viên
                    </button>
                </div>
                <div id="rewardMembersContainer" class="space-y-2">
                    @if(old('members'))
                        @foreach(old('members') as $i => $m)
                        <div class="flex items-center gap-2 reward-member-row">
                            <select name="members[{{ $i }}][employee_id]" class="form-input flex-1 text-sm">
                                <option value="">-- Chọn nhân viên --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}" {{ $m['employee_id'] == $emp->id ? 'selected' : '' }}>
                                        {{ $emp->name }} ({{ $emp->code }})
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
