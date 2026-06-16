@extends('layouts.admin')

@section('title', 'Google Sheets Sync')
@section('page-title', 'Google Sheets Sync')
@section('breadcrumb', 'Hệ thống / Google Sheets')

@section('content')
<div class="space-y-5">

    {{-- Missing-config banner --}}
    @if(!$sheetId || !$credExists)
    <div class="rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/10 px-4 py-3 flex items-start gap-3">
        <i class="bi bi-exclamation-circle text-amber-500 text-lg mt-0.5 shrink-0"></i>
        <p class="text-sm text-amber-700 dark:text-amber-300/90 leading-relaxed">
            <strong>Cấu hình chưa đầy đủ —</strong>
            @if(!$sheetId)
                chưa có <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded font-mono text-xs">GOOGLE_SHEET_ID</code> trong <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded font-mono text-xs">.env</code>.
            @endif
            @if(!$credExists)
                không tìm thấy file credentials tại <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded font-mono text-xs">{{ $credPath }}</code>.
            @endif
            Đồng bộ bị vô hiệu hoá cho đến khi thiết lập xong.
        </p>
    </div>
    @endif

    {{-- Connection status --}}
    <div class="card">
        <div class="card-body py-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">

                {{-- Sheet ID --}}
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                        <i class="bi bi-key text-slate-500 dark:text-slate-400"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-400 dark:text-slate-500 mb-0.5">Sheet ID</p>
                        @if($sheetId)
                            <code class="text-xs text-slate-700 dark:text-slate-300 truncate block">{{ $sheetId }}</code>
                        @else
                            <span class="text-xs text-slate-400 dark:text-slate-500 italic">Chưa cấu hình</span>
                        @endif
                    </div>
                    @if($sheetId)
                        <span class="badge badge-success shrink-0"><i class="bi bi-check-circle mr-1"></i>OK</span>
                    @else
                        <span class="badge badge-danger shrink-0"><i class="bi bi-x-circle mr-1"></i>Thiếu</span>
                    @endif
                </div>

                <div class="hidden sm:block w-px h-8 bg-slate-200 dark:bg-slate-700 shrink-0"></div>

                {{-- Credentials --}}
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                        <i class="bi bi-file-lock text-slate-500 dark:text-slate-400"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-400 dark:text-slate-500 mb-0.5">Credentials</p>
                        <code class="text-xs text-slate-700 dark:text-slate-300 truncate block">{{ $credPath }}</code>
                    </div>
                    @if($credExists)
                        <span class="badge badge-success shrink-0"><i class="bi bi-check-circle mr-1"></i>OK</span>
                    @else
                        <span class="badge badge-danger shrink-0"><i class="bi bi-x-circle mr-1"></i>Không có</span>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- Sync actions --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Push: DB → Sheet --}}
        <div class="card @if(!$sheetId || !$credExists) opacity-60 @endif">
            <div class="card-body space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center shrink-0 mt-0.5">
                        <i class="bi bi-arrow-up-square text-blue-500 fs-5"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-slate-800 dark:text-white leading-tight">Đẩy lên Sheet</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 leading-relaxed">
                            Ghi toàn bộ dữ liệu từ database lên Google Sheet — ghi đè nội dung hiện có.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 py-2 px-3 rounded-lg bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-500 dark:text-slate-400">
                    <i class="bi bi-database text-slate-400 dark:text-slate-500"></i>
                    <span>Database</span>
                    <i class="bi bi-arrow-right text-blue-400 mx-0.5"></i>
                    <i class="bi bi-google text-green-500"></i>
                    <span>Google Sheet</span>
                </div>

                <form method="POST" action="{{ route('google-sheets.push') }}"
                      onsubmit="return confirm('Thao tác này sẽ ghi đè dữ liệu hiện có trên Google Sheet. Tiếp tục?')">
                    @csrf
                    <button type="submit" class="btn-secondary w-full justify-center"
                            @if(!$sheetId || !$credExists) disabled @endif>
                        <i class="bi bi-upload"></i> Đẩy lên Sheet
                    </button>
                </form>
            </div>
        </div>

        {{-- Import: Sheet → DB --}}
        <div class="card @if(!$sheetId || !$credExists) opacity-60 @endif">
            <div class="card-body space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/20 flex items-center justify-center shrink-0 mt-0.5">
                        <i class="bi bi-arrow-down-square text-pcrm-600 dark:text-pcrm-400 fs-5"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-slate-800 dark:text-white leading-tight">Import từ Sheet</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 leading-relaxed">
                            Đọc dữ liệu từ Google Sheet và đồng bộ vào database — thêm mới và cập nhật bản ghi.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 py-2 px-3 rounded-lg bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-500 dark:text-slate-400">
                    <i class="bi bi-google text-green-500"></i>
                    <span>Google Sheet</span>
                    <i class="bi bi-arrow-right text-pcrm-400 mx-0.5"></i>
                    <i class="bi bi-database text-slate-400 dark:text-slate-500"></i>
                    <span>Database</span>
                </div>

                <form method="POST" action="{{ route('google-sheets.import') }}"
                      onsubmit="return confirm('Import sẽ tạo mới và cập nhật dữ liệu từ Sheet vào database. Tiếp tục?')">
                    @csrf
                    <button type="submit" class="btn-primary w-full justify-center"
                            @if(!$sheetId || !$credExists) disabled @endif>
                        <i class="bi bi-download"></i> Import từ Sheet
                    </button>
                </form>
            </div>
        </div>

    </div>

    {{-- Sheet structure --}}
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2 text-sm">
                <i class="bi bi-table text-pcrm-500"></i> Cấu trúc Google Sheet
            </h3>
            <span class="badge badge-neutral">4 tab</span>
        </div>

        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th class="table-th w-36">Tab</th>
                        <th class="table-th">Các cột</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-tr-hover">
                        <td class="table-td font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">
                            📋 Quy Chế
                        </td>
                        <td class="table-td">
                            <div class="flex flex-wrap gap-1">
                                <span class="badge badge-neutral">ID</span>
                                <span class="badge badge-neutral">Tên Quy Chế</span>
                                <span class="badge badge-neutral">Mô Tả</span>
                                <span class="badge badge-neutral">Ngày Hiệu Lực</span>
                                <span class="badge badge-neutral">Trạng Thái</span>
                            </div>
                        </td>
                    </tr>
                    <tr class="table-tr-hover">
                        <td class="table-td font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">
                            ⚠️ Vi Phạm
                        </td>
                        <td class="table-td">
                            <div class="flex flex-wrap gap-1">
                                <span class="badge badge-neutral">ID</span>
                                <span class="badge badge-neutral">Tên Vi Phạm</span>
                                <span class="badge badge-neutral">Mô Tả</span>
                                <span class="badge badge-neutral">Mức Độ</span>
                                <span class="badge badge-info">Quy Chế (ID - Tên)</span>
                                <span class="badge badge-neutral">Loại Phạt</span>
                                <span class="badge badge-neutral">Điểm Trừ</span>
                                <span class="badge badge-neutral">Tiền Trừ</span>
                                <span class="badge badge-neutral">Trạng Thái</span>
                            </div>
                        </td>
                    </tr>
                    <tr class="table-tr-hover">
                        <td class="table-td font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">
                            🏆 Danh Mục Thưởng
                        </td>
                        <td class="table-td">
                            <div class="flex flex-wrap gap-1">
                                <span class="badge badge-neutral">ID</span>
                                <span class="badge badge-neutral">Tên Danh Mục</span>
                                <span class="badge badge-neutral">Mô Tả</span>
                                <span class="badge badge-neutral">Trạng Thái</span>
                            </div>
                        </td>
                    </tr>
                    <tr class="table-tr-hover">
                        <td class="table-td font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap" style="border-bottom: none;">
                            🎁 Loại Thưởng
                        </td>
                        <td class="table-td" style="border-bottom: none;">
                            <div class="flex flex-wrap gap-1">
                                <span class="badge badge-neutral">ID</span>
                                <span class="badge badge-info">Danh Mục (ID - Tên)</span>
                                <span class="badge badge-neutral">Tên Loại Thưởng</span>
                                <span class="badge badge-neutral">Mô Tả</span>
                                <span class="badge badge-neutral">Điểm Thưởng Mặc Định</span>
                                <span class="badge badge-neutral">Trạng Thái</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-3 border-t border-slate-100 dark:border-slate-700/60 space-y-1.5">
            <p class="text-xs text-slate-400 dark:text-slate-500 flex items-start gap-2">
                <i class="bi bi-info-circle shrink-0 mt-0.5"></i>
                <span>Cột <strong class="text-slate-500 dark:text-slate-400">ID</strong>: để trống khi thêm mới, điền ID khi muốn cập nhật bản ghi hiện có.</span>
            </p>
            <p class="text-xs text-slate-400 dark:text-slate-500 flex items-start gap-2">
                <i class="bi bi-info-circle shrink-0 mt-0.5"></i>
                <span>Cột quan hệ <span class="badge badge-info" style="font-size:0.7rem">Quy Chế</span> / <span class="badge badge-info" style="font-size:0.7rem">Danh Mục</span>: hệ thống đẩy dạng <code class="bg-slate-100 dark:bg-slate-700 px-1 rounded">1 - Tên</code>. Import chấp nhận dạng đó, chỉ ID, hoặc chỉ tên danh mục.</span>
            </p>
        </div>
    </div>

    {{-- Instructions --}}
    <div class="card">
        <div class="card-header">
            <h3 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2 text-sm">
                <i class="bi bi-book text-slate-400"></i> Hướng dẫn sử dụng
            </h3>
        </div>
        <div class="card-body">
            <ol class="space-y-4">
                <li class="flex gap-4 items-start">
                    <div class="flex-shrink-0 w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center text-xs font-bold tabular-nums">1</div>
                    <p class="pt-0.5 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Nhấn <strong class="text-slate-700 dark:text-slate-300">Đẩy lên Sheet</strong> lần đầu để tạo cấu trúc 4 tab và điền dữ liệu hiện có từ database lên Google Sheet.
                    </p>
                </li>
                <li class="flex gap-4 items-start">
                    <div class="flex-shrink-0 w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center text-xs font-bold tabular-nums">2</div>
                    <p class="pt-0.5 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Mở Google Sheet, thêm hoặc sửa dữ liệu. <strong class="text-slate-700 dark:text-slate-300">Để trống cột ID</strong> khi muốn tạo bản ghi mới. <strong class="text-slate-700 dark:text-slate-300">Điền ID</strong> khi muốn cập nhật bản ghi hiện có.
                    </p>
                </li>
                <li class="flex gap-4 items-start">
                    <div class="flex-shrink-0 w-7 h-7 rounded-full bg-pcrm-100 dark:bg-pcrm-900/30 text-pcrm-700 dark:text-pcrm-400 flex items-center justify-center text-xs font-bold tabular-nums">3</div>
                    <p class="pt-0.5 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Nhấn <strong class="text-slate-700 dark:text-slate-300">Import từ Sheet</strong> để đồng bộ dữ liệu từ Google Sheet về database.
                    </p>
                </li>
                <li class="flex gap-4 items-start">
                    <div class="flex-shrink-0 w-7 h-7 rounded-full bg-amber-100 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <i class="bi bi-exclamation-triangle" style="font-size:0.7rem"></i>
                    </div>
                    <p class="pt-0.5 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Nhớ share Google Sheet cho service account email
                        <code class="text-xs bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded text-slate-600 dark:text-slate-300">ton-hr@ton-hr.iam.gserviceaccount.com</code>
                        với quyền <strong class="text-slate-700 dark:text-slate-300">Editor</strong>.
                    </p>
                </li>
            </ol>
        </div>
    </div>

</div>
@endsection
