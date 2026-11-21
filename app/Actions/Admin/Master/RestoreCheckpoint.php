<?php

// PBI-04: Bagian kode yang mendukung master data dan proteksi akses.

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
