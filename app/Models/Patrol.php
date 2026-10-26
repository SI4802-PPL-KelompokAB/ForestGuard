<?php

namespace App\Models;

use App\Casts\PatrolTimestamp;
use App\Enums\PatrolStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patrol extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'code',
        'client_uuid',
        'ranger_id',
        'forest_area_id',
        'created_by',
        'scheduled_at',
        'started_at',
        'ended_at',
        'total_distance_km',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'client_uuid' => 'string',
            'scheduled_at' => PatrolTimestamp::class,
            'started_at' => PatrolTimestamp::class,
            'ended_at' => PatrolTimestamp::class,
            'total_distance_km' => 'decimal:3',
            'status' => PatrolStatus::class,
            'created_at' => PatrolTimestamp::class,
        ];
    }

    public function ranger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ranger_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function forestArea(): BelongsTo
    {
        return $this->belongsTo(ForestArea::class)->withTrashed();
    }

    public function patrolCheckpoints(): HasMany
    {
        return $this->hasMany(PatrolCheckpoint::class)->orderBy('sequence');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(PatrolTrack::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
