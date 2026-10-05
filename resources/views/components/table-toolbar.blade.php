{{--
    Toolbar ngay trên bảng dữ liệu: số kết quả bên trái, các nút phụ (Bộ lọc, Xuất Excel, thao tác
    hàng loạt…) bên phải. Nút chính (CTA tạo mới) vẫn ở header trang qua @section('page-actions').

    Đặt làm phần tử đầu tiên trong .card chứa bảng (có đường kẻ dưới), hoặc đứng riêng ngay trên card
    (tự bỏ viền, xem app.css ".table-toolbar").

    Props:
      - paginator: LengthAwarePaginator|Collection|null — hiển thị "N kết quả" nếu đếm được
      - count:     int|null — số kết quả tự truyền (ưu tiên hơn paginator)
      - label:     đơn vị đếm, mặc định "kết quả"
    Slots:
      - default:   các nút phụ bên phải
      - info:      nội dung bổ sung bên trái (badge "Hôm nay", link "Xem tất cả"…)
--}}
@props(['paginator' => null, 'count' => null, 'label' => 'kết quả'])

@php
    $toolbarCount = $count;
    if ($toolbarCount === null && $paginator !== null) {
        $toolbarCount = method_exists($paginator, 'total') ? $paginator->total()
            : (is_countable($paginator) ? count($paginator) : null);
    }
@endphp

<div {{ $attributes->merge(['class' => 'table-toolbar']) }} role="toolbar" aria-label="Công cụ bảng dữ liệu">
    <div class="table-toolbar-info">
        @if ($toolbarCount !== null)
            <span><strong class="font-semibold text-slate-700 dark:text-slate-200 tabular-nums">{{ number_format($toolbarCount) }}</strong> {{ $label }}</span>
        @endif
        {{ $info ?? '' }}
    </div>
    @if (trim($slot) !== '')
        <div class="table-toolbar-actions">
            {{ $slot }}
        </div>
    @endif
</div>
