@extends('layouts.admin')

@section('title', 'Google Sheets Sync')
@section('page-title', 'Google Sheets Sync')
@section('breadcrumb', 'Hệ thống / Google Sheets')

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- Trạng thái kết nối --}}
    <div class="card">
        <div class="card-header">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="ph-google-logo text-green-500"></i> Cấu hình kết nối
            </h3>
        </div>
        <div class="card-body space-y-3 text-sm">
            <div class="flex items-center gap-3">
                <span class="text-slate-500 dark:text-slate-400 w-44">Google Sheet ID:</span>
                @if($sheetId)
                    <code class="text-xs bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded text-slate-700 dark:text-slate-300 break-all">{{ $sheetId }}</code>
                    <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 text-xs font-medium"><i class="ph-check-circle"></i> Đã cấu hình</span>
                @else
                    <span class="text-red-500 text-xs"><i class="ph-x-circle"></i> Chưa cấu hình — thêm GOOGLE_SHEET_ID vào .env</span>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <span class="text-slate-500 dark:text-slate-400 w-44">Credentials file:</span>
                <code class="text-xs bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded text-slate-700 dark:text-slate-300">{{ $credPath }}</code>
                @if($credExists)
                    <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 text-xs font-medium"><i class="ph-check-circle"></i> Tồn tại</span>
                @else
                    <span class="text-red-500 text-xs"><i class="ph-x-circle"></i> Không tìm thấy file</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-4 text-sm text-green-700 dark:text-green-300 flex items-start gap-3">
            <i class="ph-check-circle text-lg mt-0.5 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 text-sm text-red-700 dark:text-red-300 flex items-start gap-3">
            <i class="ph-warning-circle text-lg mt-0.5 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Cấu trúc 4 tab --}}
    <div class="card">
        <div class="card-header">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="ph-table text-pcrm-500"></i> Cấu trúc Google Sheet (4 tab)
            </h3>
        </div>
        <div class="card-body">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="text-left py-2 pr-4 text-slate-500 dark:text-slate-400 font-medium">Tab</th>
                            <th class="text-left py-2 text-slate-500 dark:text-slate-400 font-medium">Các cột</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr>
                            <td class="py-2 pr-4 font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">📋 Quy Chế</td>
                            <td class="py-2 text-slate-500 dark:text-slate-400 text-xs">ID · Tên Quy Chế · Mô Tả · Ngày Hiệu Lực · Trạng Thái</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-4 font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">⚠️ Vi Phạm</td>
                            <td class="py-2 text-slate-500 dark:text-slate-400 text-xs">ID · Tên Vi Phạm · Mô Tả · Mức Độ · <span class="text-pcrm-600 dark:text-pcrm-400 font-medium">Quy Chế (ID - Tên)</span> · Loại Phạt · Điểm Trừ · Tiền Trừ · Trạng Thái</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-4 font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">🏆 Danh Mục Thưởng</td>
                            <td class="py-2 text-slate-500 dark:text-slate-400 text-xs">ID · Tên Danh Mục · Mô Tả · Trạng Thái</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-4 font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">🎁 Loại Thưởng</td>
                            <td class="py-2 text-slate-500 dark:text-slate-400 text-xs">ID · <span class="text-pcrm-600 dark:text-pcrm-400 font-medium">Danh Mục (ID - Tên)</span> · Tên Loại Thưởng · Mô Tả · Điểm Thưởng Mặc Định · Trạng Thái</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 space-y-1 text-xs text-slate-400 dark:text-slate-500">
                <p><i class="ph-info"></i> Cột <strong>ID</strong>: để trống khi thêm mới. Có ID → cập nhật bản ghi. Không có ID → tạo mới.</p>
                <p><i class="ph-info"></i> Cột <strong>Quy Chế / Danh Mục</strong>: hệ thống đẩy lên theo dạng <code class="bg-slate-100 dark:bg-slate-700 px-1 rounded">1 - Tên danh mục</code>. Khi import chấp nhận: dạng <code class="bg-slate-100 dark:bg-slate-700 px-1 rounded">ID - Tên</code>, chỉ số ID, hoặc chỉ tên danh mục.</p>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Push DB → Sheet --}}
        <div class="card">
            <div class="card-body text-center space-y-3">
                <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto">
                    <i class="ph-upload-simple text-blue-500 text-2xl"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-slate-800 dark:text-white">Đẩy lên Sheet</h4>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Ghi toàn bộ dữ liệu hiện có trên web lên Google Sheet (ghi đè)</p>
                </div>
                <form method="POST" action="{{ route('google-sheets.push') }}"
                      onsubmit="return confirm('Thao tác này sẽ ghi đè dữ liệu hiện có trên Google Sheet. Tiếp tục?')">
                    @csrf
                    <button type="submit"
                            class="btn btn-primary w-full"
                            @if(!$sheetId || !$credExists) disabled title="Cần cấu hình đủ trước" @endif>
                        <i class="ph-upload-simple"></i> Đẩy lên Sheet
                    </button>
                </form>
            </div>
        </div>

        {{-- Import Sheet → DB --}}
        <div class="card">
            <div class="card-body text-center space-y-3">
                <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mx-auto">
                    <i class="ph-download-simple text-green-500 text-2xl"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-slate-800 dark:text-white">Import từ Sheet</h4>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Đọc dữ liệu từ Google Sheet và đồng bộ vào database (thêm mới + cập nhật)</p>
                </div>
                <form method="POST" action="{{ route('google-sheets.import') }}"
                      onsubmit="return confirm('Import sẽ tạo mới và cập nhật dữ liệu từ Sheet vào database. Tiếp tục?')">
                    @csrf
                    <button type="submit"
                            class="btn btn-success w-full"
                            @if(!$sheetId || !$credExists) disabled title="Cần cấu hình đủ trước" @endif>
                        <i class="ph-download-simple"></i> Import từ Sheet
                    </button>
                </form>
            </div>
        </div>

    </div>

    {{-- Hướng dẫn --}}
    <div class="card">
        <div class="card-header">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="ph-book-open text-slate-400"></i> Hướng dẫn sử dụng
            </h3>
        </div>
        <div class="card-body text-sm text-slate-600 dark:text-slate-400 space-y-3">
            <div class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs font-bold">1</span>
                <p>Nhấn <strong>Đẩy lên Sheet</strong> lần đầu để tạo cấu trúc 4 tab và điền dữ liệu hiện có từ web.</p>
            </div>
            <div class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs font-bold">2</span>
                <p>Mở Google Sheet, thêm/sửa dữ liệu vào các tab. Để trống cột <strong>ID</strong> khi muốn tạo mới. Điền ID khi muốn cập nhật bản ghi hiện có.</p>
            </div>
            <div class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center text-xs font-bold">3</span>
                <p>Nhấn <strong>Import từ Sheet</strong> để đồng bộ dữ liệu từ Sheet vào web.</p>
            </div>
            <div class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 flex items-center justify-center text-xs font-bold">!</span>
                <p>Sau khi Import, nhớ share Google Sheet với email: <code class="text-xs bg-slate-100 dark:bg-slate-700 px-1 rounded">ton-hr@ton-hr.iam.gserviceaccount.com</code> (quyền Editor).</p>
            </div>
        </div>
    </div>

</div>
@endsection
