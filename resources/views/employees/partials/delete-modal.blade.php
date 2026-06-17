<div id="deleteEmployeeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('deleteEmployeeModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                <i class="bi bi-person-dash text-amber-600 dark:text-amber-400"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-900 dark:text-white">Hành động với nhân viên</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Nhân viên: <strong id="deleteEmployeeName" class="text-slate-700 dark:text-slate-300"></strong></p>
            </div>
        </div>

        {{-- Option 1: Resign --}}
        <div class="rounded-lg border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/10 p-4 mb-3">
            <div class="flex items-start gap-3 mb-3">
                <i class="bi bi-briefcase-x text-amber-600 dark:text-amber-400 text-lg mt-0.5 shrink-0"></i>
                <div>
                    <p class="font-medium text-sm text-slate-800 dark:text-slate-200">Đánh dấu nghỉ việc</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Giữ lại toàn bộ dữ liệu lịch sử. Nhân viên không còn xuất hiện trong bảng xếp hạng và các báo cáo.</p>
                </div>
            </div>
            <form id="resignEmployeeForm" method="POST">
                @csrf @method('DELETE')
                <input type="hidden" name="_delete_type" value="resign">
                <button type="submit"
                        class="w-full px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium transition-colors">
                    <i class="bi bi-briefcase-x mr-1"></i> Đánh dấu nghỉ việc
                </button>
            </form>
        </div>

        {{-- Option 2: Permanent delete --}}
        <div class="rounded-lg border border-red-200 dark:border-red-800/50 bg-red-50 dark:bg-red-900/10 p-4 mb-5">
            <div class="flex items-start gap-3 mb-3">
                <i class="bi bi-trash3 text-red-600 dark:text-red-400 text-lg mt-0.5 shrink-0"></i>
                <div>
                    <p class="font-medium text-sm text-slate-800 dark:text-slate-200">Xóa khỏi hệ thống</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Xóa nhân viên và tài khoản đăng nhập. Dữ liệu lịch sử phạt/thưởng vẫn được lưu nhưng <span class="font-semibold text-red-600 dark:text-red-400">KHÔNG thể hoàn tác</span>.</p>
                </div>
            </div>
            <form id="deleteEmployeeForm" method="POST">
                @csrf @method('DELETE')
                <input type="hidden" name="_delete_type" value="permanent">
                <button type="submit"
                        class="w-full px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors"
                        onclick="return confirm('Xác nhận xóa vĩnh viễn nhân viên này khỏi hệ thống?')">
                    <i class="bi bi-trash3 mr-1"></i> Xóa khỏi hệ thống
                </button>
            </form>
        </div>

        <button onclick="closeModal('deleteEmployeeModal')" class="btn-secondary w-full">Hủy</button>
    </div>
</div>
