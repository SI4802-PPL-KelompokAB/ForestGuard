<?php

namespace App\Actions\Admin\Master;

use App\Http\Requests\Admin\Master\UpdateCheckpointRequest;
use App\Models\Checkpoint;
use Illuminate\Support\Facades\DB;

class UpdateCheckpoint
{
    public function handle(UpdateCheckpointRequest $request, Checkpoint $checkpoint): Checkpoint
    {
        return DB::transaction(function () use ($request, $checkpoint): Checkpoint {
            $checkpoint->update($request->validated());

            return $checkpoint;
        });
    }
}
