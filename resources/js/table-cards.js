/**
 * Bảng → danh sách thẻ trên mobile (< 640px).
 *
 * Chỉ là progressive enhancement cho phần hiển thị: script gắn nhãn cột (lấy từ <thead>) vào
 * từng ô qua `data-label`, rồi CSS trong app.css (`table[data-mcards]`) xếp mỗi hàng thành 1 thẻ.
 * Không có JS thì bảng vẫn hiển thị như cũ (cuộn ngang trong .table-container).
 *
 * Tuỳ chỉnh trong Blade:
 *   <table data-no-cards>      — giữ nguyên dạng bảng (lưới lịch, bảng trong modal…)
 *   <th data-mcard-title>      — cột dùng làm tiêu đề thẻ (mặc định: cột nội dung đầu tiên)
 */
const SKIP_TITLE = ['', '#', 'stt', 'tn'];
const ACTION_LABELS = ['thao tác', 'hành động'];

function headerLabels(table) {
    const rows = table.tHead ? table.tHead.rows : [];
    if (!rows.length) return null;
    const cols = [];
    Array.from(rows[rows.length - 1].cells).forEach((th) => {
        const span = th.colSpan || 1;
        const label = th.textContent.replace(/\s+/g, ' ').trim();
        const hasCheckbox = !!th.querySelector('input[type="checkbox"]');
        for (let i = 0; i < span; i++) cols.push({ th, label, hasCheckbox });
    });
    return cols;
}

function roleFor(col, index, cols) {
    const lower = col.label.toLowerCase();
    if (col.th.hasAttribute('data-mcard-title')) return 'title';
    if (ACTION_LABELS.includes(lower)) return 'actions';
    if (col.hasCheckbox || (lower === '' && index === 0)) return 'select';
    if (lower === '' && index === cols.length - 1) return 'actions';
    if (lower === '#' || lower === 'stt') return 'index';
    return 'field';
}

export function enhanceTable(table) {
    if (table.hasAttribute('data-no-cards') || table.dataset.mcards === 'ready') return;
    const cols = headerLabels(table);
    if (!cols || !cols.length) return;

    const roles = cols.map((col, i) => roleFor(col, i, cols));
    if (!roles.includes('title')) {
        const first = cols.findIndex((c, i) => roles[i] === 'field' && !SKIP_TITLE.includes(c.label.toLowerCase()));
        if (first >= 0) roles[first] = 'title';
    }

    Array.from(table.tBodies).forEach((tbody) => {
        Array.from(tbody.rows).forEach((tr) => {
            let col = 0;
            Array.from(tr.cells).forEach((td) => {
                const span = td.colSpan || 1;
                if (span >= cols.length) {
                    td.dataset.mcardRole = 'full';
                } else if (cols[col]) {
                    td.dataset.label = cols[col].label;
                    td.dataset.mcardRole = roles[col];
                }
                col += span;
            });
        });
    });

    table.dataset.mcards = 'ready';
}

export function enhanceTables(root = document) {
    root.querySelectorAll('.pcrm-page-content table').forEach(enhanceTable);
}

window.pcrmTableCards = enhanceTables;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => enhanceTables());
} else {
    enhanceTables();
}
