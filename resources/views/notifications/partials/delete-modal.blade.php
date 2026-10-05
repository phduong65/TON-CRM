{{-- Xoá 1 thông báo — thay cho confirm() của trình duyệt. Mở bằng openDeleteNotificationModal(actionUrl, title). --}}
<div id="deleteNotificationModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('deleteNotificationModal')">
    <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="deleteNotifTitle">
        <div class="pcrm-dialog-head">
            <span class="pcrm-dialog-icon bg-[#fde8ea] text-[#C94758] dark:bg-[#C94758]/20 dark:text-[#fb7185]" aria-hidden="true"><i class="bi bi-trash"></i></span>
            <div class="min-w-0 flex-1">
                <h3 id="deleteNotifTitle" class="pcrm-dialog-title">Xoá thông báo?</h3>
                <p id="deleteNotifName" class="pcrm-dialog-sub truncate"></p>
            </div>
            <button type="button" onclick="closeModal('deleteNotificationModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="deleteNotificationForm" method="POST" class="pcrm-dialog-form">
            @csrf @method('DELETE')
            <div class="pcrm-dialog-body">
                <p class="text-sm text-slate-600 dark:text-slate-300">Thông báo sẽ bị xoá khỏi hộp thư của bạn. Phiếu hoặc yêu cầu liên quan (nếu có) không bị ảnh hưởng.</p>
            </div>
            <div class="pcrm-dialog-foot">
                <div class="ml-auto flex items-center gap-2">
                    <button type="button" onclick="closeModal('deleteNotificationModal')" class="btn-secondary">Giữ lại</button>
                    <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Xoá</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteNotificationModal(actionUrl, title) {
    document.getElementById('deleteNotificationForm').action = actionUrl;
    document.getElementById('deleteNotifName').textContent = title || '';
    openModal('deleteNotificationModal');
}
</script>
