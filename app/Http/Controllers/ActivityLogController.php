<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with('causer')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('description', 'like', "%$s%")
                  ->orWhereHas('causer', fn($cq) => $cq->where('name', 'like', "%$s%"));
            });
        }

        if ($request->filled('event')) {
            $query->where('log_name', $request->event);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $activities = $query->paginate(30)->withQueryString();
        $eventTypes = Activity::distinct()->pluck('log_name')->sort()->values();

        return view('activity.index', compact('activities', 'eventTypes'));
    }
}
