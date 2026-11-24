<?php

// PBI-04: Bagian kode yang mendukung master data dan proteksi akses.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RiskLevel extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (RiskLevel $riskLevel): void {
            if ($riskLevel->isDirty(['name', 'sort_order', 'is_active'])) {
                $riskLevel->revision = Str::uuid()->toString();
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
