<?php

namespace App\Actions\Admin\Master;

use App\Http\Requests\Admin\Master\StoreCheckpointRequest;
use App\Models\Checkpoint;
use Illuminate\Support\Facades\DB;

class CreateCheckpoint
{
    public function handle(StoreCheckpointRequest $request): Checkpoint
    {
        return DB::transaction(function () use ($request): Checkpoint {
            return Checkpoint::create($request->validated());
        });
    }
}
