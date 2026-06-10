@extends('layouts.admin')

@section('title', 'Bảng xếp hạng')
@section('page-title', 'Bảng xếp hạng')
@section('breadcrumb', 'Phân tích / Xếp hạng')

@section('content')
    <!-- SaaS header -->
    <div class="page-header">
        <div>
            <div class="inline-flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-pcrm-100 dark:bg-pcrm-900/50 text-pcrm-700 dark:text-pcrm-400">
                    <i class="bi bi-trophy text-lg"></i>
                </span>
                <h3 class="page-title">Bảng xếp hạng</h3>
            </div>
            <p class="page-subtitle">Cập nhật điểm thưởng theo thời gian — vui nhộn, gần gũi và dễ nhìn trên di động.</p>
        </div>

        <!-- Tab: Employee / Team (accessible) -->
        <div class="flex gap-2" role="tablist" aria-label="Chuyển đổi bảng xếp hạng">
            <button type="button" class="btn-primary" onclick="showTab('employees')" id="tab-employees-btn" role="tab" aria-selected="true" aria-controls="tab-employees">
                <i class="ph-users-three"></i>
                <span>Xếp hạng nhân viên</span>
            </button>
            <button type="button" class="btn-secondary" onclick="showTab('teams')" id="tab-teams-btn" role="tab" aria-selected="false" aria-controls="tab-teams">
                <i class="ph-user-squares"></i>
                <span>Xếp hạng đội nhóm</span>
            </button>
        </div>
    </div>


    <!-- Employee Rankings -->
    <div id="tab-employees" class="tab-content" role="tabpanel" aria-labelledby="tab-employees-btn">

        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-slate-900 dark:text-white">Xếp hạng theo điểm thưởng</h3>
            </div>
            <div class="card-body p-0">
                <!-- Desktop table -->
                <div class="table-container border-0 rounded-none hidden sm:block">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th class="table-th w-12">#</th>
                                <th class="table-th">Nhân viên</th>
                                <th class="table-th">Chi nhánh</th>
                                <th class="table-th">Đội nhóm</th>
                                <th class="table-th text-center">Tổng điểm</th>
                                {{-- <th class="table-th text-center">Huy hiệu</th> --}}
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $index => $emp)
                            <tr class="table-tr-hover {{ $index < 3 ? 'leaderboard-row-pop' : '' }}" style="animation-delay: {{ $index * 40 }}ms">
                                <td class="table-td">
                                    @if($index < 3)
                                        <span class="rank-badge rank-{{ $index + 1 }} leaderboard-medal" aria-label="Top {{ $index + 1 }}">
                                            @if($index === 0) <i class="bi bi-trophy"></i>
                                            @elseif($index === 1) <i class="bi bi-trophy"></i>
                                            @else <i class="bi bi-trophy"></i>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td class="table-td font-medium">
                                    <a href="{{ route('employees.show', $emp) }}" class="text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                        {{ $emp->name }}
                                    </a>
                                    @if($index < 3)
                                        <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Top {{ $index + 1 }}</div>
                                    @endif
                                </td>
                                <td class="table-td">{{ $emp->branch->name ?? '—' }}</td>
                                <td class="table-td">{{ $emp->team->name ?? '—' }}</td>
                                <td class="table-td text-center font-bold">{{ number_format($emp->total_score ?? 0) }}</td>
                                {{-- <td class="table-td text-center">
                                    @if($index === 0)
                                        <span class="badge bg-rank-500 text-white">Vàng</span>
                                    @elseif($index === 1)
                                        <span class="badge bg-slate-400 text-white">Bạc</span>
                                    @elseif($index === 2)
                                        <span class="badge bg-amber-600 text-white">Đồng</span>
                                    @else
                                        <span class="badge-neutral"></span>
                                    @endif
                                </td> --}}
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="table-td text-center py-8 text-slate-400">
                                    <i class="ph-trophy text-3xl mb-2 block"></i>
                                    <p>Chưa có dữ liệu xếp hạng</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile list cards -->
                <div class="leaderboard-list sm:hidden p-4">
                    @forelse($employees as $index => $emp)
                        <a href="{{ route('employees.show', $emp) }}" class="leaderboard-card {{ $index < 3 ? 'leaderboard-card-top' : '' }}" style="animation-delay: {{ $index * 40 }}ms">
                            <div class="leaderboard-card-rank">
                                @if($index < 3)
                                    <span class="rank-badge rank-{{ $index + 1 }} leaderboard-medal">
                                        <i class="bi bi-trophy"></i>
                                    </span>
                                @else
                                    <span class="leaderboard-rank-number">{{ $index + 1 }}</span>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <div class="font-semibold text-slate-900 dark:text-white truncate">{{ $emp->name }}</div>
                                    @if($index === 0)
                                        <span class="badge bg-rank-500 text-white whitespace-nowrap">Vàng</span>
                                    @elseif($index === 1)
                                        <span class="badge bg-slate-400 text-white whitespace-nowrap">Bạc</span>
                                    @elseif($index === 2)
                                        <span class="badge bg-amber-600 text-white whitespace-nowrap">Đồng</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-[12px] text-slate-500 dark:text-slate-400 truncate">
                                    {{ $emp->branch->name ?? '—' }} • {{ $emp->team->name ?? '—' }}
                                </div>
                            </div>

                            <div class="leaderboard-card-score">
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">Tổng điểm</div>
                                <div class="text-lg font-extrabold text-slate-900 dark:text-white">{{ number_format($emp->total_score ?? 0) }}</div>
                            </div>
                        </a>
                    @empty
                        <div class="text-center py-12 text-slate-400">
                            <i class="ph-trophy text-3xl mb-2 block"></i>
                            <p>Chưa có dữ liệu xếp hạng</p>
                        </div>
                    @endforelse
                </div>
            </div>

            @if($employees->hasPages())
            <div class="card-footer">
                {{ $employees->links() }}
            </div>
            @endif
        </div>
    </div>

    <!-- Team Rankings -->
    <div id="tab-teams" class="tab-content hidden" role="tabpanel" aria-labelledby="tab-teams-btn">
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-slate-900 dark:text-white">Xếp hạng đội nhóm</h3>
            </div>
            <div class="card-body p-0">
                <!-- Desktop table -->
                <div class="table-container border-0 rounded-none hidden sm:block">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th class="table-th w-12">#</th>
                                <th class="table-th">Đội nhóm</th>
                                <th class="table-th">Chi nhánh</th>
                                <th class="table-th text-center">Số nhân viên</th>
                                <th class="table-th text-center">Điểm trung bình</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teams as $index => $team)
                            <tr class="table-tr-hover {{ $index < 3 ? 'leaderboard-row-pop' : '' }}" style="animation-delay: {{ $index * 40 }}ms">
                                <td class="table-td">
                                    @if($index < 3)
                                        <span class="rank-badge rank-{{ $index + 1 }} leaderboard-medal">
                                            <i class="bi bi-trophy"></i>
                                        </span>
                                    @else
                                        <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td class="table-td font-medium">{{ $team->name }}</td>
                                <td class="table-td">{{ $team->branch->name ?? '—' }}</td>
                                <td class="table-td text-center">{{ $team->employees_count ?? 0 }}</td>
                                <td class="table-td text-center font-bold">{{ number_format($team->average_score, 1) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="table-td text-center py-8 text-slate-400">
                                    <i class="ph-user-squares text-3xl mb-2 block"></i>
                                    <p>Chưa có đội nhóm nào</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile list cards -->
                <div class="leaderboard-list sm:hidden p-4">
                    @forelse($teams as $index => $team)
                        <div class="leaderboard-card {{ $index < 3 ? 'leaderboard-card-top' : '' }}" style="animation-delay: {{ $index * 40 }}ms">
                            <div class="leaderboard-card-rank">
                                @if($index < 3)
                                    <span class="rank-badge rank-{{ $index + 1 }} leaderboard-medal">
                                        <i class="ph-trophy-fill"></i>
                                    </span>
                                @else
                                    <span class="leaderboard-rank-number">{{ $index + 1 }}</span>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-slate-900 dark:text-white truncate">{{ $team->name }}</div>
                                <div class="mt-1 text-[12px] text-slate-500 dark:text-slate-400 truncate">
                                    {{ $team->branch->name ?? '—' }}
                                </div>
                            </div>

                            <div class="leaderboard-card-score">
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">TB điểm</div>
                                <div class="text-lg font-extrabold text-slate-900 dark:text-white">{{ number_format($team->average_score, 1) }}</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{{ $team->employees_count ?? 0 }} người</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-slate-400">
                            <i class="ph-user-squares text-3xl mb-2 block"></i>
                            <p>Chưa có đội nhóm nào</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));

            document.getElementById('tab-' + tab).classList.remove('hidden');

            const isEmployees = tab === 'employees';
            document.getElementById('tab-employees-btn').className = isEmployees ? 'btn-primary' : 'btn-secondary';
            document.getElementById('tab-teams-btn').className = isEmployees ? 'btn-secondary' : 'btn-primary';

            document.getElementById('tab-employees-btn').setAttribute('aria-selected', isEmployees ? 'true' : 'false');
            document.getElementById('tab-teams-btn').setAttribute('aria-selected', isEmployees ? 'false' : 'true');
        }
    </script>

@endsection
