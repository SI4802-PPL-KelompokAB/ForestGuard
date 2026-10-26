<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'sequence',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
