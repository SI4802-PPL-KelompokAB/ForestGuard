<?php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RegisterCitizen
{
    public function handle(RegisterRequest $request): User
    {
        $citizenRoleId = Role::query()->where('name', Role::CITIZEN)->value('id');

        $user = new User;
        $user->fill($request->safe()->only(['name', 'email', 'password']));
        $user->role_id = $citizenRoleId;
        $user->is_active = true;
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return $user;
    }
}
