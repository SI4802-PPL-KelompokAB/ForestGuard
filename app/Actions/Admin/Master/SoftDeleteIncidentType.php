<?php

namespace App\Actions\Admin\Master;

use App\Models\IncidentType;

class SoftDeleteIncidentType
{
    public function handle(IncidentType $incidentType): void
    {
        $incidentType->delete();
    }
}
