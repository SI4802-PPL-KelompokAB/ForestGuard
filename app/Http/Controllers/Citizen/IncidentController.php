<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\Incidents\StoreIncidentReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncidentRequest;
use App\Models\CaseStatus;
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

        $incident = Incident::query()
            ->select([
                'id',
                'code',
                'reporter_id',
                'incident_type_id',
                'forest_area_id',
                'case_status_id',
                'description',
                'latitude',
                'longitude',
                'gps_accuracy',
                'occurred_at',
                'reject_reason',
                'created_at',
                'updated_at',
            ])
            ->with([
                'incidentType:id,name',
                'forestArea:id,name',
                'caseStatus:id,name,sequence,is_terminal',
                'evidences' => fn ($query) => $query
                    ->select('id', 'incident_id', 'mime_type', 'captured_at')
                    ->orderBy('id'),
                'statusHistories' => fn ($query) => $query
                    ->select('id', 'incident_id', 'from_status_id', 'to_status_id', 'changed_at')
                    ->orderBy('changed_at'),
            ])
            ->findOrFail($incident->getKey());

        $statuses = CaseStatus::query()
            ->orderBy('sequence')
            ->get(['id', 'name', 'sequence', 'is_terminal']);
        $normalStatuses = $statuses
            ->reject(fn (CaseStatus $status): bool => $status->name === 'Rejected')
            ->values();
        $isRejected = $incident->caseStatus?->name === 'Rejected';
        $currentStatusSequence = $incident->caseStatus?->sequence;
        $historyByStatus = $incident->statusHistories
            ->groupBy('to_status_id')
            ->map(fn ($histories) => $histories->last());
        $rejectionHistory = $isRejected
            ? $historyByStatus->get($incident->case_status_id)
            : null;
        $rejectionPreviousSequence = $rejectionHistory
            ? $statuses->firstWhere('id', $rejectionHistory->from_status_id)?->sequence
            : null;

        $timeline = $normalStatuses->map(function (CaseStatus $status) use (
            $currentStatusSequence,
            $historyByStatus,
            $isRejected,
            $rejectionPreviousSequence
        ): array {
            $state = match (true) {
                $isRejected && $rejectionPreviousSequence !== null && $status->sequence <= $rejectionPreviousSequence => 'complete',
                $isRejected => 'upcoming',
                $currentStatusSequence !== null && $status->sequence < $currentStatusSequence => 'complete',
                $currentStatusSequence !== null && $status->sequence === $currentStatusSequence => 'current',
                default => 'upcoming',
            };

            return [
                'name' => $status->name,
                'state' => $state,
                'changedAt' => $historyByStatus->get($status->id)?->changed_at,
            ];
        });

        if ($isRejected) {
            $timeline->push([
                'name' => 'Ditolak',
                'state' => 'current',
                'changedAt' => $rejectionHistory?->changed_at,
            ]);
        }

        return view('citizen.incidents.show', compact('incident', 'timeline', 'isRejected'));
    }
}
