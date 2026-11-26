<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ClusterIncidentsJob;
use App\Models\IncidentCluster;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IncidentClusterController extends Controller
{
    public function index(): View
    {
        $clusters = IncidentCluster::query()
            ->with(['incidents.incidentType', 'incidents.forestArea'])
            ->latest('processed_at')
            ->paginate(10);

        $mapClusters = IncidentCluster::query()
            ->with('incidents')
            ->latest('processed_at')
            ->get();

        return view('admin.incident-clusters.index', compact('clusters', 'mapClusters'));
    }

    public function process(): RedirectResponse
    {
        ClusterIncidentsJob::dispatch();

        return to_route('admin.incident-clusters.index')
            ->with('status', 'Proses pengelompokan insiden dimasukkan ke antrean. Jalankan queue worker untuk memperbarui hasil.');
    }
}
