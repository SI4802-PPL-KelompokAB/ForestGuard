<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\RoleHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.guest', ['mode' => 'login']);
    }

    public function store(LoginRequest $request, AttemptLogin $attemptLogin, RoleHome $roleHome): RedirectResponse
    {
        $user = $attemptLogin->handle($request);

        return redirect()->intended($roleHome->url($user));
    }
}
