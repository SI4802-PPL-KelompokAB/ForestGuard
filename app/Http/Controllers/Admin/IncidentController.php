<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Incidents\TransitionIncidentStatus;
use App\Http\Controllers\Controller;
use App\Models\CaseStatus;
use App\Models\Incident;
use App\Models\RiskLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');

        $query = Incident::with(['incidentType', 'forestArea', 'caseStatus', 'reporter'])
            ->latest('occurred_at');

        if ($statusFilter) {
            $query->whereHas('caseStatus', fn ($q) => $q->where('name', $statusFilter));
        }

        $incidents = $query->paginate(15)->withQueryString();
        $statuses = CaseStatus::orderBy('sequence')->get();

        return view('admin.incidents.index', compact('incidents', 'statuses', 'statusFilter'));
    }

    public function show(Incident $incident): View
    {
        $incident->load([
            'incidentType',
            'forestArea',
            'caseStatus',
            'riskLevel',
            'reporter',
            'evidences',
            'statusHistories.fromStatus',
            'statusHistories.toStatus',
            'statusHistories.changedBy',
        ]);

        $riskLevels = RiskLevel::orderBy('sort_order')->get();
        $statuses = CaseStatus::orderBy('sequence')->get();

        return view('admin.incidents.show', compact('incident', 'riskLevels', 'statuses'));
    }

    public function updateStatus(Request $request, Incident $incident, TransitionIncidentStatus $action): RedirectResponse
    {
        $request->validate([
            'to_status' => ['required', 'string'],
            'risk_level_id' => ['nullable', 'integer', 'exists:risk_levels,id'],
            'reject_reason' => ['nullable', 'string'],
            'resolution_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $action->handle(
            $request->user(),
            $incident,
            $request->input('to_status'),
            $request->only(['risk_level_id', 'reject_reason', 'resolution_note', 'note'])
        );

        return back()->with('status', 'Status kasus berhasil diperbarui.');
    }
}
