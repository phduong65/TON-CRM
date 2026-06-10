<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Models\Regulation;
use Illuminate\Http\Request;

class ViolationsController extends Controller
{
    public function index()
    {
        $violations = Violation::with('regulation')
            ->orderBy('name')
            ->paginate(15);
        return view('violations.index', compact('violations'));
    }
}
