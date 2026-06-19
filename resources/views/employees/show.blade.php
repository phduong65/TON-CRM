@extends('layouts.admin')

@section('title', $employee->name)
@section('page-title', $employee->name)
@section('breadcrumb', 'Nhân viên / Chi tiết')

@section('content')
    @if(session('new_account'))
    @php $acc = session('new_account'); @endphp
    <div id="newAccountBanner" class="mb-6 rounded-xl border-2 border-emerald-400 dark:border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-3 bg-emerald-400/20 dark:bg-emerald-500/10 border-b border-emerald-300 dark:border-emerald-600">
            <i class="bi bi-shield-check text-emerald-600 dark:text-emerald-400 text-lg"></i>
            <span class="font-semibold text-emerald-800 dark:text-emerald-300">Tài khoản đã được tạo thành công!</span>
            <button onclick="document.getElementById('newAccountBanner').remove()"
                    class="ml-auto w-7 h-7 flex items-center justify-center rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-200 dark:hover:bg-emerald-700 transition">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <div class="px-5 py-4 space-y-3">
            <p class="text-sm text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-amber-500"></i>
                Ghi lại thông tin này ngay — mật khẩu <strong>chỉ hiển thị một lần</strong>.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="rounded-lg bg-white dark:bg-slate-800 border border-emerald-200 dark:border-slate-600 px-4 py-3 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Nhân viên</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-white">{{ $acc['name'] }}</p>
                    <p class="text-xs text-slate-500 font-mono">Mã: {{ $acc['code'] }}</p>
                </div>
                <div class="rounded-lg bg-white dark:bg-slate-800 border border-emerald-200 dark:border-slate-600 px-4 py-3 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Thông tin đăng nhập</p>
                    <div class="flex items-center gap-2">
                        <i class="bi bi-envelope text-slate-400 text-xs"></i>
                        <span class="text-sm font-mono text-slate-800 dark:text-white select-all">{{ $acc['email'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="bi bi-key text-slate-400 text-xs"></i>
                        <span id="newAccPassword" class="text-sm font-mono font-bold text-pcrm-600 dark:text-pcrm-400 select-all tracking-widest">{{ $acc['password'] }}</span>
                        <button onclick="navigator.clipboard.writeText('{{ $acc['password'] }}').then(()=>{this.innerHTML='<i class=\'bi bi-check2\'></i>';setTimeout(()=>{this.innerHTML='<i class=\'bi bi-clipboard\'></i>'},1500)})"
                                class="ml-1 p-1 rounded text-slate-400 hover:text-pcrm-600 dark:hover:text-pcrm-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="Sao chép mật khẩu">
                            <i class="bi bi-clipboard text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="lg:col-span-1">
            <div class="card text-center">
                <div class="card-body">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center">
                        <span class="text-2xl font-bold text-pcrm-700 dark:text-pcrm-400">
                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $employee->name }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $employee->position ?? 'Chưa có chức vụ' }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Mã: {{ $employee->code ?? '—' }}</p>

                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-slate-500">Chi nhánh</span>
                            <span class="font-medium">{{ $employee->branch->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-slate-500">Đội nhóm</span>
                            <span class="font-medium">{{ $employee->team->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-slate-500">Email</span>
                            <span class="font-medium">{{ $employee->email ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Điện thoại</span>
                            <span class="font-medium">{{ $employee->phone ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if($canViewSensitive)
            <!-- Score Summary -->
            <div class="card mt-4">
                <div class="card-header">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Thông tin điểm</h4>
                </div>
                <div class="card-body space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Tổng điểm</span>
                        <span class="text-lg font-bold text-pcrm-600 dark:text-pcrm-400">{{ number_format($employee->total_score) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Số lần vi phạm</span>
                        <span class="text-lg font-bold text-redzone-500">{{ $employee->penalties->count() }}</span>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column -->
        <div class="lg:col-span-2 space-y-6">
            @if($canViewSensitive)
            <!-- Penalty History -->
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Lịch sử xử phạt</h4>
                    <a href="{{ route('employees.penalties', $employee) }}" class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        Xem tất cả <i class="ph-arrow-right text-xs"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($employee->penalties->count() > 0)
                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($employee->penalties->take(5) as $penalty)
                            <div class="flex items-center justify-between px-6 py-3">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $penalty->violation->name ?? 'N/A' }}</p>
                                    <p class="text-xs text-slate-400">{{ $penalty->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold text-red-600 dark:text-red-400">-{{ number_format($penalty->total_points_deducted) }}đ</span>
                                    @php
                                        $s = $penalty->status;
                                        $cls = $s === 'approved' ? 'badge-success' : ($s === 'rejected' ? 'badge-danger' : 'badge-warning');
                                        $lbl = $s === 'approved' ? 'Đã duyệt' : ($s === 'rejected' ? 'Từ chối' : 'Chờ duyệt');
                                    @endphp
                                    <span class="{{ $cls }}">{{ $lbl }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-6 text-center text-sm text-slate-400">
                            <i class="ph-check-circle text-2xl mb-2 block"></i>
                            <p>Chưa có vi phạm nào</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Reward History -->
            @php $recentRewards = \App\Models\Reward::with('rewardType')->where('employee_id', $employee->id)->where('status', 'approved')->latest()->take(5)->get(); @endphp
            @if($recentRewards->count() > 0)
            <div class="card">
                <div class="card-header flex justify-between">
                    <h4 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="bi bi-gift text-emerald-500 text-sm"></i> Lịch sử thưởng điểm
                    </h4>
                    @can('view-rewards')
                    <a href="{{ route('rewards.index', ['search' => $employee->code]) }}" class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        Xem tất cả
                    </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($recentRewards as $reward)
                        <div class="flex items-center justify-between px-6 py-3">
                            <div class="flex-1">
                                <a href="{{ route('rewards.show', $reward) }}" class="text-sm font-medium text-slate-900 dark:text-white hover:text-pcrm-600 dark:hover:text-pcrm-400">
                                    {{ $reward->rewardType?->name ?? 'Thưởng điểm' }}
                                </a>
                                <p class="text-xs text-slate-400">{{ $reward->code }} · {{ $reward->created_at->format('d/m/Y') }}</p>
                            </div>
                            <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                +{{ number_format($reward->total_points_awarded) }}đ
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Score History -->
            <div class="card">
                <div class="card-header">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Lịch sử điểm thưởng/phạt</h4>
                </div>
                <div class="card-body p-0">
                    @if($employee->scores->count() > 0)
                        <div class="table-container border-0 rounded-none">
                            <table class="table-base">
                                <thead>
                                    <tr>
                                        <th class="table-th">Ngày</th>
                                        <th class="table-th">Lý do</th>
                                        <th class="table-th text-right">Điểm</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employee->scores->sortByDesc('created_at') as $score)
                                    <tr class="table-tr-hover">
                                        <td class="table-td">{{ $score->created_at->format('d/m/Y') }}</td>
                                        <td class="table-td">{{ $score->reason }}</td>
                                        <td class="table-td text-right font-semibold {{ $score->points >= 0 ? 'text-pcrm-600' : 'text-red-600' }}">
                                            {{ $score->points >= 0 ? '+' : '' }}{{ number_format($score->points) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-6 text-center text-sm text-slate-400">
                            <i class="ph-coins text-2xl mb-2 block"></i>
                            <p>Chưa có ghi nhận điểm</p>
                        </div>
                    @endif
                </div>
            </div>
            @else
            <!-- Privacy notice for non-privileged viewers -->
            <div class="card">
                <div class="card-body py-12 text-center">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-lock text-2xl text-slate-400"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Thông tin điểm và lịch sử vi phạm được bảo mật</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Chỉ bản thân nhân viên hoặc quản lý mới có thể xem.</p>
                </div>
            </div>
            @endif
        </div>
    </div>
@endsection
