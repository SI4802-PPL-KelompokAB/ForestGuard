<?php

namespace App\Models;

use Database\Factories\PatrolTrackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatrolTrack extends Model
{
    /** @use HasFactory<PatrolTrackFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'client_uuid',
        'patrol_id',
        'latitude',
        'longitude',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'recorded_at' => 'datetime',
        ];
    }

    public function patrol(): BelongsTo
    {
        return $this->belongsTo(Patrol::class);
    }
}
