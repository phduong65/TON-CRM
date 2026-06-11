<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Models\Regulation;
use Illuminate\Http\Request;

class ViolationsController extends Controller
{
    public function index(Request $request)
    {
        $query = Violation::with('regulation')->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'like', "%$s%");
        }

        if ($request->filled('regulation_id')) {
            $query->where('regulation_id', $request->regulation_id);
        }

        if ($request->filled('penalty_type')) {
            $query->where('penalty_type', $request->penalty_type);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $violations  = $query->paginate(15)->withQueryString();
        $regulations = Regulation::where('is_active', true)->orderBy('name')->get();

        return view('violations.index', compact('violations', 'regulations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'severity'        => 'required|in:low,medium,high,critical',
            'regulation_id'   => 'required|exists:regulations,id',
            'penalty_type'    => 'required|in:points,money,both',
            'points_deducted' => 'nullable|integer|min:0|max:100',
            'money_deducted'  => 'nullable|numeric|min:0',
            'is_active'       => 'boolean',
        ]);

        $validated['is_active']       = $request->boolean('is_active', true);
        $validated['points_deducted'] = $validated['points_deducted'] ?? 0;
        $validated['money_deducted']  = $validated['money_deducted'] ?? 0;

        Violation::create($validated);

        return redirect()->route('violations.index')
            ->with('success', 'Lỗi vi phạm đã được tạo!');
    }

    public function update(Request $request, Violation $violation)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'severity'        => 'required|in:low,medium,high,critical',
            'regulation_id'   => 'required|exists:regulations,id',
            'penalty_type'    => 'required|in:points,money,both',
            'points_deducted' => 'nullable|integer|min:0|max:100',
            'money_deducted'  => 'nullable|numeric|min:0',
            'is_active'       => 'boolean',
        ]);

        $validated['is_active']       = $request->boolean('is_active');
        $validated['points_deducted'] = $validated['points_deducted'] ?? 0;
        $validated['money_deducted']  = $validated['money_deducted'] ?? 0;

        $violation->update($validated);

        return redirect()->route('violations.index')
            ->with('success', 'Lỗi vi phạm đã được cập nhật!');
    }

    public function destroy(Violation $violation)
    {
        $violation->update(['is_active' => false]);

        return back()->with('success', 'Lỗi vi phạm đã được vô hiệu hóa!');
    }
}
