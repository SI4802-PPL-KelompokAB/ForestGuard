<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentCluster;
use App\Support\GeoLocationValidator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IncidentClusteringService
{
    /**
     * Rebuild clusters for the selected incidents using centroid distance.
     *
     * @param  list<int>  $incidentIds
     * @return list<IncidentCluster>
     */
    public function cluster(array $incidentIds = [], ?float $radiusKm = null): array
    {
        $radiusKm ??= (float) config('incident_clustering.radius_km', 5.0);
        if ($radiusKm <= 0) {
            throw new InvalidArgumentException('Radius clustering harus lebih besar dari 0 km.');
        }

        return DB::transaction(function () use ($incidentIds, $radiusKm): array {
            $incidents = Incident::query()
                ->when($incidentIds !== [], fn ($query) => $query->whereIn('id', $incidentIds))
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (Incident $incident): bool => $this->hasValidCoordinates($incident))
                ->values();

            $this->removeExistingMemberships($incidents->modelKeys());

            $groups = $this->buildGroups($incidents, $radiusKm);
            $clusters = [];
            foreach ($groups as $group) {
                $clusters[] = $this->storeCluster($group, $radiusKm);
            }

            return $clusters;
        });
    }

    public function distanceKm(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo): float
    {
        return GeoLocationValidator::calculateDistance(
            $latitudeFrom,
            $longitudeFrom,
            $latitudeTo,
            $longitudeTo,
        );
    }

    /** @param list<int> $incidentIds */
    private function removeExistingMemberships(array $incidentIds): void
    {
        if ($incidentIds === []) {
            return;
        }

        $clusterIds = DB::table('incident_cluster_incident')
            ->whereIn('incident_id', $incidentIds)
            ->pluck('incident_cluster_id')
            ->all();

        DB::table('incident_cluster_incident')->whereIn('incident_id', $incidentIds)->delete();

        if ($clusterIds !== []) {
            IncidentCluster::query()
                ->whereIn('id', $clusterIds)
                ->whereDoesntHave('incidents')
                ->delete();
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Incident>  $incidents
     * @return list<list<Incident>>
     */
    private function buildGroups($incidents, float $radiusKm): array
    {
        $remaining = $incidents->all();
        $groups = [];

        while ($remaining !== []) {
            $seed = array_shift($remaining);
            $group = [$seed];
            $changed = true;

            while ($changed) {
                $changed = false;
                $centroid = $this->centroid($group);

                foreach ($remaining as $index => $candidate) {
                    if ($this->distanceKm(
                        $centroid['latitude'],
                        $centroid['longitude'],
                        (float) $candidate->latitude,
                        (float) $candidate->longitude,
                    ) <= $radiusKm) {
                        $group[] = $candidate;
                        unset($remaining[$index]);
                        $changed = true;
                    }
                }

                $remaining = array_values($remaining);
            }

            $groups[] = $group;
        }

        return $groups;
    }

    /** @param list<Incident> $group */
    private function storeCluster(array $group, float $radiusKm): IncidentCluster
    {
        $incidentIds = collect($group)->pluck('id')->sort()->values()->all();
        $centroid = $this->centroid($group);
        $fingerprint = hash('sha256', implode(',', $incidentIds).'|'.$radiusKm);

        $cluster = IncidentCluster::query()->updateOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'centroid_latitude' => $centroid['latitude'],
                'centroid_longitude' => $centroid['longitude'],
                'report_count' => count($group),
                'radius_km' => $radiusKm,
                'status' => 'processed',
                'processed_at' => now(),
            ],
        );

        $cluster->incidents()->sync($incidentIds);

        return $cluster->load('incidents');
    }

    /** @param list<Incident> $group */
    private function centroid(array $group): array
    {
        $count = count($group);

        return [
            'latitude' => array_sum(array_map(fn (Incident $incident): float => (float) $incident->latitude, $group)) / $count,
            'longitude' => array_sum(array_map(fn (Incident $incident): float => (float) $incident->longitude, $group)) / $count,
        ];
    }

    private function hasValidCoordinates(Incident $incident): bool
    {
        return is_numeric($incident->latitude)
            && is_numeric($incident->longitude)
            && (float) $incident->latitude >= -90
            && (float) $incident->latitude <= 90
            && (float) $incident->longitude >= -180
            && (float) $incident->longitude <= 180;
    }
}
