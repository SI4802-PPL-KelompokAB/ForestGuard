<?php

namespace App\Actions\Admin;

use App\Models\User;

class RestoreManagedUser
{
    public function handle(User $user): User
    {
        if ($user->trashed()) {
            $user->restore();
        }

        return $user;
    }
}
