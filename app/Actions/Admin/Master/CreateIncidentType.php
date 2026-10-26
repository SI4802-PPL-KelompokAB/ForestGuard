<?php

namespace App\Actions\Admin\Master;

use App\Http\Requests\Admin\Master\StoreIncidentTypeRequest;
use App\Models\IncidentType;
use Illuminate\Support\Facades\DB;

class CreateIncidentType
{
    public function handle(StoreIncidentTypeRequest $request): IncidentType
    {
        return DB::transaction(function () use ($request): IncidentType {
            return IncidentType::create($request->validated());
        });
    }
}
