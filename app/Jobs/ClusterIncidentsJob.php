<?php

namespace App\Jobs;

use App\Services\IncidentClusteringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClusterIncidentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /** @param list<int> $incidentIds */
    public function __construct(public array $incidentIds = [], public ?float $radiusKm = null) {}

    public function handle(IncidentClusteringService $clusteringService): void
    {
        $clusteringService->cluster($this->incidentIds, $this->radiusKm);
    }
}
