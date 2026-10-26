<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestigationUpdate extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'client_uuid',
        'incident_id',
        'ranger_id',
        'note',
        'handling_status_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function ranger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ranger_id');
    }

    public function handlingStatus(): BelongsTo
    {
        return $this->belongsTo(CaseStatus::class, 'handling_status_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class, 'update_id');
    }
}
