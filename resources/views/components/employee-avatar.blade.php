{{--
    Avatar nhân viên dùng chung: hiện ảnh đại diện (users.avatar) nếu nhân viên đã cài, ngược lại hiện chữ cái đầu.
    Truyền :employee (nên eager-load 'user') hoặc :user; :name để ghi đè tên dùng cho chữ cái đầu.
    size/text/shape/fallback là class Tailwind; class truyền thêm sẽ gộp vào cả <img> lẫn <span>.
--}}
@props([
    'employee' => null,
    'user' => null,
    'name' => null,
    'size' => 'w-8 h-8',
    'text' => 'text-xs',
    'shape' => 'rounded-full',
    'initials' => 1,
    'fallback' => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
])
@php
    $avatarPath = ($user ?? $employee?->user)?->avatar;
    $displayName = $name ?? $employee?->name ?? $user?->name ?? '';
    $initialsText = mb_strtoupper(mb_substr(trim($displayName) !== '' ? trim($displayName) : 'N', 0, (int) $initials));
@endphp
@if ($avatarPath)
    <img src="{{ asset($avatarPath) }}" alt="{{ $displayName }}" loading="lazy"
         {{ $attributes->merge(['class' => "$size $shape object-cover shrink-0"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$size $shape $fallback $text flex items-center justify-center font-bold shrink-0"]) }} aria-hidden="true">{{ $initialsText }}</span>
@endif
