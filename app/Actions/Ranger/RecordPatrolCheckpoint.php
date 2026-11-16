<?php

namespace App\Actions\Ranger;

use App\Enums\PatrolStatus;
use App\Exceptions\PatrolConflict;
use App\Models\PatrolCheckpoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordPatrolCheckpoint
{
    public function __construct(private RecordPatrolTrack $recordPatrolTrack) {}

    /** @param array{latitude: float|string, longitude: float|string} $coordinates */
    public function handle(User $ranger, int $patrolId, int $plannedCheckpointId, array $coordinates): PatrolCheckpoint
    {
        abort_unless($ranger->isRanger() && $ranger->isUsable(), 403);

        return DB::transaction(function () use ($ranger, $patrolId, $plannedCheckpointId, $coordinates): PatrolCheckpoint {
            $lockedRanger = User::withTrashed()->whereKey($ranger->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRanger->isRanger() && $lockedRanger->isUsable(), 403);
            $patrol = $lockedRanger->patrols()->whereKey($patrolId)->lockForUpdate()->firstOrFail();
            if ($patrol->status !== PatrolStatus::InProgress) {
                throw new PatrolConflict('Checkpoint hanya dapat dicatat saat patroli Berlangsung.');
            }

            $planned = $patrol->patrolCheckpoints()->whereKey($plannedCheckpointId)->lockForUpdate()->firstOrFail();
            if ($planned->visited_at !== null) {
                return $planned;
            }

            $planned->update([
                'visited_at' => now('UTC'),
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
            ]);

            $this->recordPatrolTrack->handle($lockedRanger, $patrol->id, [[
                'client_uuid' => (string) Str::uuid(),
                'latitude' => $planned->latitude,
                'longitude' => $planned->longitude,
                'recorded_at' => $planned->visited_at->toISOString(),
            ]]);

            return $planned;
        }, 3);
    }
}
