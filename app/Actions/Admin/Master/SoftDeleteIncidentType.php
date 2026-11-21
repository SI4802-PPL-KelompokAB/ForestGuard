<?php

// PBI-04: Bagian kode yang mendukung master data dan proteksi akses.

namespace App\Actions\Admin\Master;

use App\Models\IncidentType;

class SoftDeleteIncidentType
{
    public function handle(IncidentType $incidentType): void
    {
        $incidentType->delete();
    }
}
