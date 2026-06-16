@extends('layouts.admin')

@section('title', 'System Logs')
@section('page-title', 'System Logs')
@section('breadcrumb', 'Hệ thống / System Logs')

@php
$levelConfig = [
    'emergency' => ['bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',    'EMERGENCY'],
    'alert'     => ['bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',    'ALERT'],
    'critical'  => ['bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',    'CRITICAL'],
    'error'     => ['bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400','ERROR'],
    'warning'   => ['bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400','WARNING'],
    'notice'    => ['bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400','NOTICE'],
    'info'      => ['bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',    'INFO'],
    'debug'     => ['bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400','DEBUG'],
];
@endphp

@section('content')
<div class="space-y-4">

    {{-- Filter bar --}}
    <div class="card">
        <div class="px-4 py-3">
            <form action="{{ route('log-viewer.index') }}" method="GET"
                  class="flex flex-wrap gap-2 items-end">

                {{-- File selector --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">File log</label>
                    <select name="file" class="form-input h-9 text-sm">
                        @foreach($files as $file)
                            <option value="{{ $file }}" @selected($file === $selectedFile)>{{ $file }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Level --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Level</label>
                    <select name="level" class="form-input h-9 text-sm">
                        <option value="">Tất cả level</option>
                        @foreach(['emergency','alert','critical','error','warning','notice','info','debug'] as $lvl)
                            <option value="{{ $lvl }}" @selected($levelFilter === $lvl)>
                                {{ strtoupper($lvl) }}
                                @if($levelCounts->has($lvl)) ({{ $levelCounts[$lvl] }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Search --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ $search }}"
                           class="form-input h-9 text-sm w-56" placeholder="Tìm trong message...">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if($levelFilter || $search)
                    <a href="{{ route('log-viewer.index', ['file' => $selectedFile]) }}"
                       class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                        <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                    </a>
                    @endif
                </div>

                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">
                        {{ number_format($total) }} dòng log
                        @if($truncated)
                            <span class="text-amber-500">(file lớn — chỉ hiển thị 10MB cuối)</span>
                        @endif
                    </p>
                </div>
            </form>
        </div>

        {{-- Level summary badges --}}
        @if($levelCounts->isNotEmpty() && !$levelFilter && !$search)
        <div class="px-4 pb-3 flex flex-wrap gap-1.5 border-t border-slate-100 dark:border-slate-700 pt-3">
            @foreach($levelCounts->sortKeys() as $lvl => $cnt)
            @php [$cls] = $levelConfig[$lvl] ?? ['bg-slate-100 text-slate-600']; @endphp
            <a href="{{ route('log-viewer.index', ['file' => $selectedFile, 'level' => $lvl]) }}"
               class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $cls }} hover:opacity-80 transition-opacity">
                {{ strtoupper($lvl) }} <span class="opacity-75">{{ $cnt }}</span>
            </a>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Log table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th w-36">Thời gian</th>
                            <th class="table-th w-24">Level</th>
                            <th class="table-th">Message</th>
                            <th class="table-th w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $i => $entry)
                        @php
                            [$badgeCls, $badgeLabel] = $levelConfig[$entry['level']] ?? ['bg-slate-100 text-slate-600', strtoupper($entry['level'])];
                            $hasExtra = trim($entry['extra']) !== '';
                            $rowId = 'log-extra-' . $i;
                        @endphp
                        <tr class="table-tr-hover align-top cursor-pointer"
                            @if($hasExtra) onclick="toggleLogExtra('{{ $rowId }}')" @endif>
                            <td class="table-td text-xs text-slate-500 whitespace-nowrap">
                                <span class="block font-mono">{{ substr($entry['datetime'], 0, 10) }}</span>
                                <span class="text-slate-400 font-mono">{{ substr($entry['datetime'], 11) }}</span>
                            </td>
                            <td class="table-td">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $badgeCls }}">
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td class="table-td">
                                <p class="text-xs font-mono text-slate-700 dark:text-slate-300 leading-snug break-all line-clamp-2">
                                    {{ $entry['message'] }}
                                </p>
                            </td>
                            <td class="table-td text-center">
                                @if($hasExtra)
                                <i id="{{ $rowId }}-icon" class="bi bi-chevron-down text-slate-400 text-xs transition-transform"></i>
                                @endif
                            </td>
                        </tr>
                        @if($hasExtra)
                        <tr id="{{ $rowId }}" class="hidden">
                            <td colspan="4" class="px-4 pb-3 pt-0 bg-slate-50 dark:bg-slate-800/50">
                                <pre class="text-xs text-slate-600 dark:text-slate-400 font-mono whitespace-pre-wrap break-all leading-snug max-h-72 overflow-y-auto bg-slate-900 dark:bg-slate-950 text-green-400 dark:text-green-300 p-3 rounded-lg">{{ trim($entry['extra']) }}</pre>
                            </td>
                        </tr>
                        @endif
                        @empty
                        <tr>
                            <td colspan="4" class="table-td text-center py-12 text-slate-400">
                                <i class="bi bi-terminal text-4xl mb-3 block"></i>
                                <p class="text-sm">Không có log entry nào</p>
                                @if($levelFilter || $search)
                                <p class="text-xs mt-1">Thử thay đổi bộ lọc</p>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($entries->hasPages())
        <div class="card-footer">
            {{ $entries->links() }}
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
function toggleLogExtra(id) {
    const row  = document.getElementById(id);
    const icon = document.getElementById(id + '-icon');
    if (!row) return;
    const hidden = row.classList.toggle('hidden');
    if (icon) icon.style.transform = hidden ? '' : 'rotate(180deg)';
}
</script>
@endpush
