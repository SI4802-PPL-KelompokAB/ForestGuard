<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IncidentReportFilterRequest;
use App\Models\CaseStatus;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Support\Reports\IncidentReportPdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(IncidentReportFilterRequest $request): View
    {
        return view('admin.reports.index', [
            'incidents' => $this->filteredIncidents($request)->paginate(15)->withQueryString(),
            'statuses' => CaseStatus::query()->orderBy('sequence')->get(),
            'incidentTypes' => IncidentType::query()->orderBy('name')->get(),
            'forestAreas' => ForestArea::query()->orderBy('name')->get(),
            'filters' => $request->validated(),
        ]);
    }

    public function export(IncidentReportFilterRequest $request, IncidentReportPdf $pdf): Response
    {
        $incidents = $this->filteredIncidents($request)->get();
        $filters = $this->filterLabels($request->validated());
        $filename = 'laporan-insiden-'.now()->format('Ymd-His').'.pdf';

        return response($pdf->make($incidents, $filters), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function filterLabels(array $filters): array
    {
        $filters['status_label'] = $filters['status'] ?? 'Semua status';
        $filters['incident_type_label'] = ! empty($filters['incident_type_id'])
            ? IncidentType::query()->find($filters['incident_type_id'])?->name
            : 'Semua jenis';
        $filters['forest_area_label'] = ! empty($filters['forest_area_id'])
            ? ForestArea::query()->find($filters['forest_area_id'])?->name
            : 'Semua kawasan';

        return $filters;
    }

    private function filteredIncidents(IncidentReportFilterRequest $request): \Illuminate\Database\Eloquent\Builder
    {
        $filters = $request->validated();
        $query = Incident::query()
            ->with(['incidentType', 'forestArea', 'caseStatus', 'reporter'])
            ->latest('occurred_at');

        if (! empty($filters['date_from'])) {
            $query->whereDate('occurred_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('occurred_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['status'])) {
            $query->whereHas('caseStatus', fn ($statusQuery) => $statusQuery->where('name', $filters['status']));
        }

        if (! empty($filters['incident_type_id'])) {
            $query->where('incident_type_id', $filters['incident_type_id']);
        }

        if (! empty($filters['forest_area_id'])) {
            $query->where('forest_area_id', $filters['forest_area_id']);
        }

        return $query;
    }
}
