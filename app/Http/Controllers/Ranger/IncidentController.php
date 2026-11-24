<?php

namespace App\Http\Controllers\Ranger;

use App\Actions\Incidents\StoreIncidentReport;
use App\Actions\Incidents\TransitionIncidentStatus;
use App\Enums\PatrolStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncidentRequest;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Observation;
use App\Models\Patrol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function create(Request $request): View
    {
        $incidentTypes = IncidentType::orderBy('name')->get();
        $forestAreas = ForestArea::orderBy('name')->get();

        // Optional active patrol of current ranger
        $activePatrol = Patrol::where('ranger_id', $request->user()->id)
            ->where('status', PatrolStatus::InProgress)
            ->first();

        $sourceObservation = null;
        if ($request->filled('observation')) {
            $sourceObservation = Observation::with('patrol.forestArea')
                ->whereHas('patrol', fn ($query) => $query->where('ranger_id', $request->user()->id))
                ->findOrFail($request->integer('observation'));
        }

        return view('ranger.incidents.create', compact('incidentTypes', 'forestAreas', 'activePatrol', 'sourceObservation'));
    }

    public function store(StoreIncidentRequest $request, StoreIncidentReport $action): RedirectResponse
    {
        $validated = $request->validated();

        if (! empty($validated['patrol_id'])) {
            $request->user()->patrols()->findOrFail($validated['patrol_id']);
        }

        // Automatically assign active patrol if not provided and exists
        if (empty($validated['patrol_id'])) {
            $activePatrol = Patrol::where('ranger_id', $request->user()->id)
                ->where('status', PatrolStatus::InProgress)
                ->first();

            if ($activePatrol) {
                $validated['patrol_id'] = $activePatrol->id;
            }
        }

        $incident = $action->handle(
            $request->user(),
            $validated,
            $request->file('photo')
        );

        return redirect()->route('ranger.incidents.show', $incident)
            ->with('status', 'Laporan insiden berhasil dikirim.')
            ->with('incident_code', $incident->code);
    }

    public function show(Request $request, Incident $incident): View
    {
        $incident->load(['caseStatus', 'patrol']);
        $rangerId = $request->user()->id;
        $isReporter = $incident->reporter_id === $rangerId;
        abort_unless($isReporter || ($incident->caseStatus->name !== 'Rejected' && (
            $incident->patrol?->ranger_id === $rangerId || $incident->assignments()->where('ranger_id', $rangerId)->exists()
        )), 404);

        $incident->load(['incidentType', 'forestArea', 'evidences', 'statusHistories.fromStatus', 'statusHistories.toStatus', 'statusHistories.changedBy']);

        return view('ranger.incidents.show', compact('incident'));
    }

    public function updateStatus(Request $request, Incident $incident, TransitionIncidentStatus $action): RedirectResponse
    {
        $request->validate([
            'to_status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->handle(
            $request->user(),
            $incident,
            $request->input('to_status'),
            $request->only(['note'])
        );

        return back()->with('status', 'Status kasus berhasil diperbarui.');
    }
}
