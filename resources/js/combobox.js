// ── Searchable combobox ────────────────────────────────────────────────────
// Progressively enhances any <select data-combobox> into a searchable
// combobox (type to filter, click/keyboard to select) while keeping the
// original <select> in the DOM so existing name/required/onchange/filter
// logic (branch/team cascading via option.hidden) keeps working unchanged.
//
// Usage: <select name="x" data-combobox data-combobox-placeholder="Tìm...">
// Call window.comboboxRefresh(select) after programmatically changing which
// <option> elements are hidden/disabled (e.g. branch/team filters) so the
// visible text stays in sync.

(function () {
    function visibleOptions(select) {
        return Array.from(select.options).filter(function (o) {
            return o.value && !o.hidden && !o.disabled;
        });
    }

    function currentLabel(select) {
        var opt = select.options[select.selectedIndex];
        return opt && opt.value ? opt.textContent.trim() : '';
    }

    function closeDropdown(state) {
        state.dropdown.classList.add('hidden');
        state.activeIndex = -1;
    }

    function renderDropdown(state, filterText) {
        var select = state.select;
        var dropdown = state.dropdown;
        var f = (filterText || '').toLowerCase().trim();

        var opts = visibleOptions(select).filter(function (o) {
            return !f || o.textContent.toLowerCase().indexOf(f) !== -1;
        });

        dropdown.innerHTML = '';
        state.activeIndex = -1;

        if (!opts.length) {
            var empty = document.createElement('div');
            empty.className = 'combobox-empty';
            empty.textContent = 'Không tìm thấy kết quả';
            dropdown.appendChild(empty);
        } else {
            opts.forEach(function (opt) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'combobox-item';
                item.textContent = opt.textContent.trim();
                item.dataset.value = opt.value;
                item.onclick = function () {
                    select.value = opt.value;
                    state.input.value = opt.textContent.trim();
                    closeDropdown(state);
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                };
                dropdown.appendChild(item);
            });
        }

        dropdown.classList.remove('hidden');
    }

    function moveActive(state, dir) {
        var items = state.dropdown.querySelectorAll('.combobox-item');
        if (!items.length) return;
        items.forEach(function (el) { el.classList.remove('is-active'); });
        state.activeIndex = (state.activeIndex + dir + items.length) % items.length;
        var el = items[state.activeIndex];
        el.classList.add('is-active');
        el.scrollIntoView({ block: 'nearest' });
    }

    function initOne(select) {
        if (select.dataset.comboboxInit) return;
        select.dataset.comboboxInit = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'combobox-wrapper';
        select.parentNode.insertBefore(wrapper, select);

        select.classList.add('combobox-native');
        wrapper.appendChild(select);

        var input = document.createElement('input');
        input.type = 'text';
        input.className = select.className.replace('combobox-native', '').trim() + ' combobox-input';
        input.placeholder = select.dataset.comboboxPlaceholder || 'Gõ để tìm kiếm...';
        input.autocomplete = 'off';
        input.value = currentLabel(select);
        wrapper.appendChild(input);

        var chevron = document.createElement('i');
        chevron.className = 'bi bi-chevron-down combobox-chevron';
        wrapper.appendChild(chevron);

        var dropdown = document.createElement('div');
        dropdown.className = 'combobox-dropdown hidden';
        wrapper.appendChild(dropdown);

        var state = { select: select, input: input, dropdown: dropdown, activeIndex: -1 };
        select._comboboxState = state;

        input.addEventListener('focus', function () { renderDropdown(state, ''); });
        input.addEventListener('input', function () {
            renderDropdown(state, input.value);
            if (!input.value) {
                select.value = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        input.addEventListener('keydown', function (e) {
            if (dropdown.classList.contains('hidden') && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
                renderDropdown(state, input.value);
                return;
            }
            if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(state, 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(state, -1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                var active = dropdown.querySelector('.combobox-item.is-active') || dropdown.querySelector('.combobox-item');
                if (active) active.click();
            } else if (e.key === 'Escape') {
                closeDropdown(state);
                input.blur();
            }
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) closeDropdown(state);
        });
    }

    window.comboboxRefresh = function (select) {
        if (select && select._comboboxState) {
            select._comboboxState.input.value = currentLabel(select);
        }
    };

    window.comboboxInit = function (root) {
        (root || document).querySelectorAll('select[data-combobox]').forEach(initOne);
    };

    document.addEventListener('DOMContentLoaded', function () { window.comboboxInit(); });

    // Re-scan whenever a modal is opened (dynamically-shown content / old() re-renders)
    var _origOpenModal = window.openModal;
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.openModal === 'function') {
            var original = window.openModal;
            window.openModal = function (id) {
                original(id);
                var el = document.getElementById(id);
                if (el) window.comboboxInit(el);
            };
        }
    });
})();
