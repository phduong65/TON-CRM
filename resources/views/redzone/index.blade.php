@extends('layouts.admin')

@section('title', 'Redzone — Cảnh báo')
@section('page-title', 'Redzone')
@section('breadcrumb', 'Phân tích / Redzone')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Nhân viên có tổng điểm dưới ngưỡng {{ number_format($threshold) }} — cần theo dõi và hỗ trợ</p>
        </div>
    </div>

    @if($employees->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            @foreach($employees as $emp)
            <div class="card border-redzone-200 dark:border-redzone-800">
                <div class="card-body">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-redzone-50 dark:bg-redzone-900/30 flex items-center justify-center text-redzone-600 dark:text-redzone-400 font-bold text-sm">
                            {{ strtoupper(substr($emp->name, 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-slate-900 dark:text-white truncate">{{ $emp->name }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ $emp->team->name ?? 'Chưa có team' }}</p>
                        </div>
                        <span class="badge-danger font-bold text-sm">{{ number_format($emp->total_score ?? 0) }}đ</span>
                    </div>

                    <!-- Progress bar: how close to 0 -->
                    @php
                        $score = $emp->total_score ?? 0;
                        $pct = $threshold > 0 ? max(0, min(100, ($threshold - $score) / $threshold * 100)) : 0;
                    @endphp
                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2">
                        <div class="bg-redzone-500 rounded-full h-2 transition-all duration-500"
                             style="width: {{ $pct }}%"></div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">
                        Còn {{ number_format(max(0, $threshold - $score)) }}đ nữa là chạm ngưỡng
                        @if($score <= 0)
                            <span class="text-red-600 font-semibold">— ĐÃ VỀ 0!</span>
                        @endif
                    </p>
                </div>
                <div class="card-footer bg-redzone-50 dark:bg-redzone-900/10 px-5 py-3">
                    <a href="{{ route('employees.show', $emp) }}" class="text-sm text-redzone-600 dark:text-redzone-400 hover:underline flex items-center gap-1">
                        <i class="ph-eye"></i> Xem chi tiết
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        @if($employees->hasPages())
        <div class="card">
            <div class="card-footer">
                {{ $employees->links() }}
            </div>
        </div>
        @endif
    @else
        <div class="card">
            <div class="card-body text-center py-12">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                    <i class="ph-smiley text-4xl text-emerald-500"></i>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">Không có ai trong Redzone!</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Tất cả nhân viên đều có điểm trên ngưỡng {{ number_format($threshold) }}.
                </p>
            </div>
        </div>
    @endif
@endsection
