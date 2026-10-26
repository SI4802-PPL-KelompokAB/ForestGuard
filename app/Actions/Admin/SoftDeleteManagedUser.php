<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class SoftDeleteManagedUser
{
    public function handle(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ]);
        }

        $user->delete();
    }
}
