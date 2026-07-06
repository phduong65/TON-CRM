<div id="deleteShiftModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('deleteShiftModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                <i class="bi bi-exclamation-triangle text-red-600 dark:text-red-400"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-900 dark:text-white">Xoá ca làm việc</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Hành động này không thể hoàn tác.</p>
            </div>
        </div>
        <p class="text-sm text-slate-700 dark:text-slate-300 mb-2">
            Xác nhận xoá ca <strong id="deleteShiftName"></strong>?
        </p>
        <p class="text-sm text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2 mb-5">
            <i class="bi bi-info-circle mr-1"></i>
            Toàn bộ lịch xếp ca (quá khứ &amp; tương lai) của <strong>tất cả nhân viên</strong> đang dùng ca này sẽ bị xoá,
            kể cả cấu hình xếp ca cố định (nếu có) liên quan đến ca này.
        </p>
        <div class="flex gap-3">
            <button onclick="closeModal('deleteShiftModal')" class="btn-secondary flex-1">Hủy</button>
            <form id="deleteShiftForm" method="POST" class="flex-1">
                @csrf @method('DELETE')
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors">
                    Xoá vĩnh viễn
                </button>
            </form>
        </div>
    </div>
</div>
