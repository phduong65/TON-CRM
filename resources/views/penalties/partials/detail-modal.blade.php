{{-- Penalty Detail Modal — click-to-open, approve/reject inline --}}
<div id="penaltyDetailModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
    onclick="if(event.target===this)closePenaltyDetail()">

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-xl flex flex-col"
        style="max-height: 90vh;">

        {{-- ── Header ── --}}
        <div id="detail-header"
            class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            {{-- Loading skeleton --}}
            <div id="detail-header-loading" class="flex items-center gap-3 w-full">
                <div class="w-8 h-8 rounded-lg bg-slate-200 dark:bg-slate-700 animate-pulse shrink-0"></div>
                <div class="flex-1 space-y-1.5">
                    <div class="h-4 w-28 bg-slate-200 dark:bg-slate-700 rounded animate-pulse"></div>
                    <div class="h-3 w-16 bg-slate-200 dark:bg-slate-700 rounded animate-pulse"></div>
                </div>
            </div>
            {{-- Populated header --}}
            <div id="detail-header-content" class="hidden flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span id="detail-code"
                        class="font-mono text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded"></span>
                    <span id="detail-status-badge" class="badge"></span>
                </div>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                    Tạo lúc <span id="detail-created-at"></span>
                </p>
            </div>
            <button onclick="closePenaltyDetail()"
                class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 shrink-0 ml-3">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- ── Body (scrollable) ── --}}
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

            {{-- Loading skeleton body --}}
            <div id="detail-body-loading" class="space-y-3">
                @for ($i = 0; $i < 4; $i++)
                    <div class="h-10 bg-slate-100 dark:bg-slate-700/50 rounded-lg animate-pulse"></div>
                @endfor
            </div>

            {{-- Populated body --}}
            <div id="detail-body-content" class="hidden space-y-5">

                {{-- Employee + violation --}}
                <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                    <div>
                        <p
                            class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">
                            Nhân viên vi phạm</p>
                        <p id="detail-employee-name mb-1" class="text-sm font-semibold text-slate-900 dark:text-white"></p>
                        <p id="detail-employee-meta" class="text-xs text-slate-400 dark:text-slate-500"></p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">
                            Vi phạm</p>
                        <p id="detail-violation-name" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                        </p>
                        <p id="detail-regulation-name" class="text-xs text-slate-400 dark:text-slate-500"></p>
                    </div>
                </div>

                {{-- Points / Money --}}
                <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                    <div>
                        <p
                            class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">
                            Điểm trừ</p>
                        <p id="detail-points" class="text-2xl font-bold text-red-600 dark:text-red-400 leading-none">
                        </p>
                    </div>
                    <div id="detail-money-wrap" class="hidden">
                        <p
                            class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-0.5">
                            Tiền phạt</p>
                        <p id="detail-money" class="text-2xl font-bold text-red-600 dark:text-red-400 leading-none"></p>
                    </div>
                </div>

                {{-- Description --}}
                <div id="detail-desc-wrap" class="hidden">
                    <p
                        class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                        Mô tả / Ghi chú</p>
                    <p id="detail-description"
                        class="text-sm text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 leading-relaxed">
                    </p>
                </div>

                {{-- Members --}}
                <div id="detail-members-wrap" class="hidden">
                    <p
                        class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                        Thành viên liên đới</p>
                    <div id="detail-members-list"
                        class="divide-y divide-slate-100 dark:divide-slate-700 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                    </div>
                </div>

                {{-- Attachments --}}
                <div id="detail-attachments-wrap" class="hidden">
                    <p
                        class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                        Tệp đính kèm</p>
                    <div id="detail-attachments-grid" class="grid grid-cols-3 gap-2"></div>
                </div>

                {{-- Approval info --}}
                <div id="detail-approved-wrap"
                    class="hidden rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <i class="ph-check-circle text-emerald-600 dark:text-emerald-400 text-lg"></i>
                        <div>
                            <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 mb-1">Đã duyệt bởi <span
                                    id="detail-approver-name"></span></p>
                            <p id="detail-approved-at" class="text-xs text-emerald-600/70 dark:text-emerald-500"></p>
                        </div>
                    </div>
                </div>

                {{-- Rejection info --}}
                <div id="detail-rejected-wrap"
                    class="hidden rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3">
                    <div class="flex items-start gap-2">
                        <i class="ph-x-circle text-red-600 dark:text-red-400 text-lg mt-0.5 shrink-0"></i>
                        <div>
                            <p class="text-xs font-semibold text-red-700 dark:text-red-400 mb-0.5">Lý do từ chối</p>
                            <p id="detail-rejected-reason"
                                class="text-sm text-red-700 dark:text-red-300 leading-relaxed"></p>
                        </div>
                    </div>
                </div>

                {{-- Inline reject form (hidden until toggled) --}}
                <div id="detail-reject-form-wrap" class="hidden">
                    <div
                        class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-4 space-y-3">
                        <p class="text-sm font-semibold text-red-700 dark:text-red-400 flex items-center gap-1.5">
                            <i class="ph-x-circle"></i> Từ chối xử phạt
                        </p>
                        <form id="detail-reject-form" method="POST">
                            @csrf
                            <textarea name="rejected_reason" rows="3" class="form-input text-sm" placeholder="Nhập lý do từ chối..." required></textarea>
                            <div class="flex gap-2 mt-3">
                                <button type="button" onclick="toggleDetailReject(false)"
                                    class="btn-secondary btn-sm flex-1">Hủy</button>
                                <button type="submit" class="btn-danger btn-sm flex-1">
                                    <i class="ph-x-circle"></i> Xác nhận từ chối
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Footer / Actions ── --}}
        <div class="shrink-0 px-6 py-4 border-t border-slate-200 dark:border-slate-700">
            {{-- Loading skeleton footer --}}
            <div id="detail-footer-loading" class="flex gap-2">
                <div class="h-9 w-20 bg-slate-200 dark:bg-slate-700 rounded-lg animate-pulse"></div>
                <div class="h-9 w-20 bg-slate-200 dark:bg-slate-700 rounded-lg animate-pulse ml-auto"></div>
            </div>

            {{-- Populated footer --}}
            <div id="detail-footer-content" class="hidden flex items-center gap-2 flex-wrap">
                <button onclick="closePenaltyDetail()" class="btn-secondary btn-sm">
                    Đóng
                </button>

                {{-- Pending-only actions (shown via JS) --}}
                <div id="detail-pending-actions" class="hidden flex items-center gap-2 ml-auto flex-wrap">
                    <button id="detail-edit-btn" onclick="openEditFromDetail()" class="btn-secondary btn-sm hidden">
                        <i class="ph-pencil-simple"></i> Sửa
                    </button>
                    <button id="detail-reject-btn" onclick="toggleDetailReject(true)"
                        class="btn-danger btn-sm hidden">
                        <i class="ph-x-circle"></i> Từ chối
                    </button>
                    <form id="detail-approve-form" method="POST" class="hidden"
                        onsubmit="return confirm('Xác nhận duyệt phiếu phạt này?')">
                        @csrf
                        <button type="submit" class="btn-primary btn-sm">
                            <i class="ph-check-circle"></i> Duyệt xử phạt
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

