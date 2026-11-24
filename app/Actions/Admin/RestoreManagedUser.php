<?php

// PBI-03: Bagian kode yang mendukung manajemen pengguna dan RBAC.

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
