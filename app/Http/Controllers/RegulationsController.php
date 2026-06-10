<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use Illuminate\Http\Request;

class RegulationsController extends Controller
{
    public function index(Request $request)
    {
        $query = Regulation::orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $regulations = $query->paginate(15)->withQueryString();
        return view('regulations.index', compact('regulations'));
    }

    public function create()
    {
        return view('regulations.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:regulations',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:points,money,both',
            'default_points' => 'nullable|integer|min:0',
            'default_money' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'effective_date' => 'nullable|date',
        ]);

        Regulation::create($validated);

        return redirect()->route('regulations.index')
            ->with('success', 'Quy chế đã được tạo!');
    }

    public function edit(Regulation $regulation)
    {
        return view('regulations.form', compact('regulation'));
    }

    public function update(Request $request, Regulation $regulation)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:regulations,code,' . $regulation->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:points,money,both',
            'default_points' => 'nullable|integer|min:0',
            'default_money' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'effective_date' => 'nullable|date',
        ]);

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
