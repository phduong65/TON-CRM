<div id="editPenaltyModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('editPenaltyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa phiếu phạt
            </h3>
            <button onclick="closeModal('editPenaltyModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <form id="editPenaltyForm" method="POST" class="px-6 py-5 space-y-4">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editPenaltyModal">

            {{-- Violation --}}
            <div>
                <label class="form-label">Vi phạm <span class="text-red-500">*</span></label>
                <select name="violation_id" id="edit_violation_id" class="form-input"
                        onchange="epOnViolationChange(this.value)" required>
                    <option value="">-- Chọn vi phạm --</option>
                    @foreach($violations as $v)
                    <option value="{{ $v->id }}">
                        {{ $v->name }}
                        @if($v->regulation)
                            ({{ $v->regulation->default_points }}đ
                            @if($v->regulation->type !== 'points')
                                / {{ number_format($v->regulation->default_money, 0, ',', '.') }}₫
                            @endif)
                        @endif
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Primary employee --}}
            <div>
                <label class="form-label">Nhân viên vi phạm <span class="text-red-500">*</span></label>
                <select name="employee_id" id="edit_employee_id" class="form-input" required>
                    <option value="">-- Chọn nhân viên --</option>
                    @foreach($employees as $e)
                    <option value="{{ $e->id }}">
                        {{ $e->code }} — {{ $e->name }}
                        @if($e->branch) ({{ $e->branch->name }}) @endif
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Points & Money --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Điểm trừ <span class="text-red-500">*</span></label>
                    <input type="number" name="points_deducted" id="edit_points_deducted"
                           class="form-input" min="0" max="100" required>
                </div>
                <div>
                    <label class="form-label">Tiền phạt (₫)</label>
                    <input type="number" name="money_deducted" id="edit_money_deducted"
                           class="form-input" min="0" step="1000">
                </div>
            </div>

            {{-- Description --}}
            <div>
                <label class="form-label">Mô tả / Ghi chú</label>
                <textarea name="description" id="edit_description" rows="2" class="form-input"
                          placeholder="Chi tiết về vi phạm..."></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editPenaltyModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary">
                    <i class="bi bi-floppy"></i> Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var EP_DEFAULTS = @json($violationDefaults);

    window.epOnViolationChange = function (violId) {
        var d = EP_DEFAULTS[violId];
        if (!d) return;
        document.getElementById('edit_points_deducted').value = d.points;
        document.getElementById('edit_money_deducted').value  = d.money;
    };

    window.openEditPenaltyModal = function (id, violationId, employeeId, points, money, description) {
        document.getElementById('editPenaltyForm').action        = '/penalties/' + id;
        document.getElementById('edit_violation_id').value       = violationId;
        document.getElementById('edit_employee_id').value        = employeeId;
        document.getElementById('edit_points_deducted').value    = points;
        document.getElementById('edit_money_deducted').value     = money;
        document.getElementById('edit_description').value        = description || '';
        openModal('editPenaltyModal');
    };
}());
</script>
@endpush
