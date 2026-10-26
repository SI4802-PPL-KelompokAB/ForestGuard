<?php

namespace App\Actions\Admin\Master;

use App\Models\IncidentType;

class RestoreIncidentType
{
    public function handle(IncidentType $incidentType): IncidentType
    {
        if ($incidentType->trashed()) {
            $incidentType->restore();
        }

        return $incidentType;
    }
}
