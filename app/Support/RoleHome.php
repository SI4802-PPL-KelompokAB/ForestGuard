<?php

namespace App\Support;

use App\Models\User;

class RoleHome
{
    public function routeName(?User $user): string
    {
        if ($user?->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($user?->isRanger()) {
            return 'ranger.home';
        }

        return 'citizen.home';
    }

    public function url(?User $user): string
    {
        return route($this->routeName($user));
    }
}
