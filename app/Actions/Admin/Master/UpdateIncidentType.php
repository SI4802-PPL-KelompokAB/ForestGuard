<?php

namespace App\Actions\Admin\Master;

use App\Http\Requests\Admin\Master\UpdateIncidentTypeRequest;
use App\Models\IncidentType;
use Illuminate\Support\Facades\DB;

class UpdateIncidentType
{
    public function handle(UpdateIncidentTypeRequest $request, IncidentType $incidentType): IncidentType
    {
        return DB::transaction(function () use ($request, $incidentType): IncidentType {
            $incidentType->update($request->validated());

            return $incidentType;
        });
    }
}
