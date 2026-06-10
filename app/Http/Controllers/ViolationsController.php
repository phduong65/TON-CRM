<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Models\Regulation;
use Illuminate\Http\Request;

class ViolationsController extends Controller
{
    public function index(Request $request)
    {
        $query = Violation::with('regulation')
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        if ($request->filled('regulation_id')) {
            $query->where('regulation_id', $request->regulation_id);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $violations  = $query->paginate(15)->withQueryString();
        $regulations = Regulation::orderBy('name')->get();

        return view('violations.index', compact('violations', 'regulations'));
    }
}
