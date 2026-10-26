<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    /**
     * role_id is assigned by the server (registration always uses citizen).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $with = [
        'role',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function patrols(): HasMany
    {
        return $this->hasMany(Patrol::class, 'ranger_id');
    }

    public function createdPatrols(): HasMany
    {
        return $this->hasMany(Patrol::class, 'created_by');
    }

    public function reportedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'reporter_id');
    }

    public function incidentAssignments(): HasMany
    {
        return $this->hasMany(IncidentAssignment::class, 'ranger_id');
    }

    public function assignedIncidentAssignments(): HasMany
    {
        return $this->hasMany(IncidentAssignment::class, 'assigned_by');
    }

    public function investigationUpdates(): HasMany
    {
        return $this->hasMany(InvestigationUpdate::class, 'ranger_id');
    }

    public function uploadedEvidences(): HasMany
    {
        return $this->hasMany(Evidence::class, 'uploaded_by');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function syncRecords(): HasMany
    {
        return $this->hasMany(SyncRecord::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'changed_by');
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === Role::ADMIN;
    }

    public function isRanger(): bool
    {
        return $this->role?->name === Role::RANGER;
    }

    public function isCitizen(): bool
    {
        return $this->role?->name === Role::CITIZEN;
    }

    public function isUsable(): bool
    {
        return $this->is_active && ! $this->trashed();
    }
}
