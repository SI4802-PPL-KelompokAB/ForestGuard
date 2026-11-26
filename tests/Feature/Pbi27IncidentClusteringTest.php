<?php

namespace Tests\Feature;

use App\Jobs\ClusterIncidentsJob;
use App\Models\CaseStatus;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentCluster;
use App\Models\IncidentType;
use App\Models\User;
use App\Services\IncidentClusteringService;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\ForestAreaSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Pbi27IncidentClusteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            CaseStatusSeeder::class,
            IncidentTypeSeeder::class,
            ForestAreaSeeder::class,
        ]);
    }

    public function test_haversine_returns_distance_in_kilometers(): void
    {
        $service = app(IncidentClusteringService::class);

        $distance = $service->distanceKm(-6.9000, 107.6000, -6.9010, 107.6000);

        $this->assertGreaterThan(0.1, $distance);
        $this->assertLessThan(0.2, $distance);
    }

    public function test_incidents_within_radius_share_a_cluster_and_centroid_is_calculated(): void
    {
        $nearbyOne = $this->createIncident('CLUSTER-01', -6.9000, 107.6000);
        $nearbyTwo = $this->createIncident('CLUSTER-02', -6.9010, 107.6000);
        $farAway = $this->createIncident('CLUSTER-03', -6.9500, 107.6000);

        $clusters = app(IncidentClusteringService::class)->cluster([], 1.0);

        $this->assertCount(2, $clusters);
        $cluster = IncidentCluster::query()->where('report_count', 2)->firstOrFail();
        $this->assertEqualsWithDelta(-6.9005, (float) $cluster->centroid_latitude, 0.00001);
        $this->assertEqualsWithDelta(107.6000, (float) $cluster->centroid_longitude, 0.00001);
        $this->assertDatabaseHas('incident_cluster_incident', [
            'incident_id' => $nearbyOne->id,
        ]);
        $this->assertDatabaseHas('incident_cluster_incident', [
            'incident_id' => $nearbyTwo->id,
        ]);
        $this->assertDatabaseHas('incident_cluster_incident', [
            'incident_id' => $farAway->id,
        ]);
    }

    public function test_invalid_coordinates_are_not_clustered(): void
    {
        $invalid = $this->createIncident('CLUSTER-INVALID', 100.0, 107.6000);

        app(IncidentClusteringService::class)->cluster([], 5.0);

        $this->assertDatabaseMissing('incident_cluster_incident', [
            'incident_id' => $invalid->id,
        ]);
        $this->assertDatabaseCount('incident_clusters', 0);
    }

    public function test_reprocessing_is_idempotent_and_does_not_duplicate_clusters(): void
    {
        $incidentOne = $this->createIncident('CLUSTER-IDEMPOTENT-01', -6.9000, 107.6000);
        $incidentTwo = $this->createIncident('CLUSTER-IDEMPOTENT-02', -6.9010, 107.6000);
        $service = app(IncidentClusteringService::class);

        $service->cluster([$incidentOne->id, $incidentTwo->id], 1.0);
        $service->cluster([$incidentOne->id, $incidentTwo->id], 1.0);

        $this->assertDatabaseCount('incident_clusters', 1);
        $this->assertDatabaseCount('incident_cluster_incident', 2);
    }

    public function test_clustering_is_dispatched_to_queue(): void
    {
        Queue::fake();

        ClusterIncidentsJob::dispatch([123], 5.0);

        Queue::assertPushed(ClusterIncidentsJob::class, function (ClusterIncidentsJob $job): bool {
            return $job->incidentIds === [123] && $job->radiusKm === 5.0;
        });
    }

    private function createIncident(string $code, float $latitude, float $longitude): Incident
    {
        return Incident::factory()
            ->for(User::factory()->admin(), 'reporter')
            ->for(IncidentType::firstOrFail(), 'incidentType')
            ->for(ForestArea::firstOrFail(), 'forestArea')
            ->for(CaseStatus::where('name', 'Reported')->firstOrFail(), 'caseStatus')
            ->state([
                'code' => $code,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ])
            ->create();
    }
}
