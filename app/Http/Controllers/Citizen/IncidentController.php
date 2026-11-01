<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\Incidents\StoreIncidentReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncidentRequest;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function create(): View
    {
        $incidentTypes = IncidentType::orderBy('name')->get();
        $forestAreas = ForestArea::orderBy('name')->get();

        return view('citizen.incidents.create', compact('incidentTypes', 'forestAreas'));
    }

    public function store(StoreIncidentRequest $request, StoreIncidentReport $action): RedirectResponse
    {
        $incident = $action->handle(
            $request->user(),
            $request->validated(),
            $request->file('photo')
        );

        return redirect()->route('citizen.incidents.show', $incident)
            ->with('status', 'Laporan insiden berhasil dikirim.')
            ->with('incident_code', $incident->code);
    }

    public function show(Request $request, Incident $incident): View
    {
        abort_unless($incident->reporter_id === $request->user()->id, 403);

        $incident->load(['incidentType', 'forestArea', 'caseStatus', 'evidences', 'statusHistories.toStatus', 'statusHistories.changedBy']);

        return view('citizen.incidents.show', compact('incident'));
    }
}
