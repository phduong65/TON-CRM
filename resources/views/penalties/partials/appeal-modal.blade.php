<div id="appealPenaltyModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('appealPenaltyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-chat-left-text text-pcrm-600 dark:text-pcrm-400"></i> Gửi khiếu nại
            </h3>
            <button onclick="closeModal('appealPenaltyModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <div class="px-4 sm:px-6 py-3 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-900/30">
            <p class="text-sm text-blue-700 dark:text-blue-300">
                Khiếu nại phiếu phạt <strong id="appealPenaltyCode"></strong>.
                Quản lý sẽ xem xét và phản hồi trong thời gian sớm nhất.
            </p>
        </div>
        <form id="appealPenaltyForm" action="" method="POST" class="px-4 sm:px-6 py-4 space-y-4">
            @csrf
            <div>
                <label class="form-label">Lý do khiếu nại <span class="text-red-500">*</span></label>
                <textarea name="reason" class="form-input" rows="4"
                          placeholder="Trình bày rõ lý do bạn không đồng ý với phiếu phạt này..."
                          required maxlength="1000"></textarea>
                <p class="text-xs text-slate-400 mt-1">Tối đa 1000 ký tự</p>
            </div>
            <div class="flex gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('appealPenaltyModal')" class="btn-secondary flex-1">Hủy</button>
                <button type="submit" class="btn-primary flex-1">
                    <i class="bi bi-send"></i> Gửi khiếu nại
                </button>
            </div>
        </form>
    </div>
</div>

<script>
window.openAppealPenaltyModal = function(id, code) {
    document.getElementById('appealPenaltyCode').textContent = code;
    document.getElementById('appealPenaltyForm').action = '/penalties/' + id + '/appeal';
    document.getElementById('appealPenaltyForm').querySelector('textarea').value = '';
    openModal('appealPenaltyModal');
};
</script>
