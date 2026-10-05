<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Holiday;
use App\Models\Team;
use App\Services\HolidayApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HolidaysController extends Controller
{
    public function index(Request $request)
    {
        $query = Holiday::with(['teams.branch'])->withCount('teams')->orderByDesc('date');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'like', "%$s%");
        }

        if ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $holidays = $query->paginate(15)->withQueryString();

        // Danh sách bộ phận (team) gom theo chi nhánh cho modal chọn phạm vi; kèm id các team văn
        // phòng để mặc định tick sẵn khi tạo lễ mới (khối văn phòng nghỉ lễ).
        $branches = Branch::with(['teams' => fn($q) => $q->where('is_active', true)->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $officeTeamIds = Team::where('is_active', true)->where('is_office', true)->pluck('id')->all();

        return view('holidays.index', compact('holidays', 'branches', 'officeTeamIds'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateHoliday($request);

        $holiday = DB::transaction(function () use ($request, $validated) {
            $holiday = Holiday::create([
                'date'           => $validated['date'],
                'name'           => $validated['name'],
                'applies_to_all' => $validated['applies_to_all'],
                'is_paid'        => $request->boolean('is_paid', true),
                'bonus_amount'   => $validated['bonus_amount'] ?? null,
                'is_active'      => $request->boolean('is_active', true),
                'created_by'     => auth()->id(),
            ]);

            if (!$validated['applies_to_all']) {
                $holiday->teams()->sync($validated['team_ids']);
            }

            return $holiday;
        });

        // Tự huỷ ca + tạo bản ghi chấm công nghỉ lễ cho khối được nghỉ.
        app(HolidayApplicationService::class)->apply($holiday);

        activity()->causedBy(auth()->user())->performedOn($holiday)->inLog('holiday')
            ->withProperties(['name' => $holiday->name, 'date' => $holiday->date->toDateString()])
            ->log("Thêm ngày nghỉ lễ {$holiday->name}");

        return redirect()->route('holidays.index')->with('success', 'Đã thêm ngày nghỉ lễ và áp dụng cho khối được nghỉ!');
    }

    public function update(Request $request, Holiday $holiday)
    {
        $validated = $this->validateHoliday($request, $holiday->id);

        DB::transaction(function () use ($request, $validated, $holiday) {
            $holiday->update([
                'date'           => $validated['date'],
                'name'           => $validated['name'],
                'applies_to_all' => $validated['applies_to_all'],
                'is_paid'        => $request->boolean('is_paid'),
                'bonus_amount'   => $validated['bonus_amount'] ?? null,
                'is_active'      => $request->boolean('is_active'),
            ]);

            $holiday->teams()->sync($validated['applies_to_all'] ? [] : $validated['team_ids']);
        });

        // Đổi ngày/phạm vi/kích hoạt -> đảo ngược tác động cũ rồi áp dụng lại theo cấu hình mới.
        app(HolidayApplicationService::class)->reapply($holiday);

        activity()->causedBy(auth()->user())->performedOn($holiday)->inLog('holiday')
            ->withProperties(['name' => $holiday->name, 'date' => $holiday->date->toDateString()])
            ->log("Cập nhật ngày nghỉ lễ {$holiday->name}");

        return redirect()->route('holidays.index')->with('success', 'Đã cập nhật ngày nghỉ lễ và áp dụng lại!');
    }

    public function destroy(Holiday $holiday)
    {
        // Vô hiệu hoá + đảo ngược: khôi phục ca đã huỷ, xoá bản ghi chấm công lễ tự tạo.
        DB::transaction(function () use ($holiday) {
            app(HolidayApplicationService::class)->reverse($holiday);
            $holiday->update(['is_active' => false]);
        });

        activity()->causedBy(auth()->user())->performedOn($holiday)->inLog('holiday')
            ->withProperties(['name' => $holiday->name])
            ->log("Vô hiệu hoá ngày nghỉ lễ {$holiday->name}");

        return back()->with('success', 'Đã vô hiệu hóa ngày nghỉ lễ và khôi phục lịch/chấm công liên quan!');
    }

    /**
     * Validate + chuẩn hoá phạm vi. Nếu KHÔNG áp dụng toàn bộ thì bắt buộc chọn ít nhất 1 bộ phận.
     */
    private function validateHoliday(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'date'           => 'required|date|unique:holidays,date' . ($ignoreId ? ",$ignoreId" : ''),
            'name'           => 'required|string|max:150',
            'applies_to_all' => 'required|boolean',
            'team_ids'       => 'nullable|array',
            'team_ids.*'     => 'integer|exists:teams,id',
            'is_paid'        => 'boolean',
            'bonus_amount'   => 'nullable|numeric|min:0',
            'is_active'      => 'boolean',
        ], [
            'date.unique' => 'Ngày này đã có trong danh sách nghỉ lễ.',
        ]);

        $validated['applies_to_all'] = $request->boolean('applies_to_all');
        $validated['team_ids']       = $validated['team_ids'] ?? [];

        if (!$validated['applies_to_all'] && empty($validated['team_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'team_ids' => 'Vui lòng chọn ít nhất 1 bộ phận, hoặc chọn "Áp dụng toàn công ty".',
            ]);
        }

        return $validated;
    }
}
