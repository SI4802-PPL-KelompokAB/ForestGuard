<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatrolCheckpoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_uuid',
        'patrol_id',
        'checkpoint_id',
        'sequence',
        'visited_at',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'visited_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function patrol(): BelongsTo
    {
        return $this->belongsTo(Patrol::class);
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class)->withTrashed();
    }
}
