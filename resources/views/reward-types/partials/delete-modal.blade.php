<div id="deleteRewardTypeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('deleteRewardTypeModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-exclamation-triangle text-red-500"></i> Xác nhận xóa
            </h3>
            <button onclick="closeModal('deleteRewardTypeModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <div class="px-4 sm:px-6 py-4 sm:py-5">
            <p class="text-slate-600 dark:text-slate-300 text-sm">
                Bạn có chắc muốn xóa loại thưởng
                <strong id="deleteRewardTypeName" class="text-slate-900 dark:text-white"></strong>?
            </p>
            <p class="text-xs text-red-500 mt-2">Không thể xóa nếu đã có phiếu thưởng liên kết.</p>
        </div>
        <form id="deleteRewardTypeForm" action="" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex items-center justify-end gap-3 px-4 sm:px-6 pb-4 sm:pb-5">
                <button type="button" onclick="closeModal('deleteRewardTypeModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Xóa</button>
            </div>
        </form>
    </div>
</div>
