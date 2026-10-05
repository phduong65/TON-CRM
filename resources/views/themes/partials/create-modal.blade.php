<div id="createThemeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createThemeModal')">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center justify-between px-5 sm:px-7 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-heading text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/30 text-pcrm-700 dark:text-pcrm-300 flex items-center justify-center shrink-0">
                    <i class="bi bi-palette text-base"></i>
                </span>
                <span>Tạo chủ đề & sự kiện mới</span>
            </h3>
            <button type="button" onclick="closeModal('createThemeModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('themes.store') }}" class="flex flex-col flex-1 min-h-0">
            @csrf
            <input type="hidden" name="_modal" value="createThemeModal">

            <div class="min-h-0 overflow-y-auto px-5 sm:px-7 py-5 space-y-6 flex-1">

                {{-- Preset Catalog Quick Select --}}
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/30 p-3.5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <i class="bi bi-stars text-amber-500"></i> Chọn nhanh từ mẫu sự kiện lễ hội:
                        </span>
                        <span class="text-[11px] text-slate-400">Tự điền nhanh thông điệp & màu sắc</span>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="button" onclick="applyPreset('tet')" class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-pcrm-500 hover:bg-pcrm-50 dark:hover:bg-pcrm-900/20 text-xs font-semibold text-slate-700 dark:text-slate-200 transition shadow-2xs">
                            🧧 Tết Nguyên Đán
                        </button>
                        <button type="button" onclick="applyPreset('quockhanh')" class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-pcrm-500 hover:bg-pcrm-50 dark:hover:bg-pcrm-900/20 text-xs font-semibold text-slate-700 dark:text-slate-200 transition shadow-2xs">
                            🇻🇳 Quốc khánh 2/9
                        </button>
                        <button type="button" onclick="applyPreset('phunu')" class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-pcrm-500 hover:bg-pcrm-50 dark:hover:bg-pcrm-900/20 text-xs font-semibold text-slate-700 dark:text-slate-200 transition shadow-2xs">
                            🌸 Phụ nữ 8/3 & 20/10
                        </button>
                        <button type="button" onclick="applyPreset('noel')" class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-pcrm-500 hover:bg-pcrm-50 dark:hover:bg-pcrm-900/20 text-xs font-semibold text-slate-700 dark:text-slate-200 transition shadow-2xs">
                            🎄 Giáng sinh (Noel)
                        </button>
                        <button type="button" onclick="applyPreset('sinhnhat')" class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-pcrm-500 hover:bg-pcrm-50 dark:hover:bg-pcrm-900/20 text-xs font-semibold text-slate-700 dark:text-slate-200 transition shadow-2xs">
                            🎉 Sinh nhật công ty
                        </button>
                    </div>
                </div>

                {{-- Group 1: General Info --}}
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">
                        1. Thông tin chung & Cấp độ
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Tên chủ đề <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="createName" required value="{{ old('name') }}"
                                   placeholder="VD: Tết Nguyên Đán 2027" class="form-input text-sm">
                            @error('name') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Mã định danh (Slug) <span class="text-red-500">*</span></label>
                            <input type="text" name="slug" id="createSlug" required value="{{ old('slug') }}"
                                   placeholder="VD: tet-nguyen-dan-2027" class="form-input text-sm font-mono">
                            @error('slug') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="form-label">Cấp độ (Level)</label>
                            <select name="level" id="createLevel" class="form-input text-sm">
                                <option value="1">1 — Corporate (Trang trọng)</option>
                                <option value="2" selected>2 — Celebration (Lễ hội)</option>
                                <option value="3">3 — Company Event (Nội bộ)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Độ ưu tiên (Priority) <span class="text-red-500">*</span></label>
                            <input type="number" name="priority" id="createPriority" value="{{ old('priority', 80) }}" min="0" max="1000" required
                                   class="form-input text-sm font-mono">
                            <p class="text-[11px] text-slate-400 mt-1">Số cao hơn sẽ ưu tiên hiển thị trước</p>
                        </div>
                        <div>
                            <label class="form-label">Trạng thái ban đầu</label>
                            <select name="status" id="createStatus" class="form-input text-sm">
                                <option value="scheduled" selected>Đã lên lịch (Scheduled)</option>
                                <option value="active">Kích hoạt ngay (Active)</option>
                                <option value="draft">Bản nháp (Draft)</option>
                                <option value="paused">Tạm dừng (Paused)</option>
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
                            <input type="datetime-local" name="start_at" id="createStartAt" value="{{ old('start_at') }}" class="form-input text-sm">
                        </div>
                        <div>
                            <label class="form-label">Thời gian kết thúc</label>
                            <input type="datetime-local" name="end_at" id="createEndAt" value="{{ old('end_at') }}" class="form-input text-sm">
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
                                <input type="color" id="createAccentPicker" value="#2F55E7"
                                       oninput="document.getElementById('createAccent').value = this.value"
                                       class="w-10 h-10 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer p-0.5 bg-white dark:bg-slate-800">
                                <div class="relative flex-1">
                                    <input type="text" name="accent_color" id="createAccent" value="{{ old('accent_color', '#2F55E7') }}"
                                           oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('createAccentPicker').value = this.value"
                                           class="form-input text-sm font-mono pl-3" placeholder="#2F55E7">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Phạm vi hiển thị áp dụng</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-0.5">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                    <input type="checkbox" name="scope[]" value="login" checked class="rounded text-pcrm-600 focus:ring-pcrm-500">
                                    <span>Đăng nhập</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                    <input type="checkbox" name="scope[]" value="dashboard_greeting" checked class="rounded text-pcrm-600 focus:ring-pcrm-500">
                                    <span>Dashboard</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                    <input type="checkbox" name="scope[]" value="app_header" checked class="rounded text-pcrm-600 focus:ring-pcrm-500">
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
                        <input type="text" name="login_greeting" id="createLoginGreeting"
                               value="{{ old('login_greeting', 'Chào mừng trở lại') }}" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Mô tả phụ trang đăng nhập</label>
                        <input type="text" name="login_subtitle" id="createLoginSubtitle"
                               value="{{ old('login_subtitle', 'Quản lý nhân sự, điểm thưởng và kỷ luật — tất cả tại một nơi.') }}" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Lời chào Dashboard (<code class="text-pcrm-600">:name</code> đại diện cho tên nhân viên)</label>
                        <input type="text" name="dashboard_greeting" id="createDashboardGreeting"
                               value="{{ old('dashboard_greeting', 'Xin chào, :name 👋') }}" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Thông điệp chân trang / Trustline</label>
                        <input type="text" name="trust_line" id="createTrustLine"
                               value="{{ old('trust_line', 'Hệ thống nội bộ TON Capital · Bảo mật thông tin') }}" class="form-input text-sm">
                    </div>
                </div>

            </div>{{-- /scrollable body --}}

            {{-- Footer --}}
            <div class="px-5 sm:px-7 py-4 bg-slate-50 dark:bg-slate-800/80 border-t border-slate-200 dark:border-slate-700 flex items-center justify-end gap-3 shrink-0">
                <button type="button" onclick="closeModal('createThemeModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Lưu & Áp dụng</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        openModal('createThemeModal');
    }
    function closeCreateModal() {
        closeModal('createThemeModal');
    }

    function applyPreset(type) {
        const presets = {
            tet: {
                name: 'Tết Nguyên Đán',
                slug: 'tet-nguyen-dan-moi',
                level: '2',
                priority: 100,
                accent: '#DC2626',
                greeting: '🧧 Chúc mừng năm mới',
                sub: 'Kính chúc bạn năm mới An Khang Thịnh Vượng — Vạn Sự Như Ý!',
                dash: '🧧 Chúc mừng năm mới, :name!',
                trust: 'Hệ thống nội bộ TON Capital · Chúc mừng năm mới'
            },
            quockhanh: {
                name: 'Quốc khánh 2/9',
                slug: 'quoc-khanh-2-9-moi',
                level: '1',
                priority: 90,
                accent: '#DC2626',
                greeting: '🇻🇳 Chào mừng Quốc khánh 2/9',
                sub: 'Tự hào non sông Việt Nam — Đoàn kết và phát triển bền vững.',
                dash: '🇻🇳 Chào mừng Quốc khánh 2/9, :name!',
                trust: 'Hệ thống nội bộ TON Capital · Chào mừng Quốc khánh 2/9'
            },
            phunu: {
                name: 'Ngày Phụ Nữ',
                slug: 'phu-nu-viet-nam',
                level: '1',
                priority: 70,
                accent: '#DB2777',
                greeting: '🌸 Chúc mừng ngày Phụ Nữ',
                sub: 'Chúc một nửa thế giới luôn rạng rỡ, hạnh phúc và thành công!',
                dash: '🌸 Chúc mừng ngày Phụ Nữ, :name!',
                trust: 'Hệ thống nội bộ TON Capital · Tôn vinh phái đẹp'
            },
            noel: {
                name: 'Giáng Sinh / Noel',
                slug: 'giang-sinh-noel',
                level: '2',
                priority: 80,
                accent: '#059669',
                greeting: '🎄 Chúc mừng Giáng sinh',
                sub: 'Kính chúc bạn và gia đình một mùa Giáng sinh an lành và ấm áp!',
                dash: '🎄 Chúc mừng Giáng sinh ấm áp, :name!',
                trust: 'Hệ thống nội bộ TON Capital · Merry Christmas'
            },
            sinhnhat: {
                name: 'Sinh nhật TON Capital',
                slug: 'sinh-nhat-ton-capital-moi',
                level: '3',
                priority: 85,
                accent: '#2F55E7',
                greeting: '🎉 Chúc mừng sinh nhật TON Capital',
                sub: 'Cùng chúc mừng ngày thành lập và chặng đường phát triển vững mạnh!',
                dash: '🎉 Chúc mừng sinh nhật TON Capital, :name!',
                trust: 'Hệ thống nội bộ TON Capital · Tự hào chặng đường phát triển'
            }
        };

        const p = presets[type];
        if (!p) return;

        document.getElementById('createName').value = p.name;
        document.getElementById('createSlug').value = p.slug;
        document.getElementById('createLevel').value = p.level;
        document.getElementById('createPriority').value = p.priority;
        document.getElementById('createAccent').value = p.accent;
        document.getElementById('createAccentPicker').value = p.accent;
        document.getElementById('createLoginGreeting').value = p.greeting;
        document.getElementById('createLoginSubtitle').value = p.sub;
        document.getElementById('createDashboardGreeting').value = p.dash;
        if (document.getElementById('createTrustLine')) {
            document.getElementById('createTrustLine').value = p.trust;
        }
    }
</script>
