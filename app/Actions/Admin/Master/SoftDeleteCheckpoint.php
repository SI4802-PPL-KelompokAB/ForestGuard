<?php

namespace App\Actions\Admin\Master;

use App\Models\Checkpoint;

class SoftDeleteCheckpoint
{
    public function handle(Checkpoint $checkpoint): void
    {
        $checkpoint->delete();
    }
}
