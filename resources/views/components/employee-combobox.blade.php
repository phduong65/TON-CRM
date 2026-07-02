@props([
    'name' => 'employee_id',
    'employees' => collect(),
    'selected' => null,
    'label' => null,
    'placeholder' => 'Tìm theo tên, mã NV...',
])

@php
    $selectedEmployee = $selected ? $employees->firstWhere('id', (int) $selected) : null;
    $selectedLabel = $selectedEmployee ? $selectedEmployee->name . ' (' . $selectedEmployee->code . ')' : '';
    $comboId = 'combo_' . $name . '_' . uniqid();
    // @json() splits its expression on every comma, so the array literal must be built
    // here first — passing it inline would silently truncate at the 3rd comma.
    $employeeComboData = $employees->map(fn($e) => [
        'id' => $e->id,
        'label' => $e->name . ' (' . $e->code . ')',
        'branch_id' => $e->branch_id,
        'team_id' => $e->team_id,
    ]);
@endphp

<div data-employee-combobox class="relative" {{ $attributes }}>
    @if($label)
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ $label }}</label>
    @endif
    <div class="relative">
        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
        <input type="text" class="emp-combobox-input form-input pl-7 pr-7 h-9 text-sm w-full"
               placeholder="{{ $placeholder }}" value="{{ $selectedLabel }}" autocomplete="off"
               role="combobox" aria-expanded="false" aria-controls="{{ $comboId }}_dropdown">
        <input type="hidden" name="{{ $name }}" class="emp-combobox-value" value="{{ $selected }}">
        <button type="button"
                class="emp-combobox-clear {{ $selectedLabel ? '' : 'hidden' }} absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                title="Xóa lựa chọn">
            <i class="bi bi-x-circle text-sm"></i>
        </button>
    </div>
    <div id="{{ $comboId }}_dropdown"
         class="emp-combobox-dropdown hidden absolute z-20 mt-1 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-lg max-h-56 overflow-y-auto text-sm"></div>
    <script type="application/json" class="emp-combobox-data">@json($employeeComboData)</script>
</div>

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function closeDropdown(root) {
            root.querySelector('.emp-combobox-dropdown').classList.add('hidden');
        }

        function renderList(root, items) {
            const dropdown = root.querySelector('.emp-combobox-dropdown');
            if (!items.length) {
                dropdown.innerHTML = '<div class="px-3 py-2 text-slate-400">Không tìm thấy nhân viên</div>';
            } else {
                dropdown.innerHTML = items.slice(0, 8).map(function (e) {
                    const safeLabel = e.label.replace(/"/g, '&quot;');
                    return '<button type="button" data-id="' + e.id + '" data-label="' + safeLabel + '" ' +
                        'class="emp-combobox-item w-full text-left px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 dark:text-slate-200">' +
                        e.label + '</button>';
                }).join('');
            }
            dropdown.classList.remove('hidden');
        }

        document.querySelectorAll('[data-employee-combobox]').forEach(function (root) {
            const input    = root.querySelector('.emp-combobox-input');
            const hidden   = root.querySelector('.emp-combobox-value');
            const clearBtn = root.querySelector('.emp-combobox-clear');
            const dataEl   = root.querySelector('.emp-combobox-data');
            const data     = JSON.parse(dataEl.textContent || '[]');

            // Nếu combobox nằm trong cùng 1 <form> với select chi nhánh/đội nhóm, đổi chi nhánh/đội nhóm
            // sẽ tự lọc nhanh danh sách gợi ý nhân viên (không cần submit lại trang).
            const form         = root.closest('form');
            const branchSelect = form ? form.querySelector('select[name="branch_id"]') : null;
            const teamSelect   = form ? form.querySelector('select[name="team_id"]') : null;

            function scopedData() {
                return data.filter(function (e) {
                    if (branchSelect && branchSelect.value && String(e.branch_id) !== branchSelect.value) return false;
                    if (teamSelect && teamSelect.value && String(e.team_id) !== teamSelect.value) return false;
                    return true;
                });
            }

            input.addEventListener('input', function () {
                const q = input.value.trim().toLowerCase();
                hidden.value = '';
                clearBtn.classList.toggle('hidden', input.value === '');
                if (!q) { closeDropdown(root); return; }
                renderList(root, scopedData().filter(function (e) { return e.label.toLowerCase().includes(q); }));
            });

            input.addEventListener('focus', function () {
                if (!hidden.value) {
                    const q = input.value.trim().toLowerCase();
                    renderList(root, q ? scopedData().filter(e => e.label.toLowerCase().includes(q)) : scopedData());
                }
            });

            root.querySelector('.emp-combobox-dropdown').addEventListener('click', function (e) {
                const item = e.target.closest('.emp-combobox-item');
                if (!item) return;
                hidden.value = item.dataset.id;
                input.value = item.dataset.label;
                closeDropdown(root);
                clearBtn.classList.remove('hidden');
                hidden.dispatchEvent(new Event('change'));
            });

            clearBtn.addEventListener('click', function () {
                hidden.value = '';
                input.value = '';
                clearBtn.classList.add('hidden');
                closeDropdown(root);
                input.focus();
                hidden.dispatchEvent(new Event('change'));
            });

            document.addEventListener('click', function (e) {
                if (!root.contains(e.target)) closeDropdown(root);
            });

            // Đổi chi nhánh/đội nhóm sau khi đã chọn nhân viên: nếu nhân viên đang chọn không còn
            // thuộc phạm vi mới thì xoá lựa chọn để tránh gửi employee_id không khớp bộ lọc.
            [branchSelect, teamSelect].forEach(function (sel) {
                if (!sel) return;
                sel.addEventListener('change', function () {
                    if (!hidden.value) return;
                    const stillValid = scopedData().some(function (e) { return String(e.id) === hidden.value; });
                    if (!stillValid) {
                        hidden.value = '';
                        input.value = '';
                        clearBtn.classList.add('hidden');
                    }
                });
            });
        });
    });
    </script>
    @endpush
@endonce
