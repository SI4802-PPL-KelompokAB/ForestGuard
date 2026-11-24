<?php

// PBI-04: Bagian kode yang mendukung master data dan proteksi akses.

namespace App\Actions\Admin\Master;

use App\Models\ForestArea;
use Illuminate\Support\Facades\DB;

class RestoreForestArea
{
    public function handle(ForestArea $forestArea): ForestArea
    {
        return DB::transaction(function () use ($forestArea): ForestArea {
            if ($forestArea->trashed()) {
                $forestArea->restore();
                $forestArea->checkpoints()->onlyTrashed()->restore();
            }

            return $forestArea;
        });
    }
}
