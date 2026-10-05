<div id="deleteRequirementModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden transform transition-all animate-fadeIn">
        <div class="p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 mx-auto flex items-center justify-center mb-4">
                <i class="bi bi-exclamation-triangle text-xl"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800 dark:text-white mb-2">Xác nhận xoá quy tắc định biên</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
                Bạn có chắc chắn muốn xoá quy tắc định biên <strong id="deleteRequirementName" class="text-slate-700 dark:text-slate-200"></strong>?
                Lịch sử tính định biên của các tuần cũ có thể bị thay đổi.
            </p>

            <form id="deleteRequirementForm" method="POST" action="" class="flex justify-center gap-3">
                @csrf
                @method('DELETE')
                <button type="button" onclick="closeModal('deleteRequirementModal')"
                    class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    Huỷ
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-300 transition-colors shadow-sm">
                    Xác nhận xoá
                </button>
            </form>
        </div>
    </div>
</div>
