<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $incidents = Incident::with(['incidentType', 'forestArea', 'caseStatus', 'evidences'])
            ->where('reporter_id', $request->user()->id)
            ->latest('occurred_at')
            ->get();

        return view('citizen.home', compact('incidents'));
    }
}
