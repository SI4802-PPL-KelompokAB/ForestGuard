<?php

namespace App\Actions\Ranger;

use App\Enums\PatrolStatus;
use App\Exceptions\PatrolConflict;
use App\Models\Patrol;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransitionPatrol
{
    public function handle(User $ranger, int $patrolId, PatrolStatus $target): Patrol
    {
        abort_unless($ranger->isRanger() && $ranger->isUsable(), 403);
        abort_unless(in_array($target, [PatrolStatus::InProgress, PatrolStatus::Completed], true), 409);

        return DB::transaction(function () use ($ranger, $patrolId, $target): Patrol {
            $lockedRanger = User::withTrashed()->whereKey($ranger->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRanger->isRanger() && $lockedRanger->isUsable(), 403);
            $patrol = $lockedRanger->patrols()->whereKey($patrolId)->lockForUpdate()->firstOrFail();
            $expected = $target === PatrolStatus::InProgress ? PatrolStatus::Assigned : PatrolStatus::InProgress;
            if ($patrol->status !== $expected) {
                throw new PatrolConflict('Status patroli sudah berubah. Muat ulang untuk melihat kondisi terbaru.');
            }

            if ($target === PatrolStatus::InProgress) {
                $active = $lockedRanger->patrols()->where('status', PatrolStatus::InProgress)->lockForUpdate()->first();
                if ($active) {
                    throw new PatrolConflict('Selesaikan patroli yang sedang berlangsung sebelum memulai tugas lain.');
                }
                $patrol->started_at = now('UTC');
                $patrol->ended_at = null;
            } else {
                $patrol->ended_at = now('UTC');
            }
            $patrol->status = $target;
            $patrol->save();

            return $patrol;
        }, 3);
    }
}
