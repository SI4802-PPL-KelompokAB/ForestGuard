<?php

// PBI-04: Bagian kode yang mendukung master data dan proteksi akses.

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
