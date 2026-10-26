<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public $timestamps = false;

    public const ADMIN = 'admin';

    public const RANGER = 'ranger';

    public const CITIZEN = 'citizen';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::ADMIN => 'Admin',
            self::RANGER => 'Ranger',
            self::CITIZEN => 'Warga',
        ];
    }

    public function label(): string
    {
        return self::options()[$this->name] ?? $this->name;
    }

    protected $fillable = [
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
