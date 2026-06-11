<?php

namespace App\Http\Controllers;

use App\Models\AttendanceImportRule;
use App\Models\Violation;
use Illuminate\Http\Request;

class AttendanceImportRulesController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'type'        => 'required|in:late,early',
            'min_minutes' => 'required|integer|min:1',
            'max_minutes' => 'nullable|integer|min:1|gt:min_minutes',
            'violation_id'=> 'required|exists:violations,id',
            'label'       => 'nullable|string|max:100',
            'is_active'   => 'boolean',
        ]);

        $rule = new AttendanceImportRule($data + ['is_active' => true]);

        if ($rule->overlapsWithExisting()) {
            return back()
                ->withInput()
                ->withErrors(['overlap' => 'Khoảng phút này trùng với một quy tắc khác đang hoạt động. Vui lòng điều chỉnh ngưỡng.'])
                ->with('_modal', 'createRuleModal');
        }

        AttendanceImportRule::create($data + ['is_active' => true]);

        return back()->with('success', 'Đã thêm quy tắc phạt chấm công.');
    }

    public function update(Request $request, AttendanceImportRule $rule)
    {
        $data = $request->validate([
            'type'        => 'required|in:late,early',
            'min_minutes' => 'required|integer|min:1',
            'max_minutes' => 'nullable|integer|min:1|gt:min_minutes',
            'violation_id'=> 'required|exists:violations,id',
            'label'       => 'nullable|string|max:100',
            'is_active'   => 'boolean',
        ]);

        $rule->fill($data);

        if ($rule->overlapsWithExisting($rule->id)) {
            return back()
                ->withInput()
                ->withErrors(['overlap' => 'Khoảng phút này trùng với một quy tắc khác đang hoạt động.'])
                ->with('_modal', 'editRuleModal_' . $rule->id);
        }

        $rule->save();

        return back()->with('success', 'Đã cập nhật quy tắc.');
    }

    public function destroy(AttendanceImportRule $rule)
    {
        $rule->delete();
        return back()->with('success', 'Đã xóa quy tắc.');
    }

    public function toggleActive(AttendanceImportRule $rule)
    {
        $rule->update(['is_active' => !$rule->is_active]);
        return back()->with('success', $rule->is_active ? 'Đã bật quy tắc.' : 'Đã tắt quy tắc.');
    }
}
