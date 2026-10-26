<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForestArea extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'area_ha',
        'boundary',
        'center_lat',
        'center_lng',
    ];

    protected function casts(): array
    {
        return [
            'area_ha' => 'decimal:2',
            'boundary' => 'array',
            'center_lat' => 'decimal:7',
            'center_lng' => 'decimal:7',
        ];
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(Checkpoint::class);
    }

    public function patrols(): HasMany
    {
        return $this->hasMany(Patrol::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
