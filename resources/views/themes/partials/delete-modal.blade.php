<div id="deleteThemeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('deleteThemeModal')">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 max-w-md w-full overflow-hidden"
         onclick="event.stopPropagation()">
        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-heading text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
                    <i class="bi bi-trash text-lg"></i>
                </span>
                <span>Xóa chủ đề sự kiện</span>
            </h3>
            <button type="button" onclick="closeModal('deleteThemeModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <form id="deleteThemeForm" method="POST" action="" class="p-5 sm:p-6 space-y-4">
            @csrf
            @method('DELETE')

            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                Bạn có chắc chắn muốn xóa chủ đề <strong id="deleteThemeName" class="text-slate-900 dark:text-white font-semibold"></strong> không?
                Hành động này không thể hoàn tác và chủ đề sẽ bị gỡ bỏ khỏi hệ thống.
            </p>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-700/80">
                <button type="button" onclick="closeModal('deleteThemeModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Xác nhận xóa</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDeleteModal(id, name) {
        document.getElementById('deleteThemeName').textContent = name;
        document.getElementById('deleteThemeForm').action = '/themes/' + id;
        openModal('deleteThemeModal');
    }

    function closeDeleteModal() {
        closeModal('deleteThemeModal');
    }
</script>
