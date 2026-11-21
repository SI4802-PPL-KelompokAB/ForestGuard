<?php

// PBI-04: Bagian kode yang mendukung master data dan proteksi akses.

namespace App\Actions\Admin\Master;

use App\Models\Checkpoint;

class SoftDeleteCheckpoint
{
    public function handle(Checkpoint $checkpoint): void
    {
        $checkpoint->delete();
    }
}
