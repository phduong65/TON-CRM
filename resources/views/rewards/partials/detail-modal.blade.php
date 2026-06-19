{{-- Reward Detail Modal — click-to-open, approve/reject inline --}}
<div id="rewardDetailModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
    onclick="if(event.target===this)closeRewardDetail()">

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-xl flex flex-col"
        style="max-height: 95vh;">

        {{-- ── Header ── --}}
        <div class="flex items-start justify-between px-4 sm:px-6 pt-4 sm:pt-5 pb-3 sm:pb-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            {{-- Loading skeleton --}}
            <div id="rwd-header-loading" class="flex items-center gap-3 w-full">
                <div class="w-8 h-8 rounded-lg bg-slate-200 dark:bg-slate-700 animate-pulse shrink-0"></div>
                <div class="flex-1 space-y-1.5">
                    <div class="h-4 w-28 bg-slate-200 dark:bg-slate-700 rounded animate-pulse"></div>
                    <div class="h-3 w-16 bg-slate-200 dark:bg-slate-700 rounded animate-pulse"></div>
                </div>
            </div>
            {{-- Populated header --}}
            <div id="rwd-header-content" class="hidden flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span id="rwd-code"
                        class="font-mono text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded"></span>
                    <span id="rwd-status-badge" class="badge"></span>
                </div>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                    Tạo lúc <span id="rwd-created-at"></span>
                </p>
            </div>
            <button onclick="closeRewardDetail()"
                class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 shrink-0 ml-3">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- ── Body (scrollable) ── --}}
        <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-4 sm:py-5 space-y-5">

            {{-- Loading skeleton body --}}
            <div id="rwd-body-loading" class="space-y-3">
                @for ($i = 0; $i < 4; $i++)
                    <div class="h-10 bg-slate-100 dark:bg-slate-700/50 rounded-lg animate-pulse"></div>
                @endfor
            </div>

            {{-- Populated body --}}
            <div id="rwd-body-content" class="hidden space-y-5">

                {{-- Target + reward type --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                    <div>
                        <p id="rwd-target-label" class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">
                            Nhân viên được thưởng</p>
                        <p id="rwd-employee-name" class="text-sm font-semibold text-slate-900 dark:text-white"></p>
                        <p id="rwd-employee-meta" class="text-xs text-slate-400 dark:text-slate-500"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">
                            Loại thưởng</p>
                        <p id="rwd-reward-type" class="text-sm font-medium text-slate-800 dark:text-slate-200"></p>
                    </div>
                </div>

                {{-- Points --}}
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">
                        Điểm thưởng</p>
                    <p id="rwd-points" class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 leading-none"></p>
                </div>

                {{-- Description --}}
                <div id="rwd-desc-wrap" class="hidden">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Lý do / Mô tả</p>
                    <p id="rwd-description"
                        class="text-sm text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 leading-relaxed"></p>
                </div>

                {{-- Members --}}
                <div id="rwd-members-wrap" class="hidden">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                        Thành viên thưởng thêm</p>
                    <div id="rwd-members-list"
                        class="divide-y divide-slate-100 dark:divide-slate-700 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                    </div>
                </div>

                {{-- Approval info --}}
                <div id="rwd-approved-wrap"
                    class="hidden rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-lg"></i>
                        <div>
                            <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 mb-0.5">
                                Đã duyệt bởi <span id="rwd-approver-name"></span>
                            </p>
                            <p id="rwd-approved-at" class="text-xs text-emerald-600/70 dark:text-emerald-500"></p>
                        </div>
                    </div>
                </div>

                {{-- Rejection info --}}
                <div id="rwd-rejected-wrap"
                    class="hidden rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3">
                    <div class="flex items-start gap-2">
                        <i class="bi bi-x-circle-fill text-red-600 dark:text-red-400 text-lg mt-0.5 shrink-0"></i>
                        <div>
                            <p class="text-xs font-semibold text-red-700 dark:text-red-400 mb-0.5">Lý do từ chối</p>
                            <p id="rwd-rejected-reason"
                                class="text-sm text-red-700 dark:text-red-300 leading-relaxed"></p>
                        </div>
                    </div>
                </div>

                {{-- Inline reject form (hidden until toggled) --}}
                <div id="rwd-reject-form-wrap" class="hidden">
                    <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-4 space-y-3">
                        <p class="text-sm font-semibold text-red-700 dark:text-red-400 flex items-center gap-1.5">
                            <i class="bi bi-x-circle"></i> Từ chối phiếu thưởng
                        </p>
                        <form id="rwd-reject-form" method="POST">
                            @csrf
                            <textarea name="rejected_reason" rows="3" class="form-input text-sm"
                                      placeholder="Nhập lý do từ chối..." required></textarea>
                            <div class="flex gap-2 mt-3">
                                <button type="button" onclick="toggleRewardReject(false)"
                                    class="btn-secondary btn-sm flex-1">Hủy</button>
                                <button type="submit" class="btn-danger btn-sm flex-1">
                                    <i class="bi bi-x-circle"></i> Xác nhận từ chối
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Footer / Actions ── --}}
        <div class="shrink-0 px-4 sm:px-6 py-3 sm:py-4 border-t border-slate-200 dark:border-slate-700">
            {{-- Loading skeleton footer --}}
            <div id="rwd-footer-loading" class="flex gap-2">
                <div class="h-9 w-20 bg-slate-200 dark:bg-slate-700 rounded-lg animate-pulse"></div>
                <div class="h-9 w-20 bg-slate-200 dark:bg-slate-700 rounded-lg animate-pulse ml-auto"></div>
            </div>

            {{-- Populated footer --}}
            <div id="rwd-footer-content" class="hidden flex items-center gap-2 flex-wrap">
                <button onclick="closeRewardDetail()" class="btn-secondary btn-sm">
                    Đóng
                </button>
                <a id="rwd-detail-link" href="#" class="btn-primary btn-sm text-xs">
                    Xem chi tiết
                </a>

                {{-- Pending-only actions (shown via JS) --}}
                <div id="rwd-pending-actions" class="hidden flex items-center gap-2 ml-auto flex-wrap">
                    <button id="rwd-reject-btn" onclick="toggleRewardReject(true)"
                        class="btn-danger btn-sm hidden">
                        <i class="bi bi-x-circle"></i> Từ chối
                    </button>
                    <form id="rwd-approve-form" method="POST" class="hidden"
                        onsubmit="return confirm('Xác nhận duyệt phiếu thưởng này?')">
                        @csrf
                        <button type="submit" class="btn-primary btn-sm">
                            <i class="bi bi-check-circle"></i> Duyệt thưởng
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    var _baseUrl = '{{ url('rewards') }}';

    function text(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val || '—';
    }

    window.openRewardDetail = function (rewardId) {
        openModal('rewardDetailModal');
        _setLoading(true);
        toggleRewardReject(false);

        fetch(_baseUrl + '/' + rewardId + '/detail-json', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { _populate(data); _setLoading(false); })
        .catch(function () { _setLoading(false); alert('Không thể tải dữ liệu. Vui lòng thử lại.'); });
    };

    window.closeRewardDetail = function () {
        closeModal('rewardDetailModal');
    };

    window.toggleRewardReject = function (open) {
        var wrap = document.getElementById('rwd-reject-form-wrap');
        var actions = document.getElementById('rwd-pending-actions');
        if (!wrap) return;
        if (open) {
            wrap.classList.remove('hidden');
            actions && actions.classList.add('hidden');
        } else {
            wrap.classList.add('hidden');
            actions && actions.classList.remove('hidden');
        }
    };

    function _setLoading(on) {
        ['rwd-header-loading', 'rwd-body-loading', 'rwd-footer-loading'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', !on);
        });
        ['rwd-header-content', 'rwd-body-content', 'rwd-footer-content'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', on);
        });
    }

    function _populate(d) {
        /* header */
        text('rwd-code', d.code);
        text('rwd-created-at', d.created_at);
        var badge = document.getElementById('rwd-status-badge');
        if (badge) {
            badge.className = 'badge ' + ({ pending: 'badge-warning', approved: 'badge-success', rejected: 'badge-danger' }[d.status] || 'badge-neutral');
            badge.textContent = d.status_label;
        }

        /* target display — thay đổi label và giá trị theo target_type */
        var targetLabelEl = document.getElementById('rwd-target-label');
        var targetLabelMap = {
            individual: 'Nhân viên được thưởng',
            branch:     'Chi nhánh được thưởng',
            team:       'Đội nhóm được thưởng',
            all:        'Đối tượng được thưởng',
        };
        if (targetLabelEl) targetLabelEl.textContent = targetLabelMap[d.target_type] || 'Đối tượng';

        if (d.target_type === 'individual') {
            text('rwd-employee-name', d.employee.name);
            var meta = [d.employee.code, d.employee.branch].filter(Boolean).join(' · ');
            text('rwd-employee-meta', meta || null);
        } else {
            text('rwd-employee-name', d.target_label);
            text('rwd-employee-meta', null);
        }

        /* reward type + points */
        text('rwd-reward-type', d.reward_type);
        text('rwd-points', '+' + d.total_points_awarded + ' điểm');

        /* description */
        var descWrap = document.getElementById('rwd-desc-wrap');
        if (descWrap) {
            if (d.description) { descWrap.classList.remove('hidden'); text('rwd-description', d.description); }
            else { descWrap.classList.add('hidden'); }
        }

        /* members */
        var membersWrap = document.getElementById('rwd-members-wrap');
        var membersList = document.getElementById('rwd-members-list');
        if (membersWrap && membersList) {
            if (d.members && d.members.length > 0) {
                membersWrap.classList.remove('hidden');
                membersList.innerHTML = d.members.map(function (m) {
                    return '<div class="flex items-center justify-between px-3 py-2.5 text-sm">' +
                        '<div><span class="font-medium text-slate-800 dark:text-slate-200">' + (m.employee_name || '—') + '</span>' +
                        (m.employee_code ? '<span class="text-xs text-slate-400 ml-1.5">' + m.employee_code + '</span>' : '') +
                        (m.note ? '<p class="text-xs text-slate-400 mt-0.5">' + m.note + '</p>' : '') +
                        '</div>' +
                        '<span class="font-semibold text-emerald-600 dark:text-emerald-400 shrink-0 ml-4">+' + m.points_awarded + 'đ</span>' +
                        '</div>';
                }).join('');
            } else {
                membersWrap.classList.add('hidden');
            }
        }

        /* approved / rejected info */
        var approvedWrap = document.getElementById('rwd-approved-wrap');
        var rejectedWrap = document.getElementById('rwd-rejected-wrap');
        if (approvedWrap) approvedWrap.classList.toggle('hidden', d.status !== 'approved');
        if (rejectedWrap) rejectedWrap.classList.toggle('hidden', d.status !== 'rejected' || !d.rejected_reason);
        if (d.status === 'approved') {
            text('rwd-approver-name', d.approver);
            text('rwd-approved-at', d.approved_at);
        }
        if (d.status === 'rejected') {
            text('rwd-rejected-reason', d.rejected_reason);
        }

        /* detail link */
        var detailLink = document.getElementById('rwd-detail-link');
        if (detailLink) detailLink.href = _baseUrl + '/' + d.id;

        /* action buttons */
        var pendingActions = document.getElementById('rwd-pending-actions');
        var approveForm = document.getElementById('rwd-approve-form');
        var rejectBtn = document.getElementById('rwd-reject-btn');
        var rejectForm = document.getElementById('rwd-reject-form');

        if (pendingActions) pendingActions.classList.toggle('hidden', d.status !== 'pending');

        var canApprove = d.status === 'pending' && d.can_approve;
        if (approveForm) {
            approveForm.classList.toggle('hidden', !canApprove);
            if (canApprove) approveForm.action = _baseUrl + '/' + d.id + '/approve';
        }
        if (rejectBtn) rejectBtn.classList.toggle('hidden', !canApprove);
        if (rejectForm) rejectForm.action = _baseUrl + '/' + d.id + '/reject';
    }
}());
</script>
@endpush
