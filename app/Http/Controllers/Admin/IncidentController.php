<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Incidents\TransitionIncidentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Models\CaseStatus;
use App\Models\Incident;
use App\Models\RiskLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');

        $query = Incident::with(['incidentType', 'forestArea', 'caseStatus', 'reporter'])
            ->latest('occurred_at');

        if ($statusFilter === 'needs-verification') {
            $query->whereHas('caseStatus', fn ($q) => $q->whereIn('name', Incident::PENDING_VERIFICATION_STATUSES));
        } elseif (is_string($statusFilter) && $statusFilter !== '') {
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

        $riskLevels = $incident->caseStatus->name === 'Under Verification'
            ? RiskLevel::active()->orderBy('sort_order')->orderBy('id')->get()
            : collect();
        $riskSnapshot = $riskLevels->isNotEmpty()
            ? Crypt::encryptString($riskLevels->keyBy('id')->toJson())
            : null;

        return view('admin.incidents.show', compact('incident', 'riskLevels', 'riskSnapshot'));
    }

    public function updateStatus(Request $request, Incident $incident, TransitionIncidentStatus $action): RedirectResponse
    {
        $validated = $request->validate([
            'to_status' => ['required', 'string'],
            'risk_level_id' => ['nullable', 'integer'],
            'risk_snapshot' => ['nullable', 'string'],
            'reject_reason' => ['nullable', 'string', 'max:1000'],
            'resolution_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['risk_snapshot'] ??= '';

        try {
            $action->handle($request->user(), $incident, $validated['to_status'], $validated);
        } catch (InvalidStatusTransitionException) {
            $message = 'Status kasus sudah berubah atau aksi ini tidak tersedia untuk status saat ini.';

            abort_if($request->expectsJson(), 409, $message);

            return to_route('admin.incidents.show', $incident)->withErrors(['to_status' => $message]);
        }

        return to_route('admin.incidents.show', $incident)->with('status', 'Status kasus berhasil diperbarui.');
    }
}
