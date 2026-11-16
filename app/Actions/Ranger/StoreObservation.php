<?php

namespace App\Actions\Ranger;

use App\Actions\Evidence\StoreEvidence;
use App\Enums\PatrolStatus;
use App\Exceptions\PatrolConflict;
use App\Models\Evidence;
use App\Models\Observation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoreObservation
{
    public function __construct(private StoreEvidence $storeEvidence) {}

    /** @param array{client_uuid: string, category: string, description: string, latitude: float|string, longitude: float|string, location_recorded_at: string} $data */
    public function handle(User $ranger, int $patrolId, array $data, ?UploadedFile $photo = null): Observation
    {
        abort_unless($ranger->isRanger() && $ranger->isUsable(), 403);
        $evidence = null;

        try {
            return DB::transaction(function () use ($ranger, $patrolId, $data, $photo, &$evidence): Observation {
                $lockedRanger = User::withTrashed()->whereKey($ranger->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedRanger->isRanger() && $lockedRanger->isUsable(), 403);
                $patrol = $lockedRanger->patrols()->whereKey($patrolId)->lockForUpdate()->firstOrFail();
                if ($patrol->status !== PatrolStatus::InProgress) {
                    throw new PatrolConflict('Observasi hanya dapat ditambahkan saat patroli Berlangsung.');
                }

                $existing = Observation::where('client_uuid', $data['client_uuid'])->first();
                if ($existing !== null) {
                    if ($existing->patrol_id !== $patrol->id) {
                        throw new PatrolConflict('UUID observasi sudah digunakan. Muat ulang formulir.');
                    }

                    return $existing;
                }

                $observation = $patrol->observations()->create([
                    'client_uuid' => $data['client_uuid'],
                    'category' => $data['category'],
                    'description' => trim($data['description']),
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'location_recorded_at' => $data['location_recorded_at'],
                    'observed_at' => now('UTC'),
                ]);

                if ($photo !== null) {
                    $evidence = $this->storeEvidence->handle($observation, $photo, $lockedRanger);
                }

                return $observation->load('evidences');
            });
        } catch (Throwable $exception) {
            if ($evidence instanceof Evidence) {
                try {
                    Storage::disk(config('filesystems.evidence_disk', 's3'))->delete($evidence->file_path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }
}
