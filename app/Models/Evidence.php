<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Evidence extends Model
{
    public $timestamps = false;

    protected $table = 'evidences';

    protected $fillable = [
        'client_uuid',
        'incident_id',
        'observation_id',
        'update_id',
        'uploaded_by',
        'file_path',
        'mime_type',
        'file_size',
        'latitude',
        'longitude',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'captured_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Evidence $evidence): void {
            $parents = collect([
                $evidence->incident_id,
                $evidence->observation_id,
                $evidence->update_id,
            ])->filter(fn ($id) => $id !== null)->count();

            if ($parents !== 1) {
                throw new InvalidArgumentException(
                    'Evidence must belong to exactly one of incident, observation, or investigation update.'
                );
            }
        });
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function observation(): BelongsTo
    {
        return $this->belongsTo(Observation::class);
    }

    public function investigationUpdate(): BelongsTo
    {
        return $this->belongsTo(InvestigationUpdate::class, 'update_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
