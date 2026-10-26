<?php

namespace App\Actions\Admin\Master;

use App\Models\Checkpoint;

class RestoreCheckpoint
{
    public function handle(Checkpoint $checkpoint): Checkpoint
    {
        if ($checkpoint->trashed()) {
            $checkpoint->restore();
        }

        return $checkpoint;
    }
}
