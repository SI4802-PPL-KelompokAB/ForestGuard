<?php

namespace App\Models;

use App\Enums\ObservationCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Observation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_uuid',
        'patrol_id',
        'category',
        'description',
        'latitude',
        'longitude',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => ObservationCategory::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'observed_at' => 'datetime',
        ];
    }

    public function patrol(): BelongsTo
    {
        return $this->belongsTo(Patrol::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