@pushOnce('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5/dist/fancybox/fancybox.css" />
@endPushOnce

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5/dist/fancybox/fancybox.umd.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Fancybox.bind('[data-fancybox]', {
                Toolbar: { display: { left: [], middle: [], right: ['close'] } },
                Images: { zoom: false },
                animated: true,
            });
        });
    </script>
@endPushOnce

@push('scripts')
    <script>
        (function() {
            'use strict';

            var _current = null;

            /* ── helpers ── */
            function show(id) {
                var el = document.getElementById(id);
                if (el) {
                    el.classList.remove('hidden');
                    el.classList.add('flex');
                }
            }

            function hide(id) {
                var el = document.getElementById(id);
                if (el) {
                    el.classList.add('hidden');
                    el.classList.remove('flex');
                }
            }

            function text(id, val) {
                var el = document.getElementById(id);
                if (el) el.textContent = val || '—';
            }

            /* ── open / close ── */
            window.openPenaltyDetail = function(penaltyId) {
                openModal('penaltyDetailModal');
                _setLoading(true);
                toggleDetailReject(false);

                fetch('/penalties/' + penaltyId + '/detail-json', {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        _current = data;
                        _populate(data);
                        _setLoading(false);
                    })
                    .catch(function() {
                        _setLoading(false);
                        alert('Không thể tải dữ liệu. Vui lòng thử lại.');
                    });
            };

            window.closePenaltyDetail = function() {
                closeModal('penaltyDetailModal');
                _current = null;
            };

            /* ── toggle reject inline form ── */
            window.toggleDetailReject = function(open) {
                var wrap = document.getElementById('detail-reject-form-wrap');
                var actionBtns = document.getElementById('detail-pending-actions');
                if (!wrap) return;
                if (open) {
                    wrap.classList.remove('hidden');
                    actionBtns && actionBtns.classList.add('hidden');
                } else {
                    wrap.classList.add('hidden');
                    actionBtns && actionBtns.classList.remove('hidden');
                }
            };

            /* ── open edit modal from detail ── */
            window.openEditFromDetail = function() {
                if (!_current) return;
                closePenaltyDetail();
                openEditPenaltyModal(
                    _current.id,
                    _current.violation_id,
                    _current.employee_id,
                    _current.total_points_deducted,
                    _current.total_money_deducted,
                    _current.description || ''
                );
            };

            /* ── loading state ── */
            function _setLoading(on) {
                ['detail-header-loading', 'detail-body-loading', 'detail-footer-loading'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.classList.toggle('hidden', !on);
                });
                ['detail-header-content', 'detail-body-content', 'detail-footer-content'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.classList.toggle('hidden', on);
                });
            }

            /* ── populate modal ── */
            function _populate(d) {
                /* --- header --- */
                text('detail-code', d.code);
                text('detail-created-at', d.created_at);
                var badge = document.getElementById('detail-status-badge');
                if (badge) {
                    badge.className = 'badge ' + ({
                        pending: 'badge-warning',
                        approved: 'badge-success',
                        rejected: 'badge-danger'
                    } [d.status] || 'badge-neutral');
                    badge.textContent = d.status_label;
                }

                /* --- employee --- */
                text('detail-employee-name', d.employee.name);
                var meta = [d.employee.code, d.employee.branch].filter(Boolean).join(' · ');
                text('detail-employee-meta', meta);

                /* --- violation --- */
                text('detail-violation-name', d.violation.name);
                var regEl = document.getElementById('detail-regulation-name');
                if (regEl) {
                    regEl.textContent = d.violation.regulation || '';
                    regEl.classList.toggle('hidden', !d.violation.regulation);
                }

                /* --- points / money --- */
                text('detail-points', '-' + d.total_points_deducted + ' điểm');
                var moneyWrap = document.getElementById('detail-money-wrap');
                if (moneyWrap) {
                    if (d.total_money_deducted > 0) {
                        moneyWrap.classList.remove('hidden');
                        text('detail-money', new Intl.NumberFormat('vi-VN').format(d.total_money_deducted) + '₫');
                    } else {
                        moneyWrap.classList.add('hidden');
                    }
                }

                /* --- description --- */
                var descWrap = document.getElementById('detail-desc-wrap');
                if (descWrap) {
                    if (d.description) {
                        descWrap.classList.remove('hidden');
                        text('detail-description', d.description);
                    } else {
                        descWrap.classList.add('hidden');
                    }
                }

                /* --- members --- */
                var membersWrap = document.getElementById('detail-members-wrap');
                var membersList = document.getElementById('detail-members-list');
                if (membersWrap && membersList) {
                    if (d.members && d.members.length > 0) {
                        membersWrap.classList.remove('hidden');
                        membersList.innerHTML = d.members.map(function(m) {
                            return '<div class="flex items-center justify-between px-3 py-2.5 text-sm">' +
                                '<div><span class="font-medium text-slate-800 dark:text-slate-200">' + (m
                                    .employee_name || '—') + '</span>' +
                                (m.employee_code ? '<span class="text-xs text-slate-400 ml-1.5">' + m
                                    .employee_code + '</span>' : '') +
                                (m.note ? '<p class="text-xs text-slate-400 mt-0.5">' + m.note + '</p>' : '') +
                                '</div>' +
                                '<span class="font-semibold text-red-600 dark:text-red-400 shrink-0 ml-4">-' + m
                                .points_deducted + 'đ</span>' +
                                '</div>';
                        }).join('');
                    } else {
                        membersWrap.classList.add('hidden');
                    }
                }

                /* --- attachments --- */
                var attWrap = document.getElementById('detail-attachments-wrap');
                var attGrid = document.getElementById('detail-attachments-grid');
                if (attWrap && attGrid) {
                    if (d.attachments && d.attachments.length > 0) {
                        attWrap.classList.remove('hidden');
                        attGrid.innerHTML = d.attachments.map(function(a) {
                            if (a.type === 'image') {
                                return '<a href="' + '/storage/' + a.path + '" data-fancybox="penalty-gallery-' + d.id + '" data-caption="' + a.filename + '"' +
                                    '   class="group relative aspect-square rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 block cursor-zoom-in">' +
                                    '  <img src="' + '/storage/' + a.path + '" alt="' + a.filename +
                                    '" class="w-full h-full object-cover transition-transform group-hover:scale-105">' +
                                    '  <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors"></div>' +
                                    '</a>';
                            }
                            return '<a href="' + a.url + '" target="_blank"' +
                                '   class="aspect-square rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 flex flex-col items-center justify-center gap-1.5 p-2 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">' +
                                '  <i class="bi bi-film text-2xl text-slate-400"></i>' +
                                '  <p class="text-[10px] text-slate-400 text-center leading-tight break-all line-clamp-2">' +
                                a.filename + '</p>' +
                                '  <p class="text-[9px] text-slate-300 dark:text-slate-500">' + a.size +
                                '</p>' +
                                '</a>';
                        }).join('');
                    } else {
                        attWrap.classList.add('hidden');
                    }
                }

                /* --- approval / rejection info --- */
                var approvedWrap = document.getElementById('detail-approved-wrap');
                var rejectedWrap = document.getElementById('detail-rejected-wrap');
                if (approvedWrap) approvedWrap.classList.toggle('hidden', d.status !== 'approved');
                if (rejectedWrap) rejectedWrap.classList.toggle('hidden', d.status !== 'rejected' || !d
                .rejected_reason);
                if (d.status === 'approved') {
                    text('detail-approver-name', d.approver);
                    text('detail-approved-at', d.approved_at);
                }
                if (d.status === 'rejected') {
                    text('detail-rejected-reason', d.rejected_reason);
                }

                /* --- action buttons --- */
                var pendingActions = document.getElementById('detail-pending-actions');
                var approveForm = document.getElementById('detail-approve-form');
                var rejectBtn = document.getElementById('detail-reject-btn');
                var editBtn = document.getElementById('detail-edit-btn');
                var rejectForm = document.getElementById('detail-reject-form');

                if (pendingActions) pendingActions.classList.toggle('hidden', d.status !== 'pending');

                if (approveForm) {
                    var canApprove = d.status === 'pending' && d.can_approve;
                    approveForm.classList.toggle('hidden', !canApprove);
                    if (canApprove) approveForm.action = '/penalties/' + d.id + '/approve';
                }
                if (rejectBtn) rejectBtn.classList.toggle('hidden', !(d.status === 'pending' && d.can_approve));
                if (rejectForm) rejectForm.action = '/penalties/' + d.id + '/reject';
                if (editBtn) editBtn.classList.toggle('hidden', !(d.status === 'pending' && d.can_edit));
            }
        }());
    </script>
@endpush
