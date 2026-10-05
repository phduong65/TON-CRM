/**
 * Bộ chọn quyền dùng chung cho modal Vai trò và mục "Quyền riêng" của modal Người dùng
 * (markup: resources/views/components/permission-picker.blade.php).
 *
 * Event delegation trên document → không cần khởi tạo từng modal. API cho script trang:
 *   PermPicker.set(root, ['view-x', ...])        — tick đúng danh sách quyền
 *   PermPicker.lockViaRole(root, ['view-x', ...]) — đánh dấu + khoá quyền đã có qua vai trò
 *   PermPicker.refresh(root)                     — cập nhật bộ đếm
 */
const q = (root, sel) => Array.from(root.querySelectorAll(sel));

function refresh(root) {
    if (!root) return;
    q(root, '[data-perm-group]').forEach((group) => {
        const boxes = q(group, '.perm-cb');
        const checked = boxes.filter((cb) => cb.checked).length;
        const counter = group.querySelector('[data-group-count]');
        if (counter) counter.textContent = `${checked}/${boxes.length}`;
        const toggle = group.querySelector('[data-group-toggle]');
        if (toggle) {
            const selectable = boxes.filter((cb) => !cb.disabled);
            const allOn = selectable.length > 0 && selectable.every((cb) => cb.checked);
            toggle.textContent = allOn ? 'Bỏ nhóm' : 'Chọn nhóm';
            toggle.setAttribute('aria-pressed', allOn ? 'true' : 'false');
        }
    });
    // Tổng: chỉ đếm quyền sẽ được gửi đi (ô bị khoá vì đã có qua vai trò không tính)
    const total = q(root, '.perm-cb').filter((cb) => cb.checked && !cb.disabled).length;
    const form = root.closest('form') || root;
    q(form, '[data-perm-total]').forEach((el) => { el.textContent = total; });
}

function setChecked(boxes, value) {
    boxes.filter((cb) => !cb.disabled).forEach((cb) => { cb.checked = value; });
}

function set(root, perms) {
    if (!root) return;
    const wanted = new Set(perms || []);
    q(root, '.perm-cb').forEach((cb) => {
        if (!cb.disabled) cb.checked = wanted.has(cb.value);
    });
    refresh(root);
}

function lockViaRole(root, perms) {
    if (!root) return;
    const viaRole = new Set(perms || []);
    q(root, '[data-perm-item]').forEach((item) => {
        const cb = item.querySelector('.perm-cb');
        const locked = viaRole.has(cb.value);
        if (locked) {
            // giữ lựa chọn riêng để khôi phục nếu đổi sang vai trò không có quyền này
            if (!cb.disabled) cb.dataset.directChecked = cb.checked ? '1' : '0';
            cb.checked = true;
            cb.disabled = true;
        } else if (cb.disabled) {
            cb.disabled = false;
            cb.checked = cb.dataset.directChecked === '1';
        }
        item.classList.toggle('is-via-role', locked);
    });
    refresh(root);
}

function applySearch(root, term) {
    const needle = term.trim().toLowerCase();
    let visible = 0;
    q(root, '[data-perm-group]').forEach((group) => {
        let groupVisible = 0;
        q(group, '[data-perm-item]').forEach((item) => {
            const match = !needle || (item.dataset.search || '').includes(needle);
            item.hidden = !match;
            if (match) groupVisible++;
        });
        group.hidden = groupVisible === 0;
        visible += groupVisible;
    });
    const empty = root.querySelector('[data-perm-empty]');
    if (empty) empty.hidden = visible > 0;
}

document.addEventListener('change', (e) => {
    if (e.target.matches('[data-perm-picker] .perm-cb')) {
        refresh(e.target.closest('[data-perm-picker]'));
    }
});

document.addEventListener('input', (e) => {
    if (e.target.matches('[data-perm-picker] [data-perm-search]')) {
        applySearch(e.target.closest('[data-perm-picker]'), e.target.value);
    }
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-perm-all], [data-perm-none], [data-group-toggle]');
    if (!btn) return;
    const root = btn.closest('[data-perm-picker]');
    if (!root) return;
    if (btn.hasAttribute('data-group-toggle')) {
        const boxes = q(btn.closest('[data-perm-group]'), '.perm-cb');
        const allOn = boxes.filter((cb) => !cb.disabled).every((cb) => cb.checked);
        setChecked(boxes, !allOn);
    } else {
        // chỉ tác động các quyền đang hiển thị theo ô tìm kiếm
        setChecked(q(root, '[data-perm-item]:not([hidden]) .perm-cb'), btn.hasAttribute('data-perm-all'));
    }
    refresh(root);
});

function refreshAll() {
    document.querySelectorAll('[data-perm-picker]').forEach(refresh);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refreshAll);
} else {
    refreshAll();
}

window.PermPicker = { set, lockViaRole, refresh };
