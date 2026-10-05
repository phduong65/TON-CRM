<div id="rollbackModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('rollbackModal')">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 max-w-md w-full overflow-hidden"
         onclick="event.stopPropagation()">
        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-heading text-lg font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <i class="bi bi-arrow-counterclockwise text-lg"></i>
                </span>
                <span>Rollback Khẩn Cấp</span>
            </h3>
            <button type="button" onclick="closeModal('rollbackModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <form id="rollbackForm" method="POST" action="" class="p-5 sm:p-6 space-y-4">
            @csrf

            <div class="p-3.5 bg-rose-50 dark:bg-rose-950/40 rounded-xl border border-rose-200 dark:border-rose-900/50 text-xs text-rose-800 dark:text-rose-300 space-y-1.5 leading-relaxed">
                <p class="font-bold flex items-center gap-1.5">
                    <i class="bi bi-exclamation-triangle-fill"></i> Cảnh báo hành động khẩn cấp:
                </p>
                <p>Hành động này sẽ <strong>ngay lập tức tạm dừng</strong> chủ đề <span id="rollbackThemeName" class="font-bold underline"></span> và đưa giao diện toàn hệ thống trở về giao diện mặc định (Default TON-HR).</p>
            </div>

            <div>
                <label class="form-label">
                    Lý do rollback khẩn cấp <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" required class="form-input text-sm"
                          placeholder="VD: Phát hiện lỗi hiển thị trên thiết bị di động; chuyển về giao diện mặc định theo yêu cầu BGĐ..."></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-700/80">
                <button type="button" onclick="closeModal('rollbackModal')" class="btn-secondary">Hủy bỏ</button>
                <button type="submit" class="btn-danger"><i class="bi bi-arrow-counterclockwise"></i> Xác nhận Rollback ngay</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRollbackModal(id, name) {
        document.getElementById('rollbackThemeName').textContent = name;
        document.getElementById('rollbackForm').action = '/themes/' + id + '/rollback';
        openModal('rollbackModal');
    }

    function closeRollbackModal() {
        closeModal('rollbackModal');
    }
</script>
