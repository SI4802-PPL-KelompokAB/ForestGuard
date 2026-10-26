<?php

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
