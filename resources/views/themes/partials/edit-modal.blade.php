<div id="editThemeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('editThemeModal')">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center justify-between px-5 sm:px-7 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-heading text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/30 text-pcrm-700 dark:text-pcrm-300 flex items-center justify-center shrink-0">
                    <i class="bi bi-pencil-square text-base"></i>
                </span>
                <span>Chỉnh sửa chủ đề & sự kiện</span>
            </h3>
            <button type="button" onclick="closeModal('editThemeModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- Form --}}
        <form id="editThemeForm" method="POST" action="" class="flex flex-col flex-1 min-h-0">
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="editThemeModal">

            <div class="min-h-0 overflow-y-auto px-5 sm:px-7 py-5 space-y-6 flex-1">

                {{-- Group 1: General Info --}}
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">
                        1. Thông tin chung & Cấp độ
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Tên chủ đề <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="editName" required class="form-input text-sm">
                        </div>
                        <div>
                            <label class="form-label">Mã định danh (Slug) <span class="text-red-500">*</span></label>
                            <input type="text" name="slug" id="editSlug" required class="form-input text-sm font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="form-label">Cấp độ (Level)</label>
                            <select name="level" id="editLevel" class="form-input text-sm">
                                <option value="1">1 — Corporate (Trang trọng)</option>
                                <option value="2">2 — Celebration (Lễ hội)</option>
                                <option value="3">3 — Company Event (Nội bộ)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Độ ưu tiên (Priority) <span class="text-red-500">*</span></label>
                            <input type="number" name="priority" id="editPriority" min="0" max="1000" required class="form-input text-sm font-mono">
                            <p class="text-[11px] text-slate-400 mt-1">Số cao hơn sẽ ưu tiên hiển thị trước</p>
                        </div>
                        <div>
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="editStatus" class="form-input text-sm">
                                <option value="active">Đang kích hoạt (Active)</option>
                                <option value="scheduled">Đã lên lịch (Scheduled)</option>
                                <option value="paused">Tạm dừng (Paused)</option>
                                <option value="draft">Bản nháp (Draft)</option>
                                <option value="archived">Lưu trữ (Archived)</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Group 2: Time Schedule --}}
                <div class="border-t border-slate-100 dark:border-slate-700/80 pt-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">
                        2. Lịch kích hoạt tự động
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Thời gian bắt đầu</label>
                            <input type="datetime-local" name="start_at" id="editStartAt" class="form-input text-sm">
                        </div>
                        <div>
                            <label class="form-label">Thời gian kết thúc</label>
                            <input type="datetime-local" name="end_at" id="editEndAt" class="form-input text-sm">
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                        Nếu để trống thời gian, chủ đề được xem là "Luôn sẵn sàng" và chỉ kích hoạt khi bật trạng thái Đang chạy (Active).
                    </p>
                </div>

                {{-- Group 3: Color & Scope --}}
                <div class="border-t border-slate-100 dark:border-slate-700/80 pt-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">
                        3. Màu sắc chủ đạo & Phạm vi áp dụng
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Màu Accent chủ đạo (Hex)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" id="editAccentPicker" value="#2F55E7"
                                       oninput="document.getElementById('editAccent').value = this.value"
                                       class="w-10 h-10 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer p-0.5 bg-white dark:bg-slate-800">
                                <div class="relative flex-1">
                                    <input type="text" name="accent_color" id="editAccent"
                                           oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('editAccentPicker').value = this.value"
                                           class="form-input text-sm font-mono pl-3" placeholder="#2F55E7">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Phạm vi hiển thị áp dụng</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-0.5">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                    <input type="checkbox" name="scope[]" id="editScopeLogin" value="login" class="rounded text-pcrm-600 focus:ring-pcrm-500">
                                    <span>Đăng nhập</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                    <input type="checkbox" name="scope[]" id="editScopeDashboard" value="dashboard_greeting" class="rounded text-pcrm-600 focus:ring-pcrm-500">
                                    <span>Dashboard</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                    <input type="checkbox" name="scope[]" id="editScopeHeader" value="app_header" class="rounded text-pcrm-600 focus:ring-pcrm-500">
                                    <span>Header</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Group 4: Greetings & Texts --}}
                <div class="border-t border-slate-100 dark:border-slate-700/80 pt-4 space-y-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        4. Lời chúc & Thông điệp hiển thị
                    </h4>
                    <div>
                        <label class="form-label">Lời chào trang đăng nhập</label>
                        <input type="text" name="login_greeting" id="editLoginGreeting" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Mô tả phụ trang đăng nhập</label>
                        <input type="text" name="login_subtitle" id="editLoginSubtitle" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Lời chào Dashboard (<code class="text-pcrm-600">:name</code> đại diện cho tên nhân viên)</label>
                        <input type="text" name="dashboard_greeting" id="editDashboardGreeting" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Thông điệp chân trang / Trustline</label>
                        <input type="text" name="trust_line" id="editTrustLine" class="form-input text-sm">
                    </div>
                </div>

            </div>{{-- /scrollable body --}}

            {{-- Footer --}}
            <div class="px-5 sm:px-7 py-4 bg-slate-50 dark:bg-slate-800/80 border-t border-slate-200 dark:border-slate-700 flex items-center justify-end gap-3 shrink-0">
                <button type="button" onclick="closeModal('editThemeModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật chủ đề</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(theme) {
        document.getElementById('editThemeForm').action = '/themes/' + theme.id;
        document.getElementById('editName').value = theme.name || '';
        document.getElementById('editSlug').value = theme.slug || '';
        document.getElementById('editLevel').value = theme.level || 1;
        document.getElementById('editPriority').value = theme.priority ?? 0;
        document.getElementById('editStatus').value = theme.status || 'draft';

        // Format dates YYYY-MM-DDTHH:mm
        if (theme.start_at) {
            document.getElementById('editStartAt').value = theme.start_at.substring(0, 16);
        } else {
            document.getElementById('editStartAt').value = '';
        }
        if (theme.end_at) {
            document.getElementById('editEndAt').value = theme.end_at.substring(0, 16);
        } else {
            document.getElementById('editEndAt').value = '';
        }

        const config = theme.config || {};
        const colors = config.colors || {};
        const content = config.content || {};

        const accent = colors.accent || '#2F55E7';
        document.getElementById('editAccent').value = accent;
        document.getElementById('editAccentPicker').value = accent;

        // Scope checkboxes
        const scope = Array.isArray(theme.scope) ? theme.scope : [];
        document.getElementById('editScopeLogin').checked = scope.includes('login');
        document.getElementById('editScopeDashboard').checked = scope.includes('dashboard_greeting');
        document.getElementById('editScopeHeader').checked = scope.includes('app_header');

        document.getElementById('editLoginGreeting').value = content.loginGreeting || '';
        document.getElementById('editLoginSubtitle').value = content.loginSubtitle || '';
        document.getElementById('editDashboardGreeting').value = content.dashboardGreeting || '';
        if (document.getElementById('editTrustLine')) {
            document.getElementById('editTrustLine').value = content.trustLine || '';
        }

        openModal('editThemeModal');
    }

    function closeEditModal() {
        closeModal('editThemeModal');
    }
</script>
