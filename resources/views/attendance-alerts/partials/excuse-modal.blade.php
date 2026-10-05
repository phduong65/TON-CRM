<div id="excuseAlertModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden transform transition-all animate-fadeIn">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                    <i class="bi bi-shield-check text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-base">Miễn cảnh báo chấm công</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500">Xác nhận nhân viên không cần bổ sung log</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('excuseAlertModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>

        <form id="excuseAlertForm" method="POST" action="" class="p-6 space-y-4">
            @csrf
            <div>
                <p class="text-xs text-slate-600 dark:text-slate-400 mb-2">
                    Nhân viên: <strong id="excuseEmployeeName" class="text-slate-900 dark:text-white"></strong><br>
                    Ca làm việc: <span id="excuseShiftInfo" class="text-slate-700 dark:text-slate-300"></span><br>
                    Loại cảnh báo: <span id="excuseAlertInfo" class="font-semibold text-amber-600 dark:text-amber-400"></span>
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Lý do miễn cảnh báo <span class="text-red-500">*</span>
                </label>
                <textarea name="resolution_note" required rows="3" placeholder="Ghi rõ lý do miễn (VD: Đi công tác ngoài nhà hàng được duyệt trước, thiết bị hỏng, ...)"
                    class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white p-3 focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3">
                <button type="button" onclick="closeModal('excuseAlertModal')"
                    class="px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    Huỷ
                </button>
                <button type="submit"
                    class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 shadow-sm transition-colors">
                    Xác nhận miễn
                </button>
            </div>
        </form>
    </div>
</div>
