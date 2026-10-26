<?php

namespace App\Models;

use App\Enums\SyncRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRecord extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'client_uuid',
        'entity_type',
        'entity_id',
        'payload',
        'status',
        'attempts',
        'last_attempt_at',
        'synced_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => SyncRecordStatus::class,
            'attempts' => 'integer',
            'created_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
