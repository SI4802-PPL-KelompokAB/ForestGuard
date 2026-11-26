<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class IncidentCluster extends Model
{
    use HasFactory;

    protected $fillable = [
        'fingerprint',
        'centroid_latitude',
        'centroid_longitude',
        'report_count',
        'radius_km',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'centroid_latitude' => 'decimal:7',
            'centroid_longitude' => 'decimal:7',
            'report_count' => 'integer',
            'radius_km' => 'decimal:3',
            'processed_at' => 'datetime',
        ];
    }

    public function incidents(): BelongsToMany
    {
        return $this->belongsToMany(Incident::class, 'incident_cluster_incident');
    }
}
