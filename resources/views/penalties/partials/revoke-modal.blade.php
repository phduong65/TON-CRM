<div id="revokePenaltyModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('revokePenaltyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-arrow-counterclockwise text-orange-500"></i> Thu hồi phiếu phạt
            </h3>
            <button onclick="closeModal('revokePenaltyModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <div class="px-4 sm:px-6 py-3 bg-orange-50 dark:bg-orange-900/20 border-b border-orange-100 dark:border-orange-900/30">
            <p class="text-sm text-orange-700 dark:text-orange-300">
                <i class="bi bi-exclamation-triangle mr-1"></i>
                Thu hồi phiếu phạt <strong id="revokePenaltyCode"></strong> sẽ hoàn lại toàn bộ điểm đã trừ.
            </p>
        </div>
        <form id="revokePenaltyForm" action="" method="POST" class="px-4 sm:px-6 py-4 space-y-4">
            @csrf
            <div>
                <label class="form-label">Lý do thu hồi <span class="text-red-500">*</span></label>
                <textarea name="revoked_reason" class="form-input" rows="3"
                          placeholder="Nhập lý do thu hồi..." required maxlength="500"></textarea>
            </div>
            <div class="flex gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('revokePenaltyModal')" class="btn-secondary flex-1">Hủy</button>
                <button type="submit"
                        class="flex-1 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center justify-center gap-1.5 transition-colors">
                    <i class="bi bi-arrow-counterclockwise"></i> Xác nhận thu hồi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
window.openRevokePenaltyModal = function(id, code) {
    document.getElementById('revokePenaltyCode').textContent = code;
    document.getElementById('revokePenaltyForm').action = '/penalties/' + id + '/revoke';
    document.getElementById('revokePenaltyForm').querySelector('textarea').value = '';
    openModal('revokePenaltyModal');
};
</script>
