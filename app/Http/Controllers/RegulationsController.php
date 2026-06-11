<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use Illuminate\Http\Request;

class RegulationsController extends Controller
{
    public function index(Request $request)
    {
        $query = Regulation::withCount('violations')->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'like', "%$s%");
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $regulations = $query->paginate(15)->withQueryString();
        return view('regulations.index', compact('regulations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'is_active'      => 'boolean',
            'effective_date' => 'nullable|date',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Regulation::create($validated);

        return redirect()->route('regulations.index')
            ->with('success', 'Quy chế đã được tạo!');
    }

    public function update(Request $request, Regulation $regulation)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'is_active'      => 'boolean',
            'effective_date' => 'nullable|date',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $regulation->update($validated);

        return redirect()->route('regulations.index')
            ->with('success', 'Quy chế đã được cập nhật!');
    }

    public function destroy(Regulation $regulation)
    {
        $regulation->update(['is_active' => false]);
        return back()->with('success', 'Quy chế đã được vô hiệu hóa!');
    }
}
