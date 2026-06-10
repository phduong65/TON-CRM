<?php

namespace App\Http\Controllers;

use App\Models\Penalty;
use App\Models\Employee;
use App\Models\Violation;
use Illuminate\Http\Request;

class PenaltiesController extends Controller
{
    public function index()
    {
        $penalties = Penalty::with(['employee', 'violation', 'approver'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        return view('penalties.index', compact('penalties'));
    }

    public function show(Penalty $penalty)
    {
        $penalty->load(['employee', 'violation', 'approver', 'members.employee']);
        return view('penalties.show', compact('penalty'));
    }

    public function approve(Penalty $penalty)
    {
        $penalty->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Deduct points
        foreach ($penalty->members as $member) {
            $member->employee->scores()->create([
                'points' => -$member->points_deducted,
                'reason' => $penalty->violation->name,
                'type' => 'penalty',
                'reference_type' => Penalty::class,
                'reference_id' => $penalty->id,
            ]);
        }

        return back()->with('success', 'Đã duyệt xử phạt!');
    }

    public function reject(Request $request, Penalty $penalty)
    {
        $request->validate(['rejected_reason' => 'required|string|max:500']);

        $penalty->update([
            'status' => 'rejected',
            'rejected_reason' => $request->rejected_reason,
        ]);

        return back()->with('success', 'Đã từ chối xử phạt!');
    }
}
