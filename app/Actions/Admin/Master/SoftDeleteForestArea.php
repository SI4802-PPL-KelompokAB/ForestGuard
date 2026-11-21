<?php

// PBI-04: Bagian kode yang mendukung master data dan proteksi akses.

namespace App\Actions\Admin\Master;

use App\Models\ForestArea;
use Illuminate\Support\Facades\DB;

class SoftDeleteForestArea
{
    public function handle(ForestArea $forestArea): void
    {
        DB::transaction(function () use ($forestArea): void {
            $forestArea->checkpoints()->delete();
            $forestArea->delete();
        });
    }
}
