<?php

namespace App\Actions\Admin;

use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;

class UpdateManagedUser
{
    public function handle(UpdateUserRequest $request, User $user): User
    {
        $actor = $request->user();
        $isSelf = $actor !== null && $actor->is($user);

        $user->name = $request->validated('name');

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        if (! $isSelf) {
            $user->is_active = $request->boolean('is_active');
            $user->role_id = Role::query()->where('name', $request->validated('role'))->value('id');
        }

        $user->save();

        return $user;
    }
}
