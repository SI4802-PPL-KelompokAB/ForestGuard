<?php

namespace App\Actions\Admin;

use App\Http\Requests\Admin\StoreUserRequest;
use App\Mail\TemporaryPasswordMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class CreateManagedUser
{
    /**
     * @return array{user: User, email_sent: bool, temporary_password: string}
     */
    public function handle(StoreUserRequest $request): array
    {
        $roleId = Role::query()->where('name', $request->validated('role'))->value('id');
        $plainPassword = Str::password(12);

        $user = DB::transaction(function () use ($request, $roleId, $plainPassword): User {
            $user = new User;
            $user->fill($request->safe()->only(['name', 'email']));
            $user->password = $plainPassword;
            $user->role_id = $roleId;
            $user->is_active = true;
            $user->save();

            return $user;
        });

        $emailSent = false;

        try {
            Mail::to($user->email)->send(new TemporaryPasswordMail($user, $plainPassword));
            $emailSent = true;
        } catch (Throwable $e) {
            Log::error('Gagal mengirim email kata sandi sementara untuk pengguna ID '.$user->id.': '.$e->getMessage(), [
                'exception' => $e,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        return [
            'user' => $user,
            'email_sent' => $emailSent,
            'temporary_password' => $plainPassword,
        ];
    }
}
