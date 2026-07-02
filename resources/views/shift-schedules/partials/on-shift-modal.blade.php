{{-- Nhân viên đang trong ca — click-to-open, fetch JSON theo bộ lọc hiện tại của trang xếp ca --}}
<div id="onShiftModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
    onclick="if(event.target===this) closeModal('onShiftModal')">

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col" style="max-height: 90vh;">

        {{-- ── Header ── --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                    <i class="bi bi-person-badge-fill text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Nhân viên đang trong ca làm</h3>
                    <p id="onShiftGeneratedAt" class="text-xs text-slate-400 mt-0.5">Đang tải...</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span id="onShiftCountBadge"
                    class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                    <i class="bi bi-record-circle-fill text-xs animate-pulse"></i> 0 đang làm việc
                </span>
                <button onclick="closeModal('onShiftModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>
        </div>

        {{-- ── Filter: chi nhánh ── --}}
        <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 shrink-0">
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Chi nhánh</label>
            <select id="onShiftBranchFilter" class="form-input h-9 text-sm w-full sm:w-64">
                <option value="">Tất cả chi nhánh</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- ── Body (scrollable) ── --}}
        <div class="flex-1 overflow-y-auto px-5 py-4">

            {{-- Loading skeleton --}}
            <div id="onShiftLoading" class="space-y-2">
                @for ($i = 0; $i < 4; $i++)
                    <div class="h-14 bg-slate-100 dark:bg-slate-700/50 rounded-lg animate-pulse"></div>
                @endfor
            </div>

            {{-- Empty state --}}
            <div id="onShiftEmpty" class="hidden p-8 text-center">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                    <i class="bi bi-moon-stars text-xl text-slate-400"></i>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">Hiện không có nhân viên nào đang trong ca làm</p>
            </div>

            {{-- Populated list --}}
            <div id="onShiftList" class="hidden divide-y divide-slate-100 dark:divide-slate-700/50"></div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function() {
            'use strict';

            var _initialized = false;

            window.openOnShiftModal = function() {
                openModal('onShiftModal');

                // Lần mở đầu tiên: đồng bộ dropdown chi nhánh trong modal theo bộ lọc
                // đang áp dụng trên trang xếp ca (nếu có). Các lần mở sau giữ nguyên
                // lựa chọn gần nhất của người dùng trong modal.
                if (!_initialized) {
                    var branchSelect = document.getElementById('onShiftBranchFilter');
                    var pageBranchEl = document.querySelector('[name="branch_id"]');
                    if (branchSelect && pageBranchEl && pageBranchEl.value) {
                        branchSelect.value = pageBranchEl.value;
                    }
                    _initialized = true;
                }

                _fetchOnShift();
            };

            document.addEventListener('DOMContentLoaded', function() {
                var branchSelect = document.getElementById('onShiftBranchFilter');
                if (branchSelect) branchSelect.addEventListener('change', _fetchOnShift);
            });

            function _fetchOnShift() {
                var loading = document.getElementById('onShiftLoading');
                var empty   = document.getElementById('onShiftEmpty');
                var list    = document.getElementById('onShiftList');

                loading.classList.remove('hidden');
                empty.classList.add('hidden');
                list.classList.add('hidden');
                list.innerHTML = '';

                var params = new URLSearchParams();
                var branchSelect = document.getElementById('onShiftBranchFilter');
                if (branchSelect && branchSelect.value) params.set('branch_id', branchSelect.value);

                ['team_id', 'employee_id'].forEach(function(key) {
                    var el = document.querySelector('[name="' + key + '"]');
                    if (el && el.value) params.set(key, el.value);
                });

                fetch('{{ route('shift-schedules.on-shift') }}?' + params.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        loading.classList.add('hidden');
                        document.getElementById('onShiftGeneratedAt').textContent = 'Cập nhật lúc ' + data.generated_at;
                        document.getElementById('onShiftCountBadge').innerHTML =
                            '<i class="bi bi-record-circle-fill text-xs animate-pulse"></i> ' + data.count + ' đang làm việc';

                        if (!data.employees.length) {
                            empty.classList.remove('hidden');
                            return;
                        }

                        list.classList.remove('hidden');
                        list.innerHTML = data.employees.map(_renderRow).join('');
                    })
                    .catch(function() {
                        loading.classList.add('hidden');
                        alert('Không thể tải dữ liệu. Vui lòng thử lại.');
                    });
            }

            function _renderRow(e) {
                var initials = (e.employee_name || 'N').trim().substring(0, 1).toUpperCase();
                var metaParts = [e.branch, e.team].filter(Boolean).join(' · ');

                var statusBadge;
                if (e.in_shift_window === false) {
                    statusBadge = '<span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">' +
                        '<i class="bi bi-exclamation-triangle-fill" style="font-size:0.6rem"></i> Ngoài giờ ca</span>';
                } else {
                    statusBadge = '<span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">' +
                        '<i class="bi bi-record-circle-fill" style="font-size:0.6rem"></i> Đang trong ca</span>';
                }

                var shiftInfo = [e.shift_name, e.shift_time].filter(Boolean).join(' ');

                return '<div class="flex items-center justify-between py-3 gap-3">' +
                    '<div class="flex items-center gap-2.5 min-w-0">' +
                        '<div class="w-8 h-8 rounded-full bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-xs font-semibold text-emerald-700 dark:text-emerald-400 flex-shrink-0">' +
                            initials +
                        '</div>' +
                        '<div class="min-w-0">' +
                            '<p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">' + (e.employee_name || 'N/A') + '</p>' +
                            '<p class="text-xs text-slate-400 truncate">' + (metaParts || '—') +
                                (shiftInfo ? '<span class="mx-1 opacity-40">·</span>' + shiftInfo : '') +
                            '</p>' +
                        '</div>' +
                    '</div>' +
                    '<div class="flex flex-col items-end gap-1 flex-shrink-0">' +
                        statusBadge +
                        '<span class="text-xs text-slate-400">Vào lúc ' + e.check_in_at + '</span>' +
                    '</div>' +
                '</div>';
            }
        }());
    </script>
@endpush
