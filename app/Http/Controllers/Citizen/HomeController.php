<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\CaseStatus;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $reporterId = $request->user()->id;
        $statuses = CaseStatus::query()
            ->orderBy('sequence')
            ->get(['id', 'name', 'sequence', 'is_terminal']);
        $normalStatuses = $statuses
            ->reject(fn (CaseStatus $status): bool => $status->name === 'Rejected')
            ->values();

        $totalReports = Incident::query()
            ->where('reporter_id', $reporterId)
            ->count();
        $processingReports = Incident::query()
            ->where('reporter_id', $reporterId)
            ->whereHas('caseStatus', fn ($query) => $query->where('is_terminal', false))
            ->count();
        $resolvedStatusId = $statuses->firstWhere('name', 'Resolved')?->id;
        $resolvedReports = $resolvedStatusId === null
            ? 0
            : Incident::query()
                ->where('reporter_id', $reporterId)
                ->where('case_status_id', $resolvedStatusId)
                ->count();

        $incidents = Incident::query()
            ->select([
                'id',
                'reporter_id',
                'code',
                'incident_type_id',
                'forest_area_id',
                'case_status_id',
                'description',
                'occurred_at',
                'created_at',
            ])
            ->with([
                'incidentType:id,name',
                'forestArea:id,name',
                'caseStatus:id,name,sequence,is_terminal',
                'statusHistories:id,incident_id,from_status_id,to_status_id,changed_at',
            ])
            ->where('reporter_id', $reporterId)
            ->latest('created_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $incidents->setCollection($incidents->getCollection()->map(function (Incident $incident) use ($normalStatuses): array {
            $isRejected = $incident->caseStatus?->name === 'Rejected';
            $currentIndex = $normalStatuses->search(
                fn (CaseStatus $status): bool => $status->id === $incident->case_status_id
            );
            $currentStatusSequence = $currentIndex === false
                ? null
                : $normalStatuses->get($currentIndex)?->sequence;
            $rejectionHistory = $isRejected
                ? $incident->statusHistories->firstWhere('to_status_id', $incident->case_status_id)
                : null;
            $rejectionPreviousSequence = $rejectionHistory
                ? $normalStatuses->firstWhere('id', $rejectionHistory->from_status_id)?->sequence
                : null;
            $steps = $normalStatuses->map(function (CaseStatus $status) use ($currentStatusSequence, $isRejected, $rejectionPreviousSequence): array {
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
                ];
            });

            return [
                'incident' => $incident,
                'steps' => $steps,
                'isRejected' => $isRejected,
                'currentStatus' => $incident->caseStatus?->name ?? 'Unknown',
                'nextStatus' => ! $isRejected && $currentIndex !== false
                    ? $normalStatuses->get($currentIndex + 1)?->name
                    : null,
            ];
        }));

        return view('citizen.home', [
            'incidents' => $incidents,
            'firstName' => Str::of($request->user()->name)->before(' ')->toString(),
            'totalReports' => $totalReports,
            'processingReports' => $processingReports,
            'resolvedReports' => $resolvedReports,
            'canCreateIncident' => Route::has('citizen.incidents.create'),
        ]);
    }
}
