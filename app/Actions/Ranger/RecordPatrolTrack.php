<?php

namespace App\Actions\Ranger;

use App\Enums\PatrolStatus;
use App\Exceptions\PatrolConflict;
use App\Models\PatrolTrack;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecordPatrolTrack
{
    /**
     * Record one or more track points for an in-progress patrol and update total distance.
     *
     * @param  array<int, array{client_uuid: string, latitude: float|string, longitude: float|string, recorded_at?: string|CarbonImmutable|null}>  $points
     * @return Collection<int, PatrolTrack>
     */
    public function handle(User $ranger, int $patrolId, array $points): Collection
    {
        abort_unless($ranger->isRanger() && $ranger->isUsable(), 403);

        return DB::transaction(function () use ($ranger, $patrolId, $points): Collection {
            $lockedRanger = User::withTrashed()->whereKey($ranger->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRanger->isRanger() && $lockedRanger->isUsable(), 403);

            $patrol = $lockedRanger->patrols()->whereKey($patrolId)->lockForUpdate()->firstOrFail();

            if ($patrol->status !== PatrolStatus::InProgress) {
                throw new PatrolConflict('Titik rute hanya dapat dicatat saat patroli berstatus sedang berlangsung.');
            }

            // Sort incoming points chronologically
            usort($points, function (array $a, array $b): int {
                $timeA = isset($a['recorded_at']) ? strtotime((string) $a['recorded_at']) : 0;
                $timeB = isset($b['recorded_at']) ? strtotime((string) $b['recorded_at']) : 0;

                return $timeA <=> $timeB;
            });

            $lastTrack = $patrol->tracks()->orderByDesc('recorded_at')->orderByDesc('id')->first();
            $accumulatedDistanceKm = 0.0;
            $results = collect();

            foreach ($points as $point) {
                $clientUuid = (string) $point['client_uuid'];

                // Idempotent: check if track with same client_uuid already exists
                $existing = PatrolTrack::where('client_uuid', $clientUuid)->first();
                if ($existing) {
                    $results->push($existing);

                    continue;
                }

                $recordedAt = isset($point['recorded_at']) && $point['recorded_at'] !== null
                    ? CarbonImmutable::parse($point['recorded_at'], 'UTC')
                    : now('UTC');

                $newTrack = $patrol->tracks()->create([
                    'client_uuid' => $clientUuid,
                    'latitude' => $point['latitude'],
                    'longitude' => $point['longitude'],
                    'recorded_at' => $recordedAt,
                ]);

                if ($lastTrack) {
                    $accumulatedDistanceKm += $this->calculateHaversineDistance(
                        (float) $lastTrack->latitude,
                        (float) $lastTrack->longitude,
                        (float) $newTrack->latitude,
                        (float) $newTrack->longitude
                    );
                }

                $lastTrack = $newTrack;
                $results->push($newTrack);
            }

            if ($accumulatedDistanceKm > 0) {
                $currentDistance = (float) ($patrol->total_distance_km ?? 0.0);
                $patrol->total_distance_km = round($currentDistance + $accumulatedDistanceKm, 3);
                $patrol->save();
            }

            return $results;
        }, 3);
    }

    /**
     * Calculate the great-circle distance between two points in kilometers using Haversine formula.
     */
    public function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return $angle * $earthRadiusKm;
    }
}
