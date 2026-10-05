{{-- Huỷ báo cáo (chỉ người tạo, khi còn chờ duyệt) — thay cho confirm() của trình duyệt.
     Mở bằng openCancelReportModal(actionUrl, code). --}}
<div id="cancelReportModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('cancelReportModal')">
    <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="cancelReportTitle">
        <div class="pcrm-dialog-head">
            <span class="pcrm-dialog-icon bg-[#fde8ea] text-[#C94758] dark:bg-[#C94758]/20 dark:text-[#fb7185]" aria-hidden="true"><i class="bi bi-trash"></i></span>
            <div class="min-w-0 flex-1">
                <h3 id="cancelReportTitle" class="pcrm-dialog-title">Huỷ báo cáo <span id="cancelReportCode" class="font-mono"></span></h3>
                <p class="pcrm-dialog-sub">Báo cáo chưa được xử lý sẽ bị xoá khỏi hàng đợi duyệt</p>
            </div>
            <button type="button" onclick="closeModal('cancelReportModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="cancelReportForm" method="POST" class="pcrm-dialog-form">
            @csrf @method('DELETE')
            <div class="pcrm-dialog-foot justify-end rounded-t-none">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeModal('cancelReportModal')" class="btn-secondary">Giữ lại</button>
                    <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Huỷ báo cáo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelReportModal(actionUrl, code) {
    document.getElementById('cancelReportForm').action = actionUrl;
    document.getElementById('cancelReportCode').textContent = code;
    openModal('cancelReportModal');
}
</script>
