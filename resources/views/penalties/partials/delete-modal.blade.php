<div id="deletePenaltyModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('deletePenaltyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                <i class="bi bi-exclamation-triangle text-red-600 dark:text-red-400"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-900 dark:text-white">Xóa phiếu phạt</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Hành động này không thể hoàn tác.</p>
            </div>
        </div>
        <p class="text-sm text-slate-700 dark:text-slate-300 mb-5">
            Xác nhận xóa phiếu phạt <strong id="deletePenaltyCode"></strong>?
        </p>
        <div class="flex gap-3">
            <button onclick="closeModal('deletePenaltyModal')" class="btn-secondary flex-1">Hủy</button>
            <form id="deletePenaltyForm" method="POST" class="flex-1">
                @csrf @method('DELETE')
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors">
                    Xóa
                </button>
            </form>
        </div>
    </div>
</div>

<script>
window.openDeletePenaltyModal = function (id, code) {
    document.getElementById('deletePenaltyCode').textContent = code;
    document.getElementById('deletePenaltyForm').action = '/penalties/' + id;
    openModal('deletePenaltyModal');
};
</script>
