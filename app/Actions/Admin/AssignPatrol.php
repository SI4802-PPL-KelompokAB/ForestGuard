<?php

namespace App\Actions\Admin;

use App\Enums\PatrolStatus;
use App\Events\PatrolAssigned;
use App\Models\Checkpoint;
use App\Models\ForestArea;
use App\Models\Patrol;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AssignPatrol
{
    /**
     * @param  array{ranger_id: int|string, forest_area_id: int|string, scheduled_at: string, checkpoint_ids: array<int, int|string>}  $data
     */
    public function handle(User $admin, array $data): Patrol
    {
        abort_unless($admin->isAdmin() && $admin->isUsable(), 403);

        return DB::transaction(function () use ($admin, $data): Patrol {
            $ranger = User::query()->whereKey($data['ranger_id'])->lockForUpdate()->first();
            if (! $ranger || ! $ranger->isUsable() || ! $ranger->isRanger()) {
                throw ValidationException::withMessages(['ranger_id' => 'Pilih Ranger aktif yang tersedia.']);
            }

            $area = ForestArea::query()->whereKey($data['forest_area_id'])->lockForUpdate()->first();
            if (! $area) {
                throw ValidationException::withMessages(['forest_area_id' => 'Pilih area hutan yang tersedia.']);
            }

            $checkpoints = Checkpoint::query()->whereIn('id', $data['checkpoint_ids'])
                ->where('forest_area_id', $area->id)->orderBy('id')->lockForUpdate()->get();
            if ($checkpoints->isEmpty() || $checkpoints->count() !== count($data['checkpoint_ids'])) {
                throw ValidationException::withMessages(['checkpoint_ids' => 'Checkpoint yang dipilih tidak sesuai area hutan atau sudah tidak tersedia.']);
            }

            $patrol = Patrol::query()->create([
                'code' => 'P-'.Str::ulid(),
                'client_uuid' => (string) Str::uuid(),
                'ranger_id' => $ranger->id,
                'forest_area_id' => $area->id,
                'created_by' => $admin->id,
                'scheduled_at' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['scheduled_at'], config('app.timezone'))->utc(),
                'created_at' => CarbonImmutable::now('UTC'),
                'status' => PatrolStatus::Assigned,
            ]);

            foreach ($checkpoints->sortBy([['code', 'asc'], ['id', 'asc']])->values() as $index => $checkpoint) {
                $patrol->patrolCheckpoints()->create([
                    'client_uuid' => (string) Str::uuid(),
                    'checkpoint_id' => $checkpoint->id,
                    'sequence' => $index + 1,
                ]);
            }

            DB::afterCommit(function () use ($patrol): void {
                try {
                    PatrolAssigned::dispatch($patrol->id, $patrol->ranger_id, $patrol->created_by);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

            return $patrol;
        }, 3);
    }
}
